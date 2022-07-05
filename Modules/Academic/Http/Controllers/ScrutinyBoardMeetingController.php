<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\ScrutinyBoard;
use Modules\Academic\Entities\ScrutinyBoardMeeting;
use Modules\Academic\Repositories\ScrutinyBoardMeetingDocumentRepository;
use Modules\Academic\Repositories\ScrutinyBoardMeetingModuleRepository;
use Modules\Academic\Repositories\ScrutinyBoardMeetingParticipantRepository;
use Modules\Academic\Repositories\ScrutinyBoardMeetingRepository;

class ScrutinyBoardMeetingController extends Controller
{
    private ScrutinyBoardMeetingRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new ScrutinyBoardMeetingRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $type = request()->route()->getAction()['type'];

        $pageTitle = "Appointment Requests | Scrutiny Board Meetings";
        if ($type === 2) {

            $pageTitle = "Scheduled | Scrutiny Board Meetings";
        } elseif ($type === 4) {

            $pageTitle = "Cancelled | Scrutiny Board Meetings";
        }

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new ScrutinyBoardMeeting());

        $this->repository->setColumns("id", "meeting_name", "department", "batch", "academic_year",
            "semester", "modules", "meeting_date", "start_time", "end_time", "venue", "type", "approval_status",
            "status", "created_at")
            ->setColumnLabel("status", "Status")

            ->setColumnDBField("modules", "scrutiny_board_id")
            ->setColumnFKeyField("modules", "scrutiny_board_id")
            ->setColumnRelation("modules", "modules", "module_name")

            ->setColumnDBField("department", "academic_calendar_id")
            ->setColumnFKeyField("department", "academic_calendar_id")
            ->setColumnRelation("department", "academicCalendar", "name")
            ->setColumnCoRelation("department", "department", "dept_name", "dept_id")

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

            ->setColumnDBField("venue", "space_id")
            ->setColumnFKeyField("venue", "id")
            ->setColumnRelation("venue", "space", "id")

            ->setColumnDisplay("department", array($this->repository, 'displayCoRelationAs'), ["department", "dept_id", "dept_name"])
            ->setColumnDisplay("batch", array($this->repository, 'displayCoRelationAs'), ["batch", "id", "name"])
            ->setColumnDisplay("academic_year", array($this->repository, 'displayCoRelationAs'), ["academic_year", "id", "name"])
            ->setColumnDisplay("semester", array($this->repository, 'displayCoRelationAs'), ["semester", "id", "name"])
            ->setColumnDisplay("modules", array($this->repository, 'displayRelationManyAs'), ["modules", "module", "module_id", "name_year_semester"])
            ->setColumnDisplay("scrutiny_board", array($this->repository, 'displayRelationAs'), ["scrutiny_board", "scrutiny_board_id", "meeting_name"])
            ->setColumnDisplay("venue", array($this->repository, 'displayRelationAs'), ["space", "space_id", "name"])
            ->setColumnDisplay("status", array($this->repository, 'displayStatusAs'), [$this->repository->statuses])
            ->setColumnDisplay("type", array($this->repository, 'displayStatusAs'), [$this->repository->types])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnDisplay("approval_status", array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])
            ->setColumnDisplay("participants", array($this->repository, 'displayListButtonAs'), ["Participants", URL::to("/academic/scrutiny_board_meeting_participant/")])
            ->setColumnDBField("participants", $this->repository->primaryKey)

            ->setColumnDisplay("documents", array($this->repository, 'displayListButtonAs'), ["Documents", URL::to("/academic/scrutiny_board_meeting_document/")])

            ->setColumnFilterMethod("meeting_date", "date_between")
            ->setColumnFilterMethod("status", "select", $this->repository->statuses)
            ->setColumnFilterMethod("type", "select", $this->repository->types)
            ->setColumnFilterMethod("venue", "select", URL::to("/academic/academic_space/search_spaces"))
            ->setColumnFilterMethod($this->repository->approvalField, "select", $this->repository->approvalStatuses)

            ->setColumnFilterMethod("department", "select", [
                "options" => URL::to("/academic/department/search_data"),
                "basedColumns" => [
                    [
                        "column" => "faculty",
                        "param" => "faculty_id",
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

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false)

            ->setColumnDBField("documents", "id");

        $this->repository->setCustomFilters("faculty", "course", "syllabus", "scrutiny_board")

            ->setColumnDBField("faculty", "academic_calendar_id", true)
            ->setColumnFKeyField("faculty", "academic_calendar_id", true)
            ->setColumnRelation("faculty", "academicCalendar", "name", true)
            ->setColumnCoRelation("faculty", "faculty", "faculty_name", "faculty_id", "faculty_id", true)
            ->setColumnFilterMethod("faculty", "select", URL::to("/academic/faculty/search_data"), true)

            ->setColumnDBField("course", "academic_calendar_id", true)
            ->setColumnFKeyField("course", "academic_calendar_id", true)
            ->setColumnRelation("course", "academicCalendar", "name", true)
            ->setColumnCoRelation("course", "course", "course_name", "course_id", true)

            ->setColumnDBField("syllabus", "academic_calendar_id", true)
            ->setColumnFKeyField("syllabus", "academic_calendar_id", true)
            ->setColumnRelation("syllabus", "academicCalendar", "name", true)
            ->setColumnCoRelation("syllabus", "syllabus", "syllabus_name", "syllabus_id", true)

            ->setColumnDBField("scrutiny_board", "scrutiny_board_id", true)
            ->setColumnFKeyField("scrutiny_board", "scrutiny_board_id", true)
            ->setColumnRelation("scrutiny_board", "scrutiny_board", "board_name", true)

            ->setColumnFilterMethod("course", "select", [
                "options" => URL::to("/academic/course/search_data"),
                "basedColumns" => [
                    [
                        "column" => "department",
                        "param" => "dept_id",
                    ]
                ],
            ], true)
            ->setColumnFilterMethod("syllabus", "select", [
                "options" => URL::to("/academic/course_syllabus/search_data"),
                "basedColumns" => [
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ]
                ],
            ], true)
            ->setColumnFilterMethod("scrutiny_board", "select", [
                "options" => URL::to("/academic/scrutiny_board/search_data"),
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
            ], true);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = $pageTitle . " | Trashed";

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "view", "restore", "export")
                ->disableViewData("edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = $pageTitle;

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("view", "trashList", "trash", "export");
        }

        $query->with(["scrutinyBoard", "academicCalendar", "academicCalendar.department", "modules", "space"]);

        if ($type === 2) {

            $this->repository->setTableTitle($tableTitle)
                ->disableViewData("add")
                ->setRowActionBeforeButton(URL::to("/academic/scrutiny_board_meeting_schedule/cancellation/"), "Cancel", "", "fa fa-ban")
                ->setColumnLabel($this->repository->approvalField, "Cancellation Approval Status");

        } elseif ($type === 4) {

            $this->repository->setTableTitle($tableTitle)
                ->disableViewData("add", "edit");
            $this->repository->unsetColumns("type");
        } else {

            $this->repository->unsetColumns("type");
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
     * Show the form for creating a new resource.
     * @return Factory|View
     */
    public function create()
    {
        $type = request()->route()->getAction()['type'];

        $pageTitle = "Request Scrutiny Board Appointment";

        $this->repository->setPageTitle($pageTitle);

        $model = new ScrutinyBoardMeeting();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();
        $formSubmitUrl = URL::to($formSubmitUrl);

        $urls = [];

        if ($type === 1) {

            $urls["listUrl"] = URL::to("/academic/scrutiny_board_meeting");
        } elseif ($type === 4) {

            $urls["listUrl"] = URL::to("/academic/scrutiny_board_meeting_cancelled");
        } else {

            $urls["listUrl"] = URL::to("/academic/scrutiny_board_meeting_schedule");
        }

        $participantDataUrl = URL::to("/academic/scrutiny_board_meeting_participant/get_records");
        $adminFetchUrl = URL::to("/admin/admin/search_data");
        $shortCodes = $this->repository->getShortCodes();

        $this->repository->setPageUrls($urls);

        return view('academic::scrutiny_board_meeting.create',
            compact('formMode', 'formSubmitUrl', 'adminFetchUrl', 'participantDataUrl', 'shortCodes', 'record', 'type'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(): JsonResponse
    {
        $type = request()->route()->getAction()['type'];

        $model = new ScrutinyBoardMeeting();

        $model = $this->repository->getValidatedData($model, [
            "meeting_name" => "required",
            "scrutiny_board_id" => "required|exists:scrutiny_boards,id",
            "meeting_date" => "required|date",
            "start_time" => "required",
            "end_time" => "required",
            "meeting_desc" => "required",
            "space_id" => "required|exists:spaces_assign,id",
        ], [], ["space_id" => "Venue", "scrutiny_board_id" => "Scrutiny Board name", "meeting_desc" => "Description"]);

        if ($this->repository->isValidData) {

            $scrutinyBoardId = $model->scrutiny_board_id;

            $scrutinyBoard = ScrutinyBoard::query()->find($scrutinyBoardId);

            $model->type = $type;
            $model->academic_calendar_id = $scrutinyBoard->academic_calendar_id;
            $response = $this->repository->saveModel($model);

            if ($response["notify"]["status"] === "success") {

                $sBMMRepo = new ScrutinyBoardMeetingModuleRepository();
                $sBMMRepo->update($model->id);

                if(request()->post("send_for_approval") == "1") {

                    $response = $this->repository->startApprovalProcess($model, 0, $response);
                }
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
        $type = request()->route()->getAction()['type'];

        $pageTitle = "View Scrutiny Board Appointment Request";
        if ($type === 2) {

            $pageTitle = "View Scheduled Scrutiny Board Meeting";
        }

        $this->repository->setPageTitle($pageTitle);

        $model = ScrutinyBoardMeeting::withTrashed()->with([
            "scrutinyBoard",
            "academicCalendar",
            "academicCalendar.faculty",
            "academicCalendar.department",
            "academicCalendar.course",
            "academicCalendar.academicYear",
            "academicCalendar.semester",
            "academicCalendar.batch",
            "space",
            "modules",
            "scheduledMeeting",
            "appointment",
            "createdUser",
            "updatedUser",
            "deletedUser"
            ])->find($id);

        if ($model) {
            $record = $model->toArray();

            $urls = [];

            if ($type === 1) {

                $urls["addUrl"] = URL::to("/academic/scrutiny_board_meeting/create");
                $urls["listUrl"] = URL::to("/academic/scrutiny_board_meeting");
                $urls["editUrl"]=URL::to("/academic/scrutiny_board_meeting/edit/" . $id);

                $controllerUrl = URL::to("/academic/scrutiny_board_meeting/");
            } elseif ($type === 4) {

                $urls["listUrl"] = URL::to("/academic/scrutiny_board_meeting_cancelled");
                $urls["editUrl"]=URL::to("/academic/scrutiny_board_meeting_cancelled/edit/" . $id);

                $controllerUrl = URL::to("/academic/scrutiny_board_meeting_cancelled/");
            } else {

                $urls["listUrl"] = URL::to("/academic/scrutiny_board_meeting_schedule");
                $urls["editUrl"]=URL::to("/academic/scrutiny_board_meeting_schedule/edit/" . $id);

                $controllerUrl = URL::to("/academic/scrutiny_board_meeting_schedule/");
            }

            $urls["appReqUrl"] = URL::to("/academic/scrutiny_board_meeting/view/");
            $urls["meetingUrl"] = URL::to("/academic/scrutiny_board_meeting_schedule/view/");
            $urls["cancelledUrl"] = URL::to("/academic/scrutiny_board_meeting_cancelled/view/");
            $urls["docUrl"]=URL::to("/academic/scrutiny_board_meeting_document/" . $id);
            $urls["partUrl"]=URL::to("/academic/scrutiny_board_meeting_participant/" . $id);

            $urls["adminUrl"] = URL::to("/admin/admin/view/");
            $urls["recordHistoryUrl"]=$this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);
            $urls["approvalHistoryUrl"]=$this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);

            $this->repository->setPageUrls($urls);

            $statusInfo = [];
            $statusInfo["status"] = $this->repository->getStatusInfo($model);
            $statusInfo["complete_status"] = $this->repository->getStatusInfo($model, "complete_status", $this->repository->completeStatuses);
            $statusInfo["approval_status"] = $this->repository->getStatusInfo($model, "approval_status", $this->repository->approvalStatuses);

            $this->repository->setPageUrls($urls);

            return view('academic::scrutiny_board_meeting.view', compact('record', 'statusInfo'));
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
        $type = request()->route()->getAction()['type'];

        $pageTitle = "Edit Scrutiny Board Appointment Request";
        if ($type === 2) {

            $pageTitle = "Edit Scheduled Scrutiny Board Meeting";
        }

        $this->repository->setPageTitle($pageTitle);

        $model = ScrutinyBoardMeeting::with(["scrutinyBoard", "space", "modules", "documents"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();
            $formSubmitUrl = URL::to($formSubmitUrl);

            $modules = [];
            if (isset($record["modules"])) {

                foreach ($record["modules"] as $module) {

                    if ($module["module"]) {

                        $modules[] = ["id" => $module["module"]["id"], "name" => $module["module"]["name"]];
                    }
                }
            }

            $urls = [];
            if ($type === 1) {

                $urls["addUrl"] = URL::to("/academic/scrutiny_board_meeting/create");
                $urls["listUrl"] = URL::to("/academic/scrutiny_board_meeting");
            } elseif ($type === 4) {

                $urls["listUrl"] = URL::to("/academic/scrutiny_board_meeting_cancelled");
            } else {

                $urls["listUrl"] = URL::to("/academic/scrutiny_board_meeting_schedule");
            }
            $urls["downloadUrl"]=URL::to("/academic/scrutiny_board_meeting_document/download/") . "/";

            $participantDataUrl = URL::to("/academic/scrutiny_board_meeting_participant/get_records/" . $id);
            $adminFetchUrl = URL::to("/admin/admin/search_data");
            $shortCodes = $this->repository->getShortCodes();

            $this->repository->setPageUrls($urls);

            return view('academic::scrutiny_board_meeting.create',
                compact('formMode', 'formSubmitUrl', 'record', 'adminFetchUrl', 'participantDataUrl', 'shortCodes', 'modules', 'type'));
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
        $type = request()->route()->getAction()['type'];

        $model = ScrutinyBoardMeeting::query()->find($id);

        if ($model) {

            $currentData = $model->toArray();
            $resendStatus = request()->post("invite_resend");

            if ($type === 1) {

                $model = $this->repository->getValidatedData($model, [
                    "meeting_name" => "required",
                    "scrutiny_board_id" => "required|exists:scrutiny_boards,id",
                    "meeting_date" => "required|date",
                    "start_time" => "required",
                    "end_time" => "required",
                    "meeting_desc" => "required",
                    "space_id" => "required|exists:spaces_assign,id",
                ], [], ["space_id" => "Venue", "scrutiny_board_id" => "Scrutiny Board name", "meeting_desc" => "Description"]);
            } elseif ($type === 4) {

                $model = $this->repository->getValidatedData($model, [
                    "meeting_name" => "required",
                    "meeting_date" => "required|date",
                    "start_time" => "required",
                    "end_time" => "required",
                    "meeting_desc" => "",
                    "space_id" => "required|exists:spaces_assign,id",
                    "invitation" => "",
                    "invite_status" => "",
                    "status" => "required",
                ], [], ["space_id" => "Venue", "scrutiny_board_id" => "Scrutiny Board Meeting name", "meeting_desc" => "Description"]);
            } else {

                $model = $this->repository->getValidatedData($model, [
                    "meeting_name" => "required",
                    "meeting_date" => "required|date",
                    "start_time" => "required",
                    "end_time" => "required",
                    "meeting_desc" => "",
                    "space_id" => "required|exists:spaces_assign,id",
                    "invitation" => "",
                    "invite_status" => "",
                    "status" => "required",
                    "complete_status" => "required",
                ], [], ["space_id" => "Venue", "scrutiny_board_id" => "Scrutiny Board Meeting name",
                    "meeting_desc" => "Description", "complete_status" => "Complete Status"]);
            }

            if ($this->repository->isValidData) {
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] === "success") {

                    $sBMMRepo = new ScrutinyBoardMeetingModuleRepository();
                    $sBMMRepo->update($id);

                    if ($type === 1) {

                        if(request()->post("send_for_approval") == "1") {

                            $response = $this->repository->startApprovalProcess($model, 0, $response);
                        }
                    } else {

                        $sBMPRepo = new ScrutinyBoardMeetingParticipantRepository();
                        $sBMPRepo->update($id);

                        $sBMDRepo = new ScrutinyBoardMeetingDocumentRepository();
                        $sBMDRepo->update($model);

                        if($currentData["invite_status"] == "0" && $model->invite_status == "1") {

                            $this->repository->sendInvitationEmail($model);
                        } else if($currentData["invite_status"] == "1" && $resendStatus == "1") {

                            //resend invitation
                            $this->repository->sendInvitationEmail($model, $currentData);
                        } else if($currentData["invite_status"] == "1" && $resendStatus == "2") {

                            //resend invitation to only to not sent participants
                            $this->repository->sendInvitationEmail($model, false, true);
                        }

                        $this->repository->updateEvent($model);

                        $response["data"]["documents"] = $model->documents()->get()->toArray();
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
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Factory|View
     */
    public function cancellation($id)
    {
        $pageTitle = "Cancellation Request | Scrutiny Board Meeting";

        $this->repository->setPageTitle($pageTitle);

        $model = ScrutinyBoardMeeting::query()->find($id);

        if ($model) {
            $record = $model->toArray();

            $formSubmitUrl = "/" . request()->path();
            $formSubmitUrl = URL::to($formSubmitUrl);

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/scrutiny_board_meeting/create");
            $urls["listUrl"] = URL::to("/academic/scrutiny_board_meeting");

            $this->repository->setPageUrls($urls);

            return view('academic::scrutiny_board_meeting.cancellation',
                compact('formSubmitUrl', 'record'));
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
    public function cancellationUpdate($id): JsonResponse
    {
        $model = ScrutinyBoardMeeting::query()->find($id);

        if ($model) {

            $currType = $model->type;

            $model = $this->repository->getValidatedData($model, [
                "type" => "required",
                "cancellation_remarks" => "required",
            ]);

            if ($this->repository->isValidData) {

                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] === "success") {

                    if ($currType === 2 && intval($model->type) === 3) {

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
        $model = ScrutinyBoardMeeting::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = ScrutinyBoardMeeting::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return mixed
     */
    public function changeStatus($id)
    {
        $model = ScrutinyBoardMeeting::query()->find($id);
        return $this->repository->updateStatus($model, "status");
    }

    public function updateAttendance($id)
    {
        $model = ScrutinyBoardMeeting::with(["scrutinyBoard"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/scrutiny_board_meeting/create");
            $urls["listUrl"] = URL::to("/academic/scrutiny_board_meeting");

            $participantDataUrl = URL::to("/academic/scrutiny_board_meeting_participant/get_records/" . $id);
            $adminFetchUrl = URL::to("/admin/admin/search_data");

            $this->repository->setPageUrls($urls);

            return view('academic::scrutiny_board_meeting.update_attendance', compact('record', 'adminFetchUrl', 'participantDataUrl'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    public function verification($id)
    {
        $model = ScrutinyBoardMeeting::with(["academicCalendar"])->find($id);

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
        $model = ScrutinyBoardMeeting::with(["academicCalendar"])->find($id);

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
        $model = ScrutinyBoardMeeting::with(["academicCalendar"])->find($id);

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
        $model = ScrutinyBoardMeeting::with(["academicCalendar"])->find($id);

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
        $model = new ScrutinyBoardMeeting();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new ScrutinyBoardMeeting();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
