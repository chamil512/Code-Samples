<?php

namespace App\Traits;

use App\Repositories\BaseRepository;
use App\Services\Notify;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use App\Traits\UrlHelper as UrlHelper;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Admin\Services\Permission;
use App\SystemApproval;

/**
 * Trait Approval
 * @package App\Traits
 */
trait Approval
{
    use UrlHelper;

    //Within your class following commented properties should have been implemented to use this trait
    public string $approvalField = "approval_status"; //model's database column for approval status;Ex:approval_status;
    public $approvalDefaultStatus = "0"; //default status value for field;Ex:0:Pending
    protected array $approvalSteps = [
        [
            "step" => "approval",
            "approvedStatus" => 1, //status which need to be updated for $approvalField of the model when approves
            "declinedStatus" => 2, //status which need to be updated for $approvalField of the model when declines
            "permissionRoutes" => [], //Ex: /academic/academic_timetable/approve
            "route" => "", //Ex: /academic/academic_timetable/approve; You should use 'GET' method route for approval form and 'POST' method route for approval process
        ]
    ];

    public string $step = "";
    public bool $isSpecificUserRequired = false;
    private string $url = "";
    private string $route = "";
    private string $title = "";
    private string $description = "";
    private array $userIds = [];
    private array $permissionRoutes = [];
    private bool $haveErrors = false;
    private array $errors = [];
    private array $approvedSubmitStatuses = [];
    private array $declinedSubmitStatuses = [];
    private array $approvalStatusList = [
        ["id" => "0", "name" => "Pending", "label" => "warning"],
        ["id" => "1", "name" => "Approve", "label" => "success"],
        ["id" => "2", "name" => "Reject", "label" => "danger"],
    ];
    private $approvedStatus = "1";
    private $declinedStatus;

    /**
     * @param $model
     */
    protected function setApprovalData($model)
    {

    }

    /**
     * @param $model
     * @param $step
     * @return string
     */
    protected function getApprovalStepTitle($model, $step): string
    {
        $name = "";
        if (isset($model->name)) {
            $name = $model->name . " ";
        }

        return $name . $step . ".";
    }

    /**
     * @param $model
     * @param $step
     * @return string|Application|Factory|View
     */
    protected function getApprovalStepDescription($model, $step): View
    {
        $name = "";
        if (isset($model->name)) {
            $name = $model->name;
        }

        return "Your " . $step . " is required for " . $name . ".";
    }

    /**
     * @param $model
     * @param $step
     * @return array
     */
    protected function getApprovalStepUsers($model, $step): array
    {
        return [];
    }

    /**
     * @param $model
     * @param $step
     * @param $previousStatus
     * @return void
     */
    protected function onApproved($model, $step, $previousStatus)
    {
    }

    /**
     * @param $model
     * @param $step
     * @param $previousStatus
     * @return void
     */
    protected function onDeclined($model, $step, $previousStatus)
    {
    }

    /**
     * @param $model
     * @param $step
     * @return string
     */
    protected function getApprovalRoute($model, $step): string
    {
        $currUrl = url()->current();
        $module = $this->getModuleFromUri($currUrl);
        $controller = $this->getControllerFromRoute($currUrl);

        if ($module != "") {
            $route = "/" . $module . "/" . $controller . "/" . $step;
        } else {
            $route = "/" . $controller . "/" . $step;
        }

        return $route;
    }

    /**
     * @param $model
     * @param $step
     * @param null $approvalStep
     * @return string
     */
    protected function getApprovalUrl($model, $step = "", $approvalStep = null): string
    {
        //setup approval status information
        if (!$approvalStep) {

            $approvalStep = $this->getApprovalStep($model, $step);
        }

        $url = "";
        if ($approvalStep) {

            if (isset($approvalStep["route"])) {
                $route = $approvalStep["route"];
            } else {
                $step = $approvalStep["step"];
                $route = $this->getApprovalRoute($model, $step);
            }

            $slash = "/";
            $route = rtrim($route, $slash);
            $route = $route . "/" . $model->id;

            $url = URL::to($route);
        }

        return $url;
    }

