<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\LecturerPaymentMethod;
use Modules\Academic\Repositories\LecturerPaymentMethodRepository;
use Illuminate\Http\Request;

class LecturerPaymentMethodController extends Controller
{
    private LecturerPaymentMethodRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new LecturerPaymentMethodRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Lecturer Payment Methods");

        $this->repository->initDatatable(new LecturerPaymentMethod());

        //$this->repository->setColumns("id", "payment_method", "faculty", "department", "course", "academic_qualification", "hourly_rate", "special_rate", "created_at")
        //$this->repository->setColumns("id", "payment_method", "course_category", "course", "academic_qualification", "hourly_rate", "special_rate", "faculty_status", "approval_status", "created_at")
        $this->repository->setColumns("id", "payment_method", "course", "academic_qualification", "hourly_rate", "pm_status", "approval_status", "created_at")
            ->setColumnLabel("academic_qualification", "Qualification")
            ->setColumnLabel("hourly_rate", "Hourly Rate (LKR)")
            ->setColumnLabel("pm_status", "Status")
            ->setColumnDisplay("pm_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses, "", "", true])
            ->setColumnDisplay("approval_status", array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])
            //->setColumnDisplay("faculty", array($this->repository, 'displayRelationAs'), ["faculty", "faculty_id", "faculty_name", URL::to("/academic/faculty/view/")])
            //->setColumnDisplay("department", array($this->repository, 'displayRelationAs'), ["department", "dept_id", "dept_name", URL::to("/academic/department/view/")])
            ->setColumnDisplay("course_category", array($this->repository, 'displayRelationAs'), ["course_category", "course_category_id", "category_name", URL::to("/academic/course_category/view/")])
            ->setColumnDisplay("course", array($this->repository, 'displayRelationAs'), ["course", "course_id", "course_name", URL::to("/academic/course/view/")])
            ->setColumnDisplay("academic_qualification", array($this->repository, 'displayRelationAs'), ["academic_qualification", "qualification_id", "qualification", URL::to("/academic/academic_qualification/view/")])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            /*->setColumnFilterMethod("faculty", "select", URL::to("/academic/faculty/search_data"))
            ->setColumnFilterMethod("department", "select", URL::to("/academic/department/search_data"))
            ->setColumnFilterMethod("course_category", "select", URL::to("/academic/course_category/search_data"))*/
            ->setColumnFilterMethod("course", "select", URL::to("/academic/course/search_data"))
            ->setColumnFilterMethod("academic_qualification", "select", URL::to("/academic/academic_qualification/search_data"))
            ->setColumnFilterMethod($this->repository->statusField, "select", $this->repository->statuses)
            ->setColumnFilterMethod($this->repository->approvalField, "select", $this->repository->approvalStatuses)
            ->setColumnSearchability("created_at", false)
            /*->setColumnDBField("faculty", "faculty_id")
            ->setColumnFKeyField("faculty", "faculty_id")
            ->setColumnRelation("faculty", "faculty", "faculty_name")

            ->setColumnDBField("department", "dept_id")
            ->setColumnFKeyField("department", "dept_id")
            ->setColumnRelation("department", "department", "dept_name")*/

            /*->setColumnDBField("course_category", "course_category_id")
            ->setColumnFKeyField("course_category", "course_category_id")
            ->setColumnRelation("course_category", "courseCategory", "category_name")*/

