<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\LecturerPayment;
use Modules\Academic\Repositories\LecturerPaymentPlanRepository;
use Modules\Academic\Repositories\LecturerPaymentRepository;

class LecturerPaymentController extends Controller
{
    private LecturerPaymentRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new LecturerPaymentRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Lecturer Payments");

        $this->repository->initDatatable(new LecturerPayment());

        $ppRepo = new LecturerPaymentPlanRepository();

        $this->repository->setColumns("id", "payment_type", "payment_method", "lecturer", "payment_date", $this->repository->statusField, "approval_status", "paid_status", "created_at")
            ->setColumnLabel("lecturer", "Lecturer Information")
            ->setColumnLabel("payment_date", "Payment for Month/Date/Time")

            ->setColumnDBField("lecturer", "lecturer_id")
            ->setColumnFKeyField("lecturer", "id")
            ->setColumnRelation("lecturer", "lecturer", "name_with_init")

            ->setColumnDBField("payment_method", "lecturer_payment_method_id")
            ->setColumnFKeyField("payment_method", "lecturer_payment_method_id")
            ->setColumnRelation("payment_method", "paymentMethod", "payment_method")

            ->setColumnDisplay("payment_method", array($this->repository, 'displayRelationAs'), ["payment_method", "id", "name"])
            ->setColumnDisplay("lecturer", array($this->repository, 'displayPaymentInfoAs'))
            ->setColumnDisplay("payment_date", array($this->repository, 'displayTimeHoursAs'))

