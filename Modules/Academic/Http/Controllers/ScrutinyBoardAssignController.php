<?php

namespace Modules\Academic\Http\Controllers;

use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Academic\Entities\ScrutinyBoardAssign;
use Modules\Academic\Exports\ExcelExport;
use Modules\Academic\Repositories\ScrutinyBoardRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScrutinyBoardAssignController extends Controller
{
    private ScrutinyBoardRepository $repository;
    private bool $trash = false;
    private bool $scrutinyBoards = false;

    public function __construct()
    {
        $this->repository = new ScrutinyBoardRepository();
        $this->repository->withPeople = true;
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $pageTitle = "Examiner Appointing Structures";

        if ($this->scrutinyBoards) {

            $pageTitle = "Scrutiny Boards";
        }

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new ScrutinyBoardAssign());

        $this->repository->setColumns("id", "board_name", "base", "course", "syllabus", "batch",
            "academic_year", "semester", "academic_calendar", "status", "sb_status", "approval_status", "sb_approval_status")
            ->setColumnLabel("board_name", "Name")
            ->setColumnLabel("base", "Based Structure")
            ->setColumnLabel("sb_status", "Status")
            ->setColumnLabel("sb_approval_status", "Approval Status")

            ->setColumnDBField("academic_calendar", "academic_calendar_id")
            ->setColumnFKeyField("academic_calendar", "academic_calendar_id")
            ->setColumnRelation("academic_calendar", "academicCalendar", "name")

            ->setColumnDBField("syllabus", "syllabus_id")
            ->setColumnFKeyField("syllabus", "syllabus_id")
            ->setColumnRelation("syllabus", "syllabus", "syllabus_name")

            ->setColumnDBField("batch", "academic_calendar_id")
            ->setColumnFKeyField("batch", "academic_calendar_id")
            ->setColumnRelation("batch", "academicCalendar", "name")
            ->setColumnCoRelation("batch", "batch", "batch_name", "batch_id")

            ->setColumnDBField("academic_year", "academic_calendar_id")
            ->setColumnFKeyField("academic_year", "academic_calendar_id")
            ->setColumnRelation("academic_year", "academicCalendar", "name")
            ->setColumnCoRelation("academic_year", "academicYear", "year_name", "academic_year_id")

            ->setColumnDBField("semester", "academic_calendar_id")
            ->setColumnFKeyField("semester", "academic_calendar_id")
            ->setColumnRelation("semester", "academicCalendar", "name")
            ->setColumnCoRelation("semester", "semester", "semester_name", "semester_id")

            ->setColumnDBField("course", "course_id")
            ->setColumnFKeyField("course", "course_id")
            ->setColumnRelation("course", "course", "course_name")

            ->setColumnDBField("base", "id")
            ->setColumnFKeyField("base", "based_scrutiny_board_id")
            ->setColumnRelation("base", "base", "board_name")

            ->setColumnDisplay("academic_calendar", array($this->repository, 'displayRelationAs'),
                ["academic_calendar", "academic_calendar_id", "name", URL::to("/academic/academic_calendar/view/")])
            ->setColumnDisplay("syllabus", array($this->repository, 'displayRelationAs'),
                ["syllabus", "based_scrutiny_board_id", "syllabus_name", URL::to("/academic/course_syllabus/view/")])
            ->setColumnDisplay("course", array($this->repository, 'displayRelationAs'),
                ["course", "id", "name", URL::to("/academic/course/view/")])
            ->setColumnDisplay("base", array($this->repository, 'displayRelationAs'),
                ["base", "id", "name", URL::to("/academic/scrutiny_board_assign/view/")])
            ->setColumnDisplay("batch", array($this->repository, 'displayCoRelationAs'), ["batch", "id", "name"])
            ->setColumnDisplay("academic_year", array($this->repository, 'displayCoRelationAs'), ["academic_year", "id", "name"])
            ->setColumnDisplay("semester", array($this->repository, 'displayCoRelationAs'), ["semester", "id", "name"])
            ->setColumnDisplay("status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses, "", "", true])
            ->setColumnDisplay("sb_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses, "", "", true])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnDisplay("approval_status", array($this->repository, 'displayApprovalStatusAs'), [$this->repository->assignedApprovalStatuses])
            ->setColumnDisplay("sb_approval_status", array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])
            ->setColumnFilterMethod("course", "select", [
                "options" => URL::to("/academic/course/search_data"),
                "basedColumns" => [
                    [
                        "column" => "department",
                        "param" => "dept_id",
                    ]
                ],
            ])
            ->setColumnFilterMethod("syllabus", "select", [
                "options" => URL::to("/academic/course_syllabus/search_data"),
                "basedColumns" => [
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ]
                ],
            ])
            ->setColumnFilterMethod("academic_calendar", "select", [
                "options" => URL::to("/academic/academic_calendar/search_data"),
                "basedColumns" => [
                    [
                        "column" => "faculty",
                        "param" => "faculty_id",
                    ],
                    [
                        "column" => "department",
                        "param" => "dept_id",
                    ],
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ],
                    [
                        "column" => "academic_year",
                        "param" => "academic_year_id",
                    ],
                    [
                        "column" => "semester",
                        "param" => "semester_id",
                    ],
                    [
                        "column" => "batch",
                        "param" => "batch_id",
                    ]
                ],
            ])
            ->setColumnFilterMethod("academic_year", "select", [
                "options" => URL::to("/academic/academic_year/search_data"),
                "basedColumns" => [
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ]
                ],
            ])
            ->setColumnFilterMethod("semester", "select", [
                "options" => URL::to("/academic/academic_semester/search_data"),
                "basedColumns" => [
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ]
                ],
            ])
            ->setColumnFilterMethod("batch", "select", [
                "options" => URL::to("/academic/batch/search_data"),
                "basedColumns" => [
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ],
                    [
                        "column" => "syllabus",
                        "param" => "syllabus_id",
                    ]
                ],
            ])

            ->setColumnFilterMethod("base", "select", URL::to("/academic/scrutiny_board_assign/search_data"))
            ->setColumnFilterMethod($this->repository->statusField, "select", $this->repository->statuses)
            ->setColumnFilterMethod("approval_status", "select", $this->repository->assignedApprovalStatuses)
            ->setColumnFilterMethod("sb_approval_status", "select", $this->repository->assignedApprovalStatuses)

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        $this->repository->setCustomFilters("faculty", "department")

            ->setColumnDBField("faculty", "academic_calendar_id", true)
            ->setColumnFKeyField("faculty", "academic_calendar_id", true)
            ->setColumnRelation("faculty", "academicCalendar", "name", true)
            ->setColumnCoRelation("faculty", "faculty", "faculty_name", "faculty_id", "faculty_id", true)
            ->setColumnFilterMethod("faculty", "select", URL::to("/academic/faculty/search_data"), true)

            ->setColumnDBField("department", "academic_calendar_id", true)
            ->setColumnFKeyField("department", "academic_calendar_id", true)
            ->setColumnRelation("department", "academicCalendar", "dept_name", true)
            ->setColumnCoRelation("department", "department", "dept_name", "dept_id", "dept_id", true)

            ->setColumnFilterMethod("department", "select", [
                "options" => URL::to("/academic/department/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "faculty",
                        "param" => "faculty_id",
                    ]
                ],
            ], true);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = $pageTitle . " | Trashed";

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = $pageTitle;

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export", "view");
        }

        $query->with(["academicCalendar", "syllabus", "course", "base"]);

        if ($this->scrutinyBoards) {

            if ($this->trash) {

                $this->repository->enableViewData("list", "export")
                    ->disableViewData("add", "delete", "restore", "edit");
            } else {

                $this->repository->disableViewData("add", "delete", "restore")
                    ->enableViewData("trashList", "trash", "export", "view");
            }

            $query->where("approval_status", 1);

            $this->repository->unsetColumns("status", "approval_status");
        } else {

            $this->repository->unsetColumns("sb_status", "sb_approval_status");
        }

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
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function indexScrutinyBoard()
    {
        $this->scrutinyBoards = true;
        return $this->index();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function trashScrutinyBoard()
    {
        $this->trash = true;
        $this->scrutinyBoards = true;
        return $this->index();
    }

    /**
     * Show the form for creating a new resource.
     * @return Factory|View
     */
    public function create()
    {
        $model = new ScrutinyBoardAssign();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();
        $formSubmitUrl = URL::to($formSubmitUrl);

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/scrutiny_board_assign");

        $this->repository->setPageUrls($urls);

        $dataFetchUrl = URL::to("/academic/scrutiny_board/get_data");

        return view('academic::scrutiny_board_assign.create', compact('formMode', 'formSubmitUrl', 'record', 'dataFetchUrl'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(): JsonResponse
    {
        $model = new ScrutinyBoardAssign();
        $model = $this->repository->getValidatedData($model, [
            "board_name" => "required",
            "academic_calendar_id" => "required|exists:academic_calendars,academic_calendar_id",
            "based_scrutiny_board_id" => "required|exists:scrutiny_boards,id",
        ], [], [
            "board_name" => "Examiner Appointing Structure Name",
            "academic_calendar_id" => "Academic Calendar",
            "based_scrutiny_board_id" => "Based Examination Assessment Structure"
        ]);

        if ($this->repository->isValidData) {

            DB::beginTransaction();
            try {

                $base = $model->base;

                $model->course_id = $base->course_id;
                $model->syllabus_id = $base->syllabus_id;
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] === "success") {

                    $response = $this->repository->createAssignRecord($model);

                    if ($response["notify"]["status"] === "success") {

                        $success  = true;
                    } else {

                        $success  = false;
                    }
                } else {

                    $success  = false;

                    $response = [];
                    $response["notify"]["status"] = "failed";
                    $response["notify"]["notify"][] = "Structure details saving was failed.";
                }
            } catch (Exception $ex) {

                $success = false;

                $response = [];
                $response["notify"]["status"] = "failed";
                $response["notify"]["notify"][] = "Structure details saving was failed.";
            }

            if ($success) {

                DB::commit();
            } else {

                DB::rollBack();
            }

        } else {
            $response = $model;
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
        $model = ScrutinyBoardAssign::with([
            "academicCalendar",
            "academicCalendar.batch",
            "academicCalendar.semester",
            "academicCalendar.academicYear",
            "course",
            "syllabus"
        ])->find($id);

        if ($model) {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/scrutiny_board_assign/create");
            $urls["listUrl"] = URL::to("/academic/scrutiny_board_assign");
            $urls["exportUrl"] = URL::to("/academic/scrutiny_board_assign/export/") . "/" . $id;

            $this->repository->setPageUrls($urls);

            $type = "view";
            $data = $this->repository->getPreparedDataForTable($model->syllabus, $model->academicCalendar, $model, true);

            return view('academic::scrutiny_board_assign.view', compact('record', 'data', 'type'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return BinaryFileResponse
     */
    public function export($id): BinaryFileResponse
    {
        $model = ScrutinyBoardAssign::with(["academicCalendar", "syllabus"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $data = $this->repository->getPreparedDataForTable($model->syllabus, $model->academicCalendar, $model, true);

            $export = new ExcelExport();
            $export->record = $record;
            $export->data = $data;
            $export->view = "academic::scrutiny_board_assign.export";

            return Excel::download($export, "Assessment Structure of Degree Programme.xlsx");
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
        $model = ScrutinyBoardAssign::with(["academicCalendar", "syllabus"])->find($id);

        if ($model) {
            if ($model->{$this->repository->approvalField} !== 1) {

                $record = $model->toArray();
                $formMode = "edit";
                $formSubmitUrl = "/" . request()->path();
                $formSubmitUrl = URL::to($formSubmitUrl);

                $urls = [];
                $urls["addUrl"] = URL::to("/academic/scrutiny_board_assign/create");
                $urls["listUrl"] = URL::to("/academic/scrutiny_board_assign");
                $urls["viewUrl"] = URL::to("/academic/scrutiny_board_assign/view/") . "/" . $id;

                $this->repository->setPageUrls($urls);

                $dataFetchUrl = URL::to("/academic/scrutiny_board/get_data");

                return view('academic::scrutiny_board_assign.create', compact('formMode', 'formSubmitUrl', 'record', 'dataFetchUrl'));
            } else {

                $notify = array();
                $notify["status"] = "failed";
                $notify["notify"][] = $model->name . " is not allowed to edit";
                $notify["notify"][] = "It has been already given the final approval for this Examiner Appointing Structure.";

                $response["notify"] = $notify;

                return $this->repository->handleResponse($response);
            }
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
        $model = ScrutinyBoardAssign::with(["academicCalendar", "syllabus"])->find($id);

        if ($model) {
            if ($model->{$this->repository->approvalField} !== 1) {

                $model = $this->repository->getValidatedData($model, [
                    "board_name" => "required",
                ], [], [
                    "board_name" => "Examiner Appointing Structure Name",
                ]);

                if ($this->repository->isValidData) {

                    $response = $this->repository->saveModel($model);

                    if ($response["notify"]["status"] === "success") {

                        $update = $this->repository->updateData($model, true);

                        if ($update["notify"]["status"] === "failed") {

                            $response["notify"] = array_merge($update["notify"], $response["notify"]);
                        } else {

                            if (request()->post("send_for_approval") == "1") {

                                $this->repository->setApprovalData($model);
                                $response = $this->repository->startApprovalProcess($model, $this->repository->approvalDefaultStatus, $response);
                                $response["notify"]["status"] = "success";
                            }

                            if ($response["notify"]["status"] === "success") {

                                $response["data"] = $this->repository->getPreparedData($model->syllabus, $model->academicCalendar, true, $model);
                            }
                        }
                    }
                } else {
                    $response = $model;
                }
            } else {

                $notify = array();
                $notify["status"] = "failed";
                $notify["notify"][] = $model->name . " is not allowed to edit";
                $notify["notify"][] = "It has been already given the final approval for this Examiner Appointing Structure.";

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
    public function showScrutinyBoard($id)
    {
        $this->repository->statusField = "sb_status";
        $model = ScrutinyBoardAssign::with([
            "academicCalendar",
            "academicCalendar.batch",
            "academicCalendar.semester",
            "academicCalendar.academicYear",
            "course",
            "syllabus"
        ])->find($id);

        if ($model && $model->{$this->repository->approvalField} === 1) {
            $record = $model->toArray();

            $urls = [];
            $urls["listUrl"] = URL::to("/academic/scrutiny_board_assign_sb");
            $urls["exportUrl"] = URL::to("/academic/scrutiny_board_assign_sb/export/") . "/" . $id;

            $this->repository->setPageUrls($urls);

            $type = "view";
            $this->repository->withQuestions = true;
            $data = $this->repository->getPreparedDataForTable($model->syllabus, $model->academicCalendar, $model, true);

            return view('academic::scrutiny_board_assign.scrutiny_board.view', compact('record', 'data', 'type'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return BinaryFileResponse
     */
    public function exportScrutinyBoard($id): BinaryFileResponse
    {
        $this->repository->statusField = "sb_status";
        $model = ScrutinyBoardAssign::with([
            "academicCalendar",
            "academicCalendar.batch",
            "academicCalendar.semester",
            "academicCalendar.academicYear",
            "course",
            "syllabus"
        ])->find($id);

        if ($model && $model->{$this->repository->approvalField} === 1) {
            $record = $model->toArray();

            $this->repository->withQuestions = true;
            $data = $this->repository->getPreparedDataForTable($model->syllabus, $model->academicCalendar, $model, true);

            $export = new ExcelExport();
            $export->record = $record;
            $export->data = $data;
            $export->view = "academic::scrutiny_board_assign.scrutiny_board.export";

            return Excel::download($export, "Scrutiny Board.xlsx");
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Factory|View
     */
    public function editScrutinyBoard($id)
    {
        $this->repository->statusField = "sb_status";
        $model = ScrutinyBoardAssign::with(["academicCalendar", "syllabus"])->find($id);

        if ($model && $model->{$this->repository->approvalField} === 1) {

            if ($model->sb_approval_status !== 1) {

                $record = $model->toArray();
                $formMode = "edit";
                $formSubmitUrl = "/" . request()->path();
                $formSubmitUrl = URL::to($formSubmitUrl);

                $urls = [];
                $urls["listUrl"] = URL::to("/academic/scrutiny_board_assign_sb");
                $urls["viewUrl"] = URL::to("/academic/scrutiny_board_assign_sb/view/") . "/" . $id;

                $this->repository->setPageUrls($urls);

                $dataFetchUrl = URL::to("/academic/scrutiny_board/get_data");

                return view('academic::scrutiny_board_assign.scrutiny_board.create', compact('formMode', 'formSubmitUrl', 'record', 'dataFetchUrl'));
            } else {

                $notify = array();
                $notify["status"] = "failed";
                $notify["notify"][] = $model->name . " is not allowed to edit";
                $notify["notify"][] = "It has been already given the final approval for this Scrutiny Board.";

                $response["notify"] = $notify;

                return $this->repository->handleResponse($response);
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
    public function updateScrutinyBoard($id): JsonResponse
    {
        $this->repository->statusField = "sb_status";
        $model = ScrutinyBoardAssign::with(["academicCalendar", "syllabus"])->find($id);

        if ($model && $model->{$this->repository->approvalField} === 1) {

            if ($model->sb_approval_status !== 1) {

                $this->repository->withQuestions = true;
                $response = $this->repository->updateData($model);

                if ($response["notify"]["status"] === "success") {

                    if (request()->post("send_for_approval") == "1") {

                        $this->repository->setApprovalData($model);
                        $response = $this->repository->startApprovalProcess($model, $this->repository->approvalDefaultStatus, $response);
                        $response["notify"]["status"] = "success";
                    }

                    if ($response["notify"]["status"] === "success") {

                        $response["data"] = $this->repository->getPreparedData($model->syllabus, $model->academicCalendar, true, $model);
                    }
                }
            } else {

                $notify = array();
                $notify["status"] = "failed";
                $notify["notify"][] = $model->name . " is not allowed to edit";
                $notify["notify"][] = "It has been already given the final approval for this Scrutiny Board.";

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
        $model = ScrutinyBoardAssign::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = ScrutinyBoardAssign::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return mixed
     */
    public function changeStatus($id)
    {
        $model = ScrutinyBoardAssign::query()->find($id);
        return $this->repository->updateStatus($model, $this->repository->statusField, "", "remarks");
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return mixed
     */
    public function changeStatusScrutinyBoard($id)
    {
        $this->repository->statusField = "sb_status";
        $model = ScrutinyBoardAssign::query()->find($id);
        return $this->repository->updateStatus($model, $this->repository->statusField, "", "sb_remarks");
    }

    public function approval($id)
    {
        $model = ScrutinyBoardAssign::with(["academicCalendar"])->find($id);

        if ($model) {
            $this->repository->setApprovalData($model);
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
        $model = ScrutinyBoardAssign::with(["academicCalendar"])->find($id);

        if ($model) {
            $this->repository->setApprovalData($model);
            return $this->repository->processApprovalSubmission($model, "approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function scrutinyBoardVerification($id)
    {
        $this->repository->statusField = "sb_status";
        $model = ScrutinyBoardAssign::with(["academicCalendar"])->find($id);

        if ($model && $model->{$this->repository->approvalField} === 1) {
            $this->repository->withQuestions = true;
            $this->repository->setApprovalData($model);
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
    public function scrutinyBoardVerificationSubmit($id)
    {
        $this->repository->statusField = "sb_status";
        $model = ScrutinyBoardAssign::with(["academicCalendar"])->find($id);

        if ($model && $model->{$this->repository->approvalField} === 1) {
            $this->repository->withQuestions = true;
            $this->repository->setApprovalData($model);
            return $this->repository->processApprovalSubmission($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function scrutinyBoardApproval($id)
    {
        $this->repository->statusField = "sb_status";
        $model = ScrutinyBoardAssign::with(["academicCalendar"])->find($id);

        if ($model && $model->{$this->repository->approvalField} === 1) {
            $this->repository->withQuestions = true;
            $this->repository->setApprovalData($model);
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
    public function scrutinyBoardApprovalSubmit($id)
    {
        $this->repository->statusField = "sb_status";
        $model = ScrutinyBoardAssign::with(["academicCalendar"])->find($id);

        if ($model && $model->{$this->repository->approvalField} === 1) {
            $this->repository->withQuestions = true;
            $this->repository->setApprovalData($model);
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
        $model = new ScrutinyBoardAssign();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function scrutinyBoardApprovalHistory($modelHash, $id)
    {
        $this->repository->statusField = "sb_status";
        $model = new ScrutinyBoardAssign();

        $this->repository->withQuestions = true;
        $this->repository->setApprovalData($model);
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new ScrutinyBoardAssign();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