    /**
     * @param $model
     * @return array
     */
    public function triggerApprovalProcess($model): array
    {
        //setup approval status information
        $approvalInfo = $this->getApprovalInfoPrepared($model);

        if ($approvalInfo["notify"]["status"] == "success") {
            $modelName = $this->getClassName($model);
            $modelHash = $this->generateClassNameHash($modelName);

            $sysApp = SystemApproval::query()->where(["approval_step" => $this->step, "model_hash" => $modelHash, "model_id" => $model->id])->first();

            if (!$sysApp) {
                $sysApp = new SystemApproval();
                $sysApp->title = $this->title;
                $sysApp->description = $this->description;
                $sysApp->approval_url = $this->url;
                $sysApp->approval_step = $this->step;
                $sysApp->model_id = $model->id;
                $sysApp->model_name = $modelName;
                $sysApp->model_hash = $modelHash;
                $sysApp->status = 0;

                DB::beginTransaction();
                if ($sysApp->save()) {
                    $send = Notify::send($this->title, $this->description, $this->url, $this->userIds, $this->permissionRoutes);

                    $response = [];
                    if ($send["status"] === "success") {

                        $response["notify"]["status"] = "success";
                        $response["notify"]["notify"][] = "Successfully sent the notification.";

                        DB::commit();
                    } else {

                        $response["notify"] = $send;
                        DB::rollBack();
                    }
                } else {
                    DB::rollBack();
                    $response = [];
                    $response["notify"]["status"] = "failed";
                    $response["notify"]["notify"][] = "Error occurred while starting the approval process.";
                }
            } else {
                $sysApp->title = $this->title;
                $sysApp->description = $this->description;
                $sysApp->approval_url = $this->url;

                DB::beginTransaction();
                if ($sysApp->save()) {
                    $send = Notify::send($this->title, $this->description, $this->url, $this->userIds, $this->permissionRoutes);

                    $response = [];
                    if ($send["status"] === "success") {

                        $response["notify"]["status"] = "success";
                        $response["notify"]["notify"][] = "Successfully resent the notification.";

                        DB::commit();
                    } else {

                        $response["notify"] = $send;
                        DB::rollBack();
                    }
                } else {
                    DB::rollBack();
                    $response = [];
                    $response["notify"]["status"] = "failed";
                    $response["notify"]["notify"][] = "Error occurred while starting the approval process.";
                }
            }

            //for debugging purpose
            $response["approval_info"] = $approvalInfo;
        } else {
            $response = $approvalInfo;
        }

        return $response;
    }

    /**
     * @param $model
     * @param string $step
     * @return array
     */
    public function getApprovalInfoPrepared($model, string $step = ""): array
    {
        $this->setApprovalData($model);

        //get next approval status
        $approvalStep = $this->getApprovalStep($model, $step);

        if ($approvalStep && isset($approvalStep["step"])) {
            $step = $approvalStep["step"];

            if (isset($approvalStep["route"])) {
                $route = $approvalStep["route"];
            } else {
                $route = $this->getApprovalRoute($model, $step);
            }

            if ($route !== "") {

                $this->userIds = $this->getApprovalStepUsers($model, $step);

                if (!$this->isSpecificUserRequired || count($this->userIds) > 0) {

                    $slash = "/";
                    $route = rtrim($route, $slash);
                    $route = $route . "/" . $model->id;

                    $this->url = URL::to($route);
                    $this->route = $route;
                    $this->title = $this->getApprovalStepTitle($model, $step);
                    $this->description = $this->getApprovalStepDescription($model, $step);
                    $this->step = $step;
                    $this->approvedStatus = $approvalStep["approvedStatus"];
                    $this->declinedStatus = $approvalStep["declinedStatus"];

                    if (isset($approvalStep["approvalStatuses"])) {

                        $this->approvalStatusList = $approvalStep["approvalStatuses"];
                    }

                    if (isset($approvalStep["approvedSubmitStatuses"])) {

                        $this->approvedSubmitStatuses = $approvalStep["approvedSubmitStatuses"];
                    }

                    if (isset($approvalStep["declinedSubmitStatuses"])) {

                        $this->declinedSubmitStatuses = $approvalStep["declinedSubmitStatuses"];
                    }

                    //check if additional permissions has been set
                    $permissionRoutes = [];
                    if (isset($approvalStep["permissionRoutes"]) && count($approvalStep["permissionRoutes"]) > 0) {
                        $permissionRoutes = $approvalStep["permissionRoutes"];
                    }

                    $this->permissionRoutes = $permissionRoutes;

                    $response["notify"]["status"] = "success";
                } else {
                    $response["notify"]["status"] = "failed";
                    $response["notify"]["notify"][] = "Approval request sending was failed";
                    $response["notify"]["notify"][] = "Users could not found in the system who are eligible to perform the operation";
                }
            } else {
                $response["notify"]["status"] = "failed";
                $response["notify"]["notify"][] = "Approval route has not been setup.";
            }
        } else {
            $response["notify"]["status"] = "failed";
            $response["notify"]["notify"][] = "Approval steps have not been setup.";
        }

        //for debugging purpose
        $response["approval_step"] = $approvalStep;
        $response["url"] = $this->url;

        return $response;
    }