            ->setColumnDisplay("payment_type", array($this->repository, 'displayStatusAs'), [$ppRepo->paymentTypes])
            ->setColumnDisplay($this->repository->statusField, array($this->repository, 'displayStatusAs'), [$this->repository->statuses])
            ->setColumnDisplay("approval_status", array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])
            ->setColumnDisplay("paid_status", array($this->repository, 'displayStatusAs'), [$this->repository->paidStatusOptions])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false)
            ->setColumnSearchability("payment_info", false)

            ->setColumnFilterMethod("lecturer", "select", URL::to("/academic/lecturer/search_data"))
            ->setColumnFilterMethod("payment_method", "select", URL::to("/academic/lecturer_payment_method/search_data"))
            ->setColumnFilterMethod("payment_date", "date_between")
            ->setColumnFilterMethod("payment_type", "select", $ppRepo->paymentTypes)
            ->setColumnFilterMethod($this->repository->statusField, "select", $this->repository->statuses)
            ->setColumnFilterMethod("approval_status", "select", $this->repository->approvalStatuses)
            ->setColumnFilterMethod("paid_status", "select", $this->repository->paidStatusOptions);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Lecturer Payments | Trashed")
                ->enableViewData("list", "view", "restore", "export")
                ->disableViewData("add", "edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Lecturer Payments")
                ->enableViewData("edit", "view", "trashList", "trash", "export")
                ->disableViewData("add");

            $this->repository->setUrl("edit", URL::to("/academic/lecturer_payment/send_for_approval/") . "/");
            $this->repository->setUrlLabel("edit", "Trigger Approval");
        }

        $this->repository->setCustomFilters("batch", "academic_year", "semester")
            ->setColumnDBField("batch", "batch_id", true)
            ->setColumnFKeyField("batch", "batch_id", true)
            ->setColumnRelation("batch", "batch", "batch_name", true)
            ->setColumnFilterMethod("batch", "select", URL::to("/academic/batch/search_data"), true)

            ->setColumnDBField("academic_year", "academic_year_id", true)
            ->setColumnFKeyField("academic_year", "academic_year_id", true)
            ->setColumnRelation("academic_year", "academicYear", "year_name", true)
            ->setColumnFilterMethod("academic_year", "select", URL::to("/academic/academic_year/search_data"), true)

            ->setColumnDBField("semester", "semester_id", true)
            ->setColumnFKeyField("semester", "semester_id", true)
            ->setColumnRelation("semester", "semester", "semester_name", true)
            ->setColumnFilterMethod("semester", "select", URL::to("/academic/academic_semester/search_data"), true);

        $query = $query->with(["paymentMethod", "lecturer", "course", "module", "batch", "academicYear", "semester", "paymentMethod"]);

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
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Factory|View
     */
    public function sendForApproval($id)
    {
        $model = LecturerPayment::query()->find($id);

        if($model)
        {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["listUrl"]=URL::to("/academic/lecturer_payment");

            $this->repository->setPageUrls($urls);

            $record = $this->repository->getRecordPrepared($record);

            return view('academic::lecturer_payment.send_for_approval', compact('formMode', 'formSubmitUrl', 'record'));
        }
        else
        {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Update the specified resource in storage.
     * @param int $id
     * @return JsonResponse
     */
    public function sendForApprovalUpdate($id): JsonResponse
    {
        $model = LecturerPayment::query()->find($id);

        if ($model) {
            if (request()->post("send_for_approval") == "1") {

                if ($model->approval_status === "" || $model->approval_status === null) {

                    $model->load(["lecturer", "course", "batch", "module", "paymentMethod"]);
                    $response = $this->repository->startApprovalProcess($model);

                    if ($response["notify"]["status"] === "success") {

                        $response["notify"]["notify"][] = "Successfully started the approval process.";
                    }
                } else {

                    $notify = array();
                    $notify["status"] = "failed";
                    $notify["notify"][] = "Approval process has been already started.";

                    $response["notify"] = $notify;
                }
            } else {
                $notify = array();
                $notify["status"] = "success";
                $notify["notify"][] = "Nothing triggered related to the approval process";

                $response["notify"] = $notify;
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
     * Show the specified resource.
     * @param int $id
     * @return Factory|View
     */
    public function show($id)
    {
        $model = LecturerPayment::withTrashed()->with([
            "lecturer",
            "course",
            "module",
            "paymentMethod",
            "paymentPlan",
            "createdUser",
            "updatedUser",
            "deletedUser"])->find($id);

        if($model)
        {
            $record = $model->toArray();

            $record = $this->repository->getRecordPrepared($record);

            $controllerUrl = URL::to("/academic/lecturer_payment/");

            $urls = [];
            $urls["addUrl"]=URL::to($controllerUrl . "/create");
            $urls["editUrl"]=URL::to($controllerUrl . "/edit/" . $id);
            $urls["listUrl"]=URL::to($controllerUrl);
            $urls["adminUrl"]=URL::to("/admin/admin/view/");
            $urls["lecturerUrl"]=URL::to("/academic/lecturer/view/");
            $urls["courseUrl"]=URL::to("/academic/course/view/");
            $urls["paymentPlanUrl"]=URL::to("/academic/lecturer_payment_plan/view/" . $model->payment_plan_id);
            $urls["rosterViewUrl"] = URL::to("/academic/lecturer_roster/" . $model->lecturer_id);
            $urls["recordHistoryUrl"]=$this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);
            $urls["approvalHistoryUrl"]=$this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);

            $this->repository->setPageUrls($urls);

            $lPPRepo = new LecturerPaymentPlanRepository();

            $statusInfo = [];
            $statusInfo["paid_status"] = $this->repository->getStatusInfo($model, "paid_status", $this->repository->paidStatusOptions);
            $statusInfo["payment_type"] = $lPPRepo->getStatusInfo($model->paymentPlan, "payment_type", $lPPRepo->paymentTypes);
            $statusInfo[$this->repository->approvalField] = $this->repository->getStatusInfo($model, $this->repository->approvalField, $this->repository->approvalStatuses);

            return view('academic::lecturer_payment.view', compact('record', 'statusInfo'));
        }
        else
        {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Move the record to trash
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = LecturerPayment::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = LecturerPayment::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $options = [];
        $options["suffix"] = "Lecturer Payment";

        $model = new LecturerPayment();
        return $this->repository->recordHistory($model, $modelHash, $id, $options);
    }

    public function verification($id)
    {
        $model = LecturerPayment::with(["lecturer", "course", "batch", "module", "paymentMethod"])->find($id);

        if ($model) {

            $totals = [];
            $totals["approval"] = $this->repository->getFullApprovalAmount($model);
            $totals["partial"] = $this->repository->getPartialApprovalAmount($model);

            $model->totals = $totals;

            return $this->repository->renderApprovalView($model, "verification", "academic::lecturer_payment.approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @throws ValidationException
     */
    public function verificationSubmit($id)
    {
        $model = LecturerPayment::with(["lecturer", "course", "batch", "module", "paymentMethod"])->find($id);

        if ($model) {

            $lecturerPaymentMethodId = request()->post("lecturer_payment_method_id");
            $hourlyRate = request()->post("hourly_rate");

            if ($lecturerPaymentMethodId !== null && $hourlyRate !== null) {

                $model->lecturer_payment_method_id = $lecturerPaymentMethodId;
                $model->hourly_rate = $hourlyRate;
            }

            if ($model->lecturer_payment_method_id !== null && $model->lecturer_payment_method_id !== "") {

                $status = request()->post("status");
                $remarks = request()->post("remarks");

                if ($status !== "1" && $remarks === null) {

                    $response["status"] = "failed";
                    $response["notify"][] = "Remarks Required.";

                    return $this->repository->handleResponse($response);

                } else {

                    return $this->repository->processApprovalSubmission($model, "verification");
                }
            } else {

                $response["status"] = "failed";
                $response["notify"][] = "Payment Method Method Required.";

                return $this->repository->handleResponse($response);
            }
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function verificationRequests()
    {
        $model = new LecturerPayment();
        return $this->repository->renderApprovalRequests("academic::layouts.master", $model, "verification", "Lecturer Payment | Verification Approval Requests");
    }

    /*public function preApproval($id)
    {
        $model = LecturerPayment::with(["lecturer", "course", "batch", "module", "paymentMethod"])->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "pre_approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalSubmit($id)
    {
        $model = LecturerPayment::with(["lecturer", "course", "batch", "module", "paymentMethod"])->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "pre_approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function approval($id)
    {
        $model = LecturerPayment::with(["lecturer", "course", "batch", "module", "paymentMethod"])->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function approvalSubmit($id)
    {
        $model = LecturerPayment::with(["lecturer", "course", "batch", "module", "paymentMethod"])->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }*/

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function approvalHistory($modelHash, $id)
    {
        $model = new LecturerPayment();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }
}