            ->setColumnDBField("course", "course_id")
            ->setColumnFKeyField("course", "course_id")
            ->setColumnRelation("course", "course", "course_name")
            ->setColumnDBField("academic_qualification", "qualification_id")
            ->setColumnFKeyField("academic_qualification", "qualification_id")
            ->setColumnRelation("academic_qualification", "academicQualification", "qualification");

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Lecturer Payment Methods | Trashed")
                ->enableViewData("list", "view", "restore", "export")
                ->disableViewData("edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Lecturer Payment Methods")
                ->enableViewData("trashList", "view", "trash", "export")
                ->setRowActionBeforeButton(URL::to("/academic/lecturer_payment_method/duplicate/"), "Duplicate", "", "fa fa-copy");
        }

        $query = $query->with(["faculty", "department", "courseCategory", "course", "academicQualification"]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function trash()
    {
        $this->trash = true;
        return $this->index();
    }

    /**
     * Show the form for creating a new resource.
     * @return Factory|View
     */
    public function create()
    {
        $model = new LecturerPaymentMethod();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = URL::to("/" . request()->path());

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/lecturer_payment_method");

        $this->repository->setPageUrls($urls);

        return view('academic::lecturer_payment_method.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(): JsonResponse
    {
        $model = new LecturerPaymentMethod();

        $model = $this->repository->getValidatedData($model, [
            "payment_method" => "required",
            "faculty_id" => "required|exists:faculties,faculty_id",
            "dept_id" => "required|exists:departments,dept_id",
            "course_category_id" => "required|exists:course_categories,course_category_id",
            "course_id" => "required|exists:courses,course_id",
            "qualification" => "required|array",
            "hourly_rate" => "required|regex:/^\d*(\.\d{2})?$/",
        ]);

        if ($this->repository->isValidData) {
            $qualificationIds = $model->qualification;
            unset($model->qualification);

            if (is_array($qualificationIds) && count($qualificationIds) > 0) {

                $errorOccurred = false;
                DB::beginTransaction();
                foreach ($qualificationIds as $qualificationId) {

                    //check if this record already exists
                    $checkModel = LecturerPaymentMethod::query()
                        ->where(["course_id" => $model->course_id, "qualification_id" => $qualificationId])
                        ->first();

                    if ($checkModel) {
                        $savingModel = $checkModel;
                        $savingModel->hourly_rate = $model->hourly_rate;
                    } else {
                        $savingModel = $model->replicate();
                        $savingModel->qualification_id = $qualificationId;
                    }

                    $response = $this->repository->saveModel($savingModel);

                    if ($response["notify"]["status"] == "success") {

                        if (request()->post("send_for_approval") == "1") {

                            $savingModel->{$this->repository->approvalField} = 0;
                            $savingModel->save();

                            $update = $this->repository->triggerApprovalProcess($savingModel);

                            if ($update["notify"]["status"] !== "success") {

                                $response["notify"]["status"] = "failed";

                                if (is_array($update["notify"]) && count($update["notify"]) > 0) {

                                    foreach ($update["notify"] as $message) {

                                        $response["notify"]["notify"][] = $message;
                                    }
                                }

                                $errorOccurred = true;
                                break;
                            }
                        }
                    } else {

                        $errorOccurred = true;
                        break;
                    }
                }

                if ($errorOccurred) {

                    DB::rollBack();

                    $notify = array();
                    $notify["status"] = "failed";
                    $notify["notify"] = $response["notify"]["notify"] ?? [];
                    $notify["notify"][] = "Error occurred while saving details.";

                } else {
                    DB::commit();

                    $notify = array();
                    $notify["status"] = "success";
                    $notify["notify"][] = "Successfully saved the details.";
                }
            } else {

                $notify = array();
                $notify["status"] = "failed";
                $notify["notify"][] = "Details saving was failed.";
                $notify["notify"][] = "Please select at least one qualification.";
            }

            $response["notify"] = $notify;
        } else {
            $response = $model;
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Show the specified resource.
     * @param $id
     * @return Application|Factory|View|void
     */
    public function show($id)
    {
        $model = LecturerPaymentMethod::withTrashed()->with([
            "faculty",
            "department",
            "courseCategory",
            "course",
            "academicQualification",
            "createdUser",
            "updatedUser",
            "deletedUser"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $controllerUrl = URL::to("/academic/lecturer_payment_method/");

            $urls = [];
            $urls["addUrl"] = URL::to($controllerUrl . "/create");
            $urls["editUrl"] = URL::to($controllerUrl . "/edit/" . $id);
            $urls["listUrl"] = URL::to($controllerUrl);
            $urls["adminUrl"] = URL::to("/admin/admin/view/");
            $urls["recordHistoryUrl"] = $this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);
            $urls["approvalHistoryUrl"] = $this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);

            $this->repository->setPageUrls($urls);

            $statusInfo = [];
            $statusInfo["status"] = $this->repository->getStatusInfo($model);
            $statusInfo["approval_status"] = $this->repository->getStatusInfo($model, "approval_status", $this->repository->approvalStatuses);

            return view('academic::lecturer_payment_method.view', compact('record', 'statusInfo'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Show the form for editing the specified resource.
     * @param $id
     * @return Application|Factory|View|void
     */
    public function edit($id)
    {
        $model = LecturerPaymentMethod::with(["faculty", "department", "courseCategory", "course", "academicQualification"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = URL::to("/" . request()->path());

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/lecturer_payment_method/create");
            $urls["listUrl"] = URL::to("/academic/lecturer_payment_method");

            $this->repository->setPageUrls($urls);

            return view('academic::lecturer_payment_method.create', compact('formMode', 'formSubmitUrl', 'record'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Update the specified resource in storage.
     * @param $id
     * @return JsonResponse
     * @throws ValidationException
     */
    public function update($id): JsonResponse
    {
        $model = LecturerPaymentMethod::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "payment_method" => "required",
                "faculty_id" => "required|exists:faculties,faculty_id",
                "dept_id" => "required|exists:departments,dept_id",
                "course_category_id" => "required|exists:course_categories,course_category_id",
                "course_id" => "required|exists:courses,course_id",
                "qualification" => "required|array",
                "hourly_rate" => "required|regex:/^\d*(\.\d{2})?$/",
            ]);

            if ($this->repository->isValidData) {
                $qualificationIds = $model->qualification;
                unset($model->qualification);

                if (is_array($qualificationIds) && count($qualificationIds) > 0) {

                    $errorOccurred = false;
                    DB::beginTransaction();
                    foreach ($qualificationIds as $qualificationId) {

                        //check if this record already exists
                        $checkModel = LecturerPaymentMethod::query()
                            ->where(["course_id" => $model->course_id, "qualification_id" => $qualificationId])
                            ->first();

                        if ($checkModel) {
                            $savingModel = $checkModel;
                            $savingModel->hourly_rate = $model->hourly_rate;
                        } else {
                            $savingModel = $model->replicate();
                            $savingModel->qualification_id = $qualificationId;
                        }

                        $response = $this->repository->saveModel($savingModel);

                        if ($response["notify"]["status"] == "success") {

                            if (request()->post("send_for_approval") == "1") {

                                $savingModel->{$this->repository->approvalField} = 0;
                                $savingModel->save();

                                $update = $this->repository->triggerApprovalProcess($savingModel);

                                if ($update["notify"]["status"] !== "success") {

                                    $response["notify"]["status"] = "failed";

                                    if (is_array($update["notify"]) && count($update["notify"]) > 0) {

                                        foreach ($update["notify"] as $message) {

                                            $response["notify"]["notify"][] = $message;
                                        }
                                    }

                                    $errorOccurred = true;
                                    break;
                                }
                            }
                        } else {

                            $errorOccurred = true;
                            break;
                        }
                    }

                    if ($errorOccurred) {

                        DB::rollBack();

                        $notify = array();
                        $notify["status"] = "failed";
                        $notify["notify"] = $response["notify"]["notify"] ?? [];
                        $notify["notify"][] = "Error occurred while saving details.";

                    } else {
                        DB::commit();

                        $notify = array();
                        $notify["status"] = "success";
                        $notify["notify"][] = "Successfully saved the details.";
                    }
                } else {
                    $notify = array();
                    $notify["status"] = "failed";
                    $notify["notify"][] = "Details saving was failed.";
                    $notify["notify"][] = "Please select at least one qualification.";
                }

                $response["notify"] = $notify;
            } else {
                $response = $model;
            }
        } else {
            $notify = array();
            $notify["status"] = "failed";
            $notify["notify"][] = "Details saving was failed. Requested record does not exist.";

            $response["notify"] = $notify;
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Show the form for editing the specified resource.
     * @param $id
     * @return Application|Factory|View|void
     */
    public function duplicate($id)
    {
        $model = LecturerPaymentMethod::with(["faculty", "department", "courseCategory", "course", "academicQualification"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $record["approval_status"] = "";

            $formMode = "add";
            $isDuplicate = true;
            $formSubmitUrl = URL::to("/" . request()->path());

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/lecturer_payment_method/create");
            $urls["listUrl"] = URL::to("/academic/lecturer_payment_method");

            $this->repository->setPageUrls($urls);

            return view('academic::lecturer_payment_method.create', compact('formMode', 'formSubmitUrl', 'record', 'isDuplicate'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Update the specified resource in storage.
     * @param $id
     * @return JsonResponse
     * @throws ValidationException
     */
    public function duplicateSubmit($id): JsonResponse
    {
        $oldModel = LecturerPaymentMethod::with(["faculty", "department", "courseCategory", "course", "academicQualification"])->find($id);

        if ($oldModel) {
            $model = new LecturerPaymentMethod();

            $model = $this->repository->getValidatedData($model, [
                "payment_method" => "required",
                "faculty_id" => "required|exists:faculties,faculty_id",
                "dept_id" => "required|exists:departments,dept_id",
                "course_category_id" => "required|exists:course_categories,course_category_id",
                "course_id" => "required|exists:courses,course_id",
                "qualification_id" => "required",
                "hourly_rate" => "required|regex:/^\d*(\.\d{2})?$/",
            ]);

            if ($this->repository->isValidData) {

                $model->{$this->repository->statusField} = 0;
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] === "success") {

                    if (request()->post("disable_parent") === "Y") {

                        //disable duplicated parent record
                        $oldModel->{$this->repository->statusField} = 0;
                        $oldModel->save();
                    }

                    if (request()->post("migrate") === "Y") {

                        $this->repository->migratePendingPayments($oldModel, $model);
                    }

                    if (request()->post("send_for_approval") == "1") {

                        $response = $this->repository->startApprovalProcess($model, 0, $response);
                    }
                }
            } else {
                $response = $model;
            }
        } else {
            $notify = array();
            $notify["status"] = "failed";
            $notify["notify"][] = "Details saving was failed. Requested record does not exist.";

            $response["notify"] = $notify;
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Move the record to trash
     * @param $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = LecturerPaymentMethod::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = LecturerPaymentMethod::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Search records
     * @param Request $request
     * @return JsonResponse|void
     */
    public function searchData(Request $request)
    {
        if ($request->expectsJson()) {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $courseId = $request->post("course_id");
            $qualificationId = $request->post("qualification_id");
            $limit = $request->post("limit");

            $query = LecturerPaymentMethod::query()
                ->select("lecturer_payment_method_id", "payment_method", "hourly_rate", "qualification_id")
                ->where("pm_status", "=", "1")
                ->orderBy("payment_method");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($courseId != "") {
                $query = $query->where("course_id", $courseId);
            }

            if ($qualificationId != "") {
                $query = $query->where("qualification_id", $qualificationId);
            }

            if ($searchText != "") {
                $query = $query->where("payment_method", "LIKE", "%" . $searchText . "%");
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("lecturer_payment_method_id", $idNot);
            }

            $data = $query->get();

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }

    /**
     * Update status of the specified resource in storage.
     * @param $id
     * @return JsonResponse
     */
    public function changeStatus($id): JsonResponse
    {
        $model = LecturerPaymentMethod::query()->find($id);
        return $this->repository->updateStatus($model, $this->repository->statusField, "", "remarks");
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new LecturerPaymentMethod();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }

    /**
     * @param $id
     * @return Application|Factory|JsonResponse|RedirectResponse|View|null
     */
    public function verification($id)
    {
        $model = LecturerPaymentMethod::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $id
     * @return JsonResponse|RedirectResponse|null
     */
    public function verificationSubmit($id)
    {
        $model = LecturerPaymentMethod::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $id
     * @return Application|Factory|JsonResponse|RedirectResponse|View|null
     */
    public function preApprovalSAR($id)
    {
        $model = LecturerPaymentMethod::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "pre_approval_sar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $id
     * @return JsonResponse|RedirectResponse|null
     */
    public function preApprovalSARSubmit($id)
    {
        $model = LecturerPaymentMethod::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "pre_approval_sar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $id
     * @return Application|Factory|JsonResponse|RedirectResponse|View|null
     */
    public function preApprovalRegistrar($id)
    {
        $model = LecturerPaymentMethod::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "pre_approval_registrar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $id
     * @return JsonResponse|RedirectResponse|null
     */
    public function preApprovalRegistrarSubmit($id)
    {
        $model = LecturerPaymentMethod::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "pre_approval_registrar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $id
     * @return Application|Factory|JsonResponse|RedirectResponse|View|null
     */
    public function preApprovalVC($id)
    {
        $model = LecturerPaymentMethod::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "pre_approval_vc");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $id
     * @return JsonResponse|RedirectResponse|null
     */
    public function preApprovalVCSubmit($id)
    {
        $model = LecturerPaymentMethod::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "pre_approval_vc");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $id
     * @return Application|Factory|JsonResponse|RedirectResponse|View|null
     */
    public function approval($id)
    {
        $model = LecturerPaymentMethod::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $id
     * @return JsonResponse|RedirectResponse|null
     */
    public function approvalSubmit($id)
    {
        $model = LecturerPaymentMethod::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function approvalHistory($modelHash, $id)
    {
        $model = new LecturerPaymentMethod();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }
}
