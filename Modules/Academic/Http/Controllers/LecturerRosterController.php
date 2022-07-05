<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\Lecturer;
use Modules\Academic\Entities\LecturerRoster;
use Modules\Academic\Repositories\LecturerRosterRepository;
use Modules\Academic\Repositories\LecturerRosterShiftRepository;

class LecturerRosterController extends Controller
{
    private LecturerRosterRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new LecturerRosterRepository();
    }

    /**
     * Display a listing of the resource.
     * @param int $lecturerId
     * @return Factory|View
     */
    public function index($lecturerId)
    {
        $lecturer = Lecturer::query()->find($lecturerId);

        $lecTitle = "";
        if ($lecturer) {
            $lecTitle = $lecturer["name_with_init"];
        } else {
            abort(404, "Lecturer not available");
        }
        $pageTitle = $lecTitle . " | Lecturer Rosters";

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new LecturerRoster());

        $this->repository->setColumns("id", "roster_name", "roster_from", "roster_till", $this->repository->statusField, $this->repository->approvalField, "created_at")
            ->setColumnLabel($this->repository->statusField, "Status")
            ->setColumnDisplay($this->repository->statusField, array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses, "", "", true])
            ->setColumnDisplay($this->repository->approvalField, array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnFilterMethod("roster_name")
            ->setColumnFilterMethod($this->repository->statusField, "select", $this->repository->statuses)
            ->setColumnFilterMethod($this->repository->approvalField, "select", $this->repository->approvalStatuses)
            ->setColumnSearchability("created_at", false);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = $lecTitle . " | Lecturer Rosters | Trashed";
            $this->repository->setUrl("list", "/academic/lecturer_roster/" . $lecturerId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "view", "restore", "export")
                ->disableViewData("edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = $lecTitle . " | Lecturer Rosters";
            $this->repository->setCustomControllerUrl("/academic/lecturer_roster", ["list"], false)
                ->setUrl("trashList", "/academic/lecturer_roster/trash/" . $lecturerId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("view", "trashList", "trash", "export");
        }

        $this->repository->setUrl("add", "/academic/lecturer_roster/create/" . $lecturerId);

        $query = $query->where(["lecturer_id" => $lecturerId]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param int $lecturerId
     * @return Factory|View
     */
    public function trash($lecturerId)
    {
        $this->trash = true;
        return $this->index($lecturerId);
    }

    /**
     * Show the form for creating a new resource.
     * @param mixed $lecturerId
     * @return Factory|View
     */
    public function create($lecturerId)
    {
        $lecturer = Lecturer::query()->find($lecturerId);

        if (!$lecturer) {
            abort(404, "Lecturer not available");
        }

        $model = new LecturerRoster();
        $model->lecturer = $lecturer;

        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/lecturer_roster/" . $lecturerId);

        $this->repository->setPageUrls($urls);

        return view('academic::lecturer_roster.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @param $lecturerId
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store($lecturerId): JsonResponse
    {
        $lecturer = Lecturer::query()->find($lecturerId);

        if (!$lecturer) {
            abort(404, "Lecturer not available");
        }

        $model = new LecturerRoster();

        $model = $this->repository->getValidatedData($model, [
            "roster_name" => "required",
            "roster_from" => "required|date",
            "roster_till" => "required|date",
        ]);
        $model->lecturer_id = $lecturerId;

        $lRRepo = new LecturerRosterShiftRepository();
        if ($lRRepo->isValidRoster($model)) {

            if ($this->repository->isValidData) {

                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] == "success") {

                    $lRRepo->update($model);

                    if (request()->post("send_for_approval") == "1") {

                        $response = $this->repository->startApprovalProcess($model, 0, $response);
                    }
                }
            } else {
                $response = $model;
            }
        } else {

            $response["notify"]["status"] = "failed";
            $response["notify"]["notify"][] = "Some of the dates are already allocated in another roster.";
            $response["notify"]["notify"][] = "Please make sure you are adding the correct dates.";
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
        $model = LecturerRoster::withTrashed()->with([
            "lecturer",
            "rosterShifts",
            "createdUser",
            "updatedUser",
            "deletedUser"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $controllerUrl = URL::to("/academic/lecturer_roster/");

            $urls = [];
            $urls["addUrl"] = URL::to($controllerUrl . "/create");
            $urls["editUrl"] = URL::to($controllerUrl . "/edit/" . $id);
            $urls["editAttendanceUrl"] = URL::to($controllerUrl . "/edit_attendance/" . $id);
            $urls["listUrl"] = URL::to($controllerUrl) . $model->lecturer_id;
            $urls["lecturerUrl"] = URL::to("/academic/lecturer/view/" . $model->lecturer_id);
            $urls["recordHistoryUrl"] = $this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);
            $urls["approvalHistoryUrl"] = $this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);

            $this->repository->setPageUrls($urls);

            $statusInfo = [];
            $statusInfo["status"] = $this->repository->getStatusInfo($model);
            $statusInfo[$this->repository->approvalField] = $this->repository->getStatusInfo($model, $this->repository->approvalField, $this->repository->approvalStatuses);

            return view('academic::lecturer_roster.view', compact('record', 'statusInfo'));
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
        $model = LecturerRoster::with(["lecturer", "rosterShifts"])->find($id);

        if ($model) {
            $lecturerId = $model->lecturer_id;

            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/lecturer_roster/create/" . $lecturerId);
            $urls["listUrl"] = URL::to("/academic/lecturer_roster/" . $lecturerId);
            $urls["viewUrl"] = URL::to("/academic/lecturer_roster/view/" . $id);
            $urls["editAttendanceUrl"] = URL::to("/academic/lecturer_roster/edit_attendance/" . $id);

            $this->repository->setPageUrls($urls);

            return view('academic::lecturer_roster.create', compact('formMode', 'formSubmitUrl', 'record'));
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
    public function update($id): JsonResponse
    {
        $model = LecturerRoster::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "roster_name" => "required",
                "roster_from" => "required|date",
                "roster_till" => "required|date",
            ]);

            $lRRepo = new LecturerRosterShiftRepository();
            if ($lRRepo->isValidRoster($model)) {

                if ($this->repository->isValidData) {
                    $response = $this->repository->saveModel($model);

                    if ($response["notify"]["status"] === "success") {

                        $lRRepo->update($model);

                        if (request()->post("send_for_approval") == "1") {

                            $response = $this->repository->startApprovalProcess($model, 0, $response);
                        }

                        $response["data"]["roster_shifts"] = $model->rosterShifts()->get()->toArray();
                    }
                } else {
                    $response = $model;
                }
            } else {

                $response["notify"]["status"] = "failed";
                $response["notify"]["notify"][] = "Some of the dates are already allocated in another roster.";
                $response["notify"]["notify"][] = "Please make sure you are adding the correct dates.";
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
     * @param int $id
     * @return Factory|View
     */
    public function editAttendance($id)
    {
        $model = LecturerRoster::with(["lecturer", "rosterShifts"])->find($id);

        if ($model) {

            $approvalField = $this->repository->approvalField;
            if($model->{$approvalField} === 1) {

                $lecturerId = $model->lecturer_id;

                $record = $model->toArray();
                $formMode = "edit";
                $formSubmitUrl = "/" . request()->path();

                $urls = [];
                $urls["addUrl"] = URL::to("/academic/lecturer_roster/create/" . $lecturerId);
                $urls["listUrl"] = URL::to("/academic/lecturer_roster/" . $lecturerId);
                $urls["editUrl"] = URL::to("/academic/lecturer_roster/edit/" . $id);
                $urls["viewUrl"] = URL::to("/academic/lecturer_roster/view/" . $id);

                $this->repository->setPageUrls($urls);

                return view('academic::lecturer_roster.create_attendance', compact('formMode', 'formSubmitUrl', 'record'));
            } else {

                abort(403, "Attendance update is not allowed until get approved the roster.");
            }
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Update the specified resource in storage.
     * @param int $id
     * @return JsonResponse
     */
    public function updateAttendance($id): JsonResponse
    {
        $model = LecturerRoster::query()->find($id);

        if ($model) {

            $approvalField = $this->repository->approvalField;
            if($model->{$approvalField} === 1) {

                $lRRepo = new LecturerRosterShiftRepository();
                $lRRepo->updateAttendance($model);

                $notify = array();
                $notify["status"] = "success";
                $notify["notify"][] = "Successfully saved the details.";

                $response["notify"] = $notify;
                $response["data"]["roster_shifts"] = $model->rosterShifts()->get()->toArray();
            } else {

                $notify = array();
                $notify["status"] = "failed";
                $notify["notify"][] = "Attendance update is not allowed until get approved the roster.";

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
     * Move the record to trash
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = LecturerRoster::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = LecturerRoster::withTrashed()->find($id);

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
        $options["title"] = "Lecturer Roster";

        $model = new LecturerRoster();
        return $this->repository->recordHistory($model, $modelHash, $id, $options);
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return JsonResponse
     */
    public function changeStatus($id): JsonResponse
    {
        $model = LecturerRoster::query()->find($id);
        return $this->repository->updateStatus($model, $this->repository->statusField, "", "remarks");
    }

    public function verification($id)
    {
        $model = LecturerRoster::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "verification");
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
        $model = LecturerRoster::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function approval($id)
    {
        $model = LecturerRoster::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @throws ValidationException
     */
    public function approvalSubmit($id)
    {
        $model = LecturerRoster::query()->find($id);

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
        $model = new LecturerRoster();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }
}