    /**
     * @param $model
     * @param $step
     * @return array|false
     */
    private function getApprovalStep($model, $step)
    {
        $approvalStep = false;
        $approvalSteps = $this->approvalSteps;
        $modelStatus = $model->{$this->approvalField};

        if ($modelStatus == $this->approvalDefaultStatus) {
            if (is_array($approvalSteps) && count($approvalSteps) > 0) {
                //next approval step
                $approvalStep = $approvalSteps[0];
            }
        } else {

            if (is_array($approvalSteps)) {
                $count = count($approvalSteps);

                if ($count > 0) {
                    foreach ($approvalSteps as $key => $thisStep) {

                        if ($step !== "") {

                            if ($thisStep["step"] == $step) {

                                $approvalStep = $thisStep;
                            }
                        } else {
                            if ($thisStep["approvedStatus"] == $modelStatus || $thisStep["declinedStatus"] == $modelStatus) {
                                if ($thisStep["approvedStatus"] == $modelStatus) {
                                    $nextKey = $key + 1;

                                    if (isset($approvalSteps[$nextKey])) {
                                        //next approval step
                                        $approvalStep = $approvalSteps[$nextKey];
                                    } else {
                                        $approvalStep = $thisStep;
                                    }
                                } else {
                                    $approvalStep = $thisStep;
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }

        return $approvalStep;
    }

    /**
     * @param $model
     * @param $step
     * @param string $path
     * @return Application|Factory|JsonResponse|RedirectResponse|View|null
     */
    public function renderApprovalView($model, $step, $path = "")
    {
        $this->setApprovalData($model);

        $baseRepo = new BaseRepository();

        if ($this->_isStepAllowed($model, $step)) {
            //setup approval status information
            $approvalInfo = $this->getApprovalInfoPrepared($model, $step);

            if ($approvalInfo["notify"]["status"] == "success") {
                if ($this->_havePermissionForApproval()) {
                    $modelName = $this->getClassName($model);
                    $modelHash = $this->generateClassNameHash($modelName);

                    $approval = SystemApproval::query()->where(["approval_step" => $step, "model_hash" => $modelHash, "model_id" => $model->id])->first();

                    if ($approval) {
                        $formSubmitUrl = $this->url;

                        if ($path === "") {
                            $path = "dashboard.approval.view";
                        }

                        $approvalStatusList = $this->approvalStatusList;

                        $record = $model->toArray();
                        $record = $this->getRecordPrepared($record);

                        return view($path, compact('formSubmitUrl', 'approval', 'approvalStatusList', 'record'));
                    } else {
                        $response["notify"]["status"] = "failed";
                        $response["notify"]["notify"][] = $model->name . " " . $step . " approval request does not exist";
                    }
                } else {
                    $response["notify"]["status"] = "failed";
                    $response["notify"]["notify"][] = "You don't have permission to perform this operation.";
                }
            } else {
                $response = $approvalInfo;
            }
        } else {
            $response["notify"]["status"] = "failed";
            if ($this->haveErrors) {
                $response["notify"]["notify"] = $this->errors;
            } else {
                $response["notify"]["notify"][] = "Unknown error occurred.";
            }
        }

        return $baseRepo->handleResponse($response);
    }

    /**
     * @param $model
     * @param $step
     * @return JsonResponse|RedirectResponse|null
     */
    public function processApprovalSubmission($model, $step)
    {
        DB::beginTransaction();

        $success = false;

        $baseRepo = new BaseRepository();
        try {

            $this->setApprovalData($model);

            if ($this->_isStepAllowed($model, $step)) {
                //setup approval status information
                $approvalInfo = $this->getApprovalInfoPrepared($model, $step);

                if ($approvalInfo["notify"]["status"] === "success") {
                    if ($this->_havePermissionForApproval()) {
                        $modelName = $this->getClassName($model);
                        $modelHash = $this->generateClassNameHash($modelName);

                        $approval = SystemApproval::query()->where(["approval_step" => $step, "model_hash" => $modelHash, "model_id" => $model->id])->first();

                        $approvedStatus = $this->approvedStatus;
                        $declinedStatus = $this->declinedStatus;
                        if ($approval) {

                            $status = request()->post("status");

                            if ($status !== null) {

                                if (in_array($status, $this->approvedSubmitStatuses)) {
                                    $status = 1;
                                    $approvedStatus = $status;
                                }

                                if (in_array($status, $this->declinedSubmitStatuses)) {
                                    $status = 0;
                                    $declinedStatus = $status;
                                }

                                if ($status !== 1) {

                                    $approval = $baseRepo->getValidatedData($approval, [
                                        "remarks" => "required",
                                    ]);
                                } else {
                                    $baseRepo->isValidData = true;
                                }
                            } else {

                                $approval = $baseRepo->getValidatedData($approval, [
                                    "status" => "required|digits:1",
                                    "remarks" => "required",
                                ]);
                            }

                            if ($baseRepo->isValidData) {

                                $approval->status = $status;
                                $approval->approval_by = auth("admin")->user()->admin_id;
                                $response = $baseRepo->saveModel($approval);

                                if ($response["notify"]["status"] == "success") {

                                    $previousStatus = $model->{$this->approvalField};
                                    if ($approval->status == 1) {

                                        $model->{$this->approvalField} = $approvedStatus;
                                        $baseRepo->saveModel($model);

                                        $this->onApproved($model, $step, $previousStatus);

                                        if ($this->_haveMoreStepsLeft($step)) {
                                            //trigger next approval step in the process
                                            $this->triggerApprovalProcess($model);
                                        }
                                    } else {
                                        $model->{$this->approvalField} = $declinedStatus;
                                        $baseRepo->saveModel($model);

                                        $this->onDeclined($model, $step, $previousStatus);
                                    }

                                    $success = true;
                                }
                            } else {
                                $response = $approval;
                            }
                        } else {
                            $response["notify"]["status"] = "failed";
                            $response["notify"]["notify"][] = $model->name . " " . $step . " approval request does not exist";
                        }
                    } else {
                        $response["notify"]["status"] = "failed";
                        $response["notify"]["notify"][] = "You don't have permission to perform this operation.";
                    }
                } else {
                    $response = $approvalInfo;
                }
            } else {
                $response["notify"]["status"] = "failed";
                if ($this->haveErrors) {
                    $response["notify"]["notify"] = $this->errors;
                } else {
                    $response["notify"]["notify"][] = "Unknown error occurred.";
                }
            }
        } catch (Exception $exception) {

            $success = false;

            $response["notify"]["notify"][] = "Something went wrong. Please try again later.";
            $response["error"] = $exception->getMessage() . " in " . $exception->getFile() . " @ " . $exception->getLine();;
        }

        if ($success) {

            DB::commit();
        } else {

            DB::rollBack();
        }

        return $baseRepo->handleResponse($response);
    }

    /**
     * @return bool
     */
    private function _havePermissionForApproval(): bool
    {
        $permissionRoutes = $this->permissionRoutes;
        if (!in_array($this->route, $permissionRoutes)) {
            $permissionRoutes[] = $this->route;
        }

        $adminId = auth("admin")->user()->admin_id;
        $adminIds = [];
        $adminIds[] = $adminId;

        if (isset($this->userIds) && is_array($this->userIds) && count($this->userIds) > 0) {

            if (!in_array($adminId, $this->userIds)) {
                return false;
            }
        }

        $adminIds = Permission::getAdminIdsWithRoutesPermission($permissionRoutes, $adminIds);
        if (count($adminIds) > 0 && in_array(auth("admin")->user()->admin_id, $adminIds)) {

            return true;
        }

        return false;
    }

    /**
     * @param $model
     * @param $step
     * @return bool
     */
    private function _isStepAllowed($model, $step): bool
    {
        $allowed = false;
        $approvalSteps = $this->approvalSteps;

        if (count($approvalSteps) > 0) {

            $prevApprovedStatus = $this->approvalDefaultStatus;
            $prevStep = [];
            foreach ($approvalSteps as $key => $approvalStep) {

                if ($approvalStep["step"] == $step) {

                    if ($prevApprovedStatus == $model->{$this->approvalField}) {
                        $allowed = true;
                    } else {

                        if ($approvalStep["approvedStatus"] == $model->{$this->approvalField}) {
                            $allowed = true;
                        } else if ($approvalStep["declinedStatus"] == $model->{$this->approvalField}) {
                            $allowed = true;
                        } else {

                            $this->haveErrors = true;

                            if ($key === 0) {

                                $this->errors[] = "This link has been expired or don't have access here.";
                            } else {
                                $stepTitle = $this->getApprovalStepTitle($model, $prevStep["step"]);
                                $this->errors[] = "This record should have the approval from " . $stepTitle . " in the approval process.";
                            }
                        }
                    }
                    break;
                }

                $prevStep = $approvalStep;
                $prevApprovedStatus = $prevStep["approvedStatus"];
            }
        }

        return $allowed;
    }

    /**
     * @param $step
     * @return bool
     */
    private function _haveMoreStepsLeft($step): bool
    {
        $approvalSteps = $this->approvalSteps;

        if (is_array($approvalSteps)) {
            $count = count($approvalSteps);

            if ($count > 0) {
                $lastStep = $approvalSteps[$count - 1];

                if ($lastStep["step"] != $step) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param $model
     * @param $startStatus
     * @param array $response
     * @return array
     */
    public function startApprovalProcess($model, $startStatus = 0, $response = []): array
    {
        DB::beginTransaction();
        $model->{$this->approvalField} = $startStatus;
        $model->save();

        $update = $this->triggerApprovalProcess($model);

        if ($update["notify"]["status"] === "success") {
            DB::commit();

            $response = $this->onStartSuccess($model, $response);

            if (!isset($response["notify"]["status"])) {
                $response["notify"]["status"] = "success";
            }
        } else {
            DB::rollBack();

            $response = $this->onStartFailed($model, $response);

            if (!isset($response["notify"]["status"])) {
                $response["notify"]["status"] = "failed";
            } else {
                $response["notify"]["status"] = "warning";
            }
        }

        if (!isset($response["notify"]["notify"])) {
            $response["notify"]["notify"] = [];
        }

        if (isset($update["notify"]["notify"]) && is_array($update["notify"]["notify"]) && count($update["notify"]["notify"]) > 0) {

            foreach ($update["notify"]["notify"] as $message) {

                $response["notify"]["notify"][] = $message;
            }
        }

        $response["debugger"] = $update;

        return $response;
    }

    /**
     * @param $model
     * @param $response
     * @return array
     */
    protected function onStartSuccess($model, $response)
    {
        return $response;
    }

    /**
     * @param $model
     * @param $response
     * @return array
     */
    protected function onStartFailed($model, $response)
    {
        return $response;
    }

    /**
     * @param $layout
     * @param $model
     * @param $step
     * @param $pageTitle
     * @return mixed
     */
    public function renderApprovalRequests($layout, $model, $step, $pageTitle)
    {
        $this->setApprovalData($model);

        //setup approval status information
        $approvalStep = $this->getApprovalStep($model, $step);

        if (isset($approvalStep["approvalStatuses"])) {

            $this->approvalStatusList = $approvalStep["approvalStatuses"];
        }

        $modelName = $this->getClassName($model);
        $modelHash = $this->generateClassNameHash($modelName);

        $tableTitle = $pageTitle;

        $this->setPageTitle($pageTitle);
        $this->initDatatable(new SystemApproval());

        $this->setColumns("id", "title", "description", "status", "remarks", "approval_by", "created_at")

            ->setColumnDBField("approval_by", "approval_by")
            ->setColumnFKeyField("approval_by", "admin_id")
            ->setColumnRelation("approval_by", "approvalBy", "name")

            ->setColumnLabel("status", "Approval Status")
            ->setColumnLabel("created_at", "Created/Updated On")
            ->setColumnSearchability("created_at", false)

            ->setColumnDisplay("approval_by", array($this, 'displayRelationAs'), ["approval_by", "admin_id", "name"])
            ->setColumnDisplay("status", array($this, 'displayStatusActionAs'),
                [$this->approvalStatusList, "", "", true, "approval_url", ""]);

        $this->setTableTitle($tableTitle)
            ->disableViewData("list", "trash", "restore", "add", "edit", "delete")
            ->enableViewData("view", "export");

        $this->setUrlColumn("view", "approval_url");

        $query = $this->model::query();

        $query->where("model_hash", $modelHash)->where("approval_step", $step);
        $query->with(["approvalBy"]);

        return $this->render($layout)->index($query);
    }
}
