<?php

namespace Modules\Academic\Http\Controllers;

use App\Helpers\Helper;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\Lecturer;
use Modules\Academic\Entities\LecturerPaymentPlan;
use Modules\Academic\Repositories\LecturerPaymentPlanDocumentRepository;
use Modules\Academic\Repositories\LecturerPaymentPlanExamWorkTypeRepository;
use Modules\Academic\Repositories\LecturerPaymentPlanRepository;

class LecturerPaymentPlanController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new LecturerPaymentPlanRepository();
    }

    /**
     * Display a listing of the resource.
     * @param $lecturerId
     * @return Response
     */
    public function index($lecturerId)
    {
        $lecturer = Lecturer::query()->find($lecturerId);

        if ($lecturer) {
            $pageTitle = $lecturer["name"] . " | Lecturer Payment Plans";
            $tableTitle = $lecturer["name"] . " | Lecturer Payment Plans";

            $weekDays = Helper::getWeekDays();

            $this->repository->setPageTitle($pageTitle);

            $this->repository->initDatatable(new LecturerPaymentPlan());

            $this->repository->setColumns("id", "course", "payment_type", "applicable_from", "applicable_till", "applicable_days", $this->repository->statusField, $this->repository->approvalField, "created_at")
                ->setColumnLabel($this->repository->statusField, "Status")
                ->setColumnDBField("course", "course_id")
                ->setColumnFKeyField("course", "course_id")
                ->setColumnRelation("course", "course", "course_name")
                ->setColumnDisplay("course", array($this->repository, 'displayRelationAs'), ["course", "course_id", "course_name"])
                ->setColumnDisplay("payment_type", array($this->repository, 'displayStatusAs'), [$this->repository->paymentTypes])
                ->setColumnDisplay("applicable_days", array($this->repository, 'displayArrayListAs'), [$weekDays])
                ->setColumnDisplay($this->repository->statusField, array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses, "/academic/lecturer_payment_plan/change_status/", "", true])
                ->setColumnDisplay($this->repository->approvalField, array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])
                ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
                ->setColumnFilterMethod("payment_type", "select", $this->repository->paymentTypes)
                ->setColumnFilterMethod("course", "select", URL::to("/academic/course/search_data"))
                ->setColumnFilterMethod($this->repository->statusField, "select", $this->repository->statuses)
                ->setColumnDisplay("documents", array($this->repository, 'displayListButtonAs'), ["Documents", URL::to("/academic/lecturer_payment_plan_document/")])
                ->setColumnSearchability("created_at", false);

            if ($this->trash) {
                $query = $this->repository->model::onlyTrashed();

                $this->repository->setTableTitle($tableTitle . " | Trashed")
                    ->enableViewData("list", "view", "restore", "export")
                    ->disableViewData("edit", "delete")
                    ->setUrl("list", $this->repository->getUrl("list") . "/" . $lecturerId)
                    ->setUrl("add", $this->repository->getUrl("add") . "/" . $lecturerId);
            } else {
                $query = $this->repository->model::query();

                $this->repository->setTableTitle($tableTitle)
                    ->enableViewData("view", "trashList", "trash", "export")
                    ->setUrl("trashList", $this->repository->getUrl("trashList") . "/" . $lecturerId)
                    ->setUrl("add", $this->repository->getUrl("add") . "/" . $lecturerId);
            }

            $query->where("lecturer_id", "=", $lecturerId);

            $query = $query->with(["course", "examWorkTypes"]);

            return $this->repository->render("academic::layouts.master")->index($query);
        } else {
            abort(404);
        }
    }

    /**
     * Display a listing of the resource.
     * @param $lecturerId
     * @return Response
     */
    public function trash($lecturerId)
    {
        $this->trash = true;
        return $this->index($lecturerId);
    }

    /**
     * Show the form for creating a new resource.
     * @param int $lecturerId
     * @return Factory|View
     */
    public function create($lecturerId)
    {
        $lecturer = Lecturer::query()->find($lecturerId);

        if ($lecturer) {
            $this->repository->setPageTitle("Lecturer Payment Plans | Add New");

            $model = new LecturerPaymentPlan();
            $model->lecturer = $lecturer;

            $record = $model;

            $formMode = "add";
            $formSubmitUrl = request()->getPathInfo();

            $urls = [];
            $urls["listUrl"] = URL::to("/academic/lecturer_payment_plan/" . $lecturerId);

            $this->repository->setPageUrls($urls);

            $paymentTypes = $this->repository->paymentTypes;
            $weekDays = Helper::getWeekDays();

            return view('academic::lecturer_payment_plan.create', compact('formMode', 'formSubmitUrl', 'record', 'paymentTypes', 'weekDays'));
        } else {
            abort(404);
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Lecturer $lecturerId
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store($lecturerId)
    {
        $lecturer = Lecturer::query()->find($lecturerId);

        if ($lecturer) {
            $model = new LecturerPaymentPlan();

            $model = $this->repository->getValidatedData($model, [
                "course_id" => [Rule::requiredIf(function () {
                    return request()->post("payment_type") == "2";
                })],
                "special_rate" => [Rule::requiredIf(function () {
                    return request()->post("payment_type") == "2";
                })],
                "fixed_amount" => [Rule::requiredIf(function () {
                    return request()->post("payment_type") == "3";
                })],
                "payment_type" => "required",
                "applicable_from" => "required|date",
                "applicable_till" => "required|date",
                "applicable_days" => [Rule::requiredIf(function () {
                    return request()->post("payment_type") == "3";
                })],
                "remarks" => "",
            ], [], ["course_id" => "Course"]);

            if ($this->repository->isValidData) {
                $model->lecturer_id = $lecturerId;
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] == "success") {

                    $docRepo = new LecturerPaymentPlanDocumentRepository();
                    $docRepo->update($model);

                    $eWRepo = new LecturerPaymentPlanExamWorkTypeRepository();
                    $eWRepo->update($model);

                    if (request()->post("send_for_approval") == "1") {

                        $response = $this->repository->startApprovalProcess($model, 0, $response);
                    }
                }
            } else {
                $response = $model;
            }

            return $this->repository->handleResponse($response);
        } else {
            $response["notify"]["status"] = "failed";
            $response["notify"]["notify"][] = "Selected permission system does not exist.";
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
        $model = LecturerPaymentPlan::withTrashed()->with([
            "course",
            "examWorkTypes",
            "ppDocuments",
            "createdUser",
            "updatedUser",
            "deletedUser"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $controllerUrl = URL::to("/academic/lecturer_payment_plan/");

            $applicableDays = [];
            if (is_array($record["applicable_days"]) && count($record["applicable_days"]) > 0) {

                $weekDaysData = Helper::getWeekDaysByDay();
                foreach ($record["applicable_days"] as $day) {

                    if (isset($weekDaysData[$day])) {

                        $applicableDays[] = $weekDaysData[$day];
                    }
                }
            }

            $record["applicable_days"] = $applicableDays;

            $urls = [];
            $urls["addUrl"] = URL::to($controllerUrl . "/create");
            $urls["editUrl"] = URL::to($controllerUrl . "/edit/" . $id);
            $urls["listUrl"] = URL::to($controllerUrl);
            $urls["adminUrl"] = URL::to("/admin/admin/view/");
            $urls["courseUrl"] = URL::to("/academic/course/view/");
            $urls["docsUrl"] = URL::to("/academic/lecturer_payment_plan_document/" . $id);
            $urls["recordHistoryUrl"] = $this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);
            $urls["approvalHistoryUrl"] = $this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);
            $urls["downloadUrl"] = URL::to("/academic/lecturer_payment_plan_document/download/") . "/";

            $this->repository->setPageUrls($urls);

            $statusInfo = [];
            $statusInfo["status"] = $this->repository->getStatusInfo($model);
            $statusInfo["payment_type"] = $this->repository->getStatusInfo($model, "payment_type", $this->repository->paymentTypes);
            $statusInfo[$this->repository->approvalField] = $this->repository->getStatusInfo($model, $this->repository->approvalField, $this->repository->approvalStatuses);

            return view('academic::lecturer_payment_plan.view', compact('record', 'statusInfo'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Factory|View
     */
    public function edit($id)
    {
        $this->repository->setPageTitle("Lecturer Payment Plans | Edit");

        $model = LecturerPaymentPlan::with(["course", "examWorkTypes", "ppDocuments"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $formMode = "edit";
            $formSubmitUrl = request()->getPathInfo();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/lecturer_payment_plan/create/" . $record["lecturer_id"]);
            $urls["listUrl"] = URL::to("/academic/lecturer_payment_plan/" . $record["lecturer_id"]);
            $urls["downloadUrl"] = URL::to("/academic/lecturer_payment_plan_document/download/") . "/";

            $this->repository->setPageUrls($urls);

            $paymentTypes = $this->repository->paymentTypes;
            $weekDays = Helper::getWeekDays();

            return view('academic::lecturer_payment_plan.create', compact('formMode', 'formSubmitUrl', 'record', 'paymentTypes', 'weekDays'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Update the specified resource in storage.
     * @param int $id
     * @return JsonResponse
     * @throws ValidationException
     */
    public function update($id)
    {
        $model = LecturerPaymentPlan::query()->find($id);

        if ($model) {

            $model = $this->repository->getValidatedData($model, [
                "course_id" => [Rule::requiredIf(function () {
                    return request()->post("payment_type") == "2";
                })],
                "special_rate" => [Rule::requiredIf(function () {
                    return request()->post("payment_type") == "2";
                })],
                "fixed_amount" => [Rule::requiredIf(function () {
                    return request()->post("payment_type") == "3";
                })],
                "payment_type" => "required",
                "applicable_from" => "required|date",
                "applicable_till" => "required|date",
                "applicable_days" => [Rule::requiredIf(function () {
                    return request()->post("payment_type") == "3";
                })],
                "remarks" => "",
            ], [], ["course_id" => "Course"]);

            if ($this->repository->isValidData) {
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] == "success") {

                    $docRepo = new LecturerPaymentPlanDocumentRepository();
                    $docRepo->update($model);

                    $eWRepo = new LecturerPaymentPlanExamWorkTypeRepository();
                    $eWRepo->update($model);

                    $response["data"]["documents"] = $model->ppDocuments()->get()->toArray();

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
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = LecturerPaymentPlan::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = LecturerPaymentPlan::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new LecturerPaymentPlan();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return JsonResponse
     */
    public function changeStatus($id)
    {
        $model = LecturerPaymentPlan::query()->find($id);
        return $this->repository->updateStatus($model, $this->repository->statusField, "", "remarks");
    }

    public function verification($id)
    {
        $model = LecturerPaymentPlan::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function verificationSubmit($id)
    {
        $model = LecturerPaymentPlan::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalSAR($id)
    {
        $model = LecturerPaymentPlan::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "pre_approval_sar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalSARSubmit($id)
    {
        $model = LecturerPaymentPlan::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "pre_approval_sar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalRegistrar($id)
    {
        $model = LecturerPaymentPlan::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "pre_approval_registrar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalRegistrarSubmit($id)
    {
        $model = LecturerPaymentPlan::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "pre_approval_registrar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalVC($id)
    {
        $model = LecturerPaymentPlan::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "pre_approval_vc");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalVCSubmit($id)
    {
        $model = LecturerPaymentPlan::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "pre_approval_vc");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function approval($id)
    {
        $model = LecturerPaymentPlan::query()->find($id);

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
        $model = LecturerPaymentPlan::query()->find($id);

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
        $model = new LecturerPaymentPlan();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }
}
