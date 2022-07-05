<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicMeeting;
use Modules\Academic\Entities\AcademicMeetingParticipant;
use Modules\Academic\Entities\AcademicMeetingSchedule;
use Modules\Academic\Repositories\AcademicMeetingParticipantRepository;

class AcademicMeetingParticipantController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new AcademicMeetingParticipantRepository();
    }

    /**
     * Display a listing of the resource.
     * @param int $scheduleId
     * @return Factory|View
     */
    public function index($scheduleId)
    {
        $cc = AcademicMeetingSchedule::query()->find($scheduleId);

        $ccTitle = "";
        if ($cc) {
            //get meeting
            $academicMeeting = AcademicMeeting::query()->find($cc["academic_meeting_id"]);

            if ($academicMeeting) {

                $ccTitle = $academicMeeting["meeting_name"]." | ".$cc["meeting_no"];
            } else {
                abort(404, "Academic Meeting not available");
            }
        } else {
            abort(404, "Academic Meeting Schedule not available");
        }

        $pageTitle = $ccTitle . " | Participants";

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new AcademicMeetingParticipant());

        $this->repository->setColumns("id", "member_name", "invite_status", "rsvp_status", "participate_status", "excuse", "created_at")
            ->setColumnLabel("member_name", "Participant")
            ->setColumnLabel("participate_status", "Status")
            ->setColumnDisplay("member_name", array($this->repository, 'displayRelationAs'),
                ["participant", "admin_id", "name", URL::to("/admin/admin/view/")])
            ->setColumnDisplay("rsvp_status", array($this->repository, 'displayStatusAs'), [$this->repository->rsvpStatuses])
            ->setColumnDisplay("invite_status", array($this->repository, 'displayStatusAs'), [$this->repository->inviteStatuses])
            ->setColumnDisplay("participate_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnDBField("member_name", "admin_id")
            ->setColumnFilterMethod("member_name", "select", URL::to("/admin/admin/search_data"))
            ->setColumnFilterMethod("participate_status", "select", [$this->repository->statuses])
            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();
            $tableTitle = $ccTitle . " | Participants | Trashed";

            $this->repository->setUrl("list", "/academic/academic_meeting_participant/" . $scheduleId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("edit", "delete");
        } else {
            $query = $this->repository->model::query();
            $tableTitle = $ccTitle . " | Participants";

            $this->repository->setCustomControllerUrl("/academic/academic_meeting_participant", ["list"], false)
                ->setUrl("trashList", "/academic/academic_meeting_participant/trash/" . $scheduleId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("view", "trashList", "trash", "export");
        }

        $this->repository->setUrl("add", "/academic/academic_meeting_participant/create/" . $scheduleId);
        $query = $query->where(["academic_meeting_schedule_id" => $scheduleId]);

        $query = $query->with(["participant"]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param int $scheduleId
     * @return Factory|View
     */
    public function trash($scheduleId)
    {
        $this->trash = true;
        return $this->index($scheduleId);
    }

    /**
     * Show the form for creating a new resource.
     * @param $scheduleId
     * @return Factory|View
     */
    public function create($scheduleId)
    {
        $academic_meeting = AcademicMeetingSchedule::query()->find($scheduleId);
        if (!$academic_meeting) {
            abort(404, "Academic Meeting Schedule not available");
        }

        $model = new AcademicMeetingParticipant();
        $model->academic_meeting = $academic_meeting;
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/academic_meeting_participant/" . $scheduleId);

        $this->repository->setPageUrls($urls);

        return view('academic::academic_meeting_participant.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @param $scheduleId
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store($scheduleId): JsonResponse
    {
        $academic_meeting = AcademicMeetingSchedule::query()->find($scheduleId);
        if (!$academic_meeting) {
            abort(404, "Academic Meeting Schedule not available");
        }

        $model = new AcademicMeetingParticipant();
        $model = $this->repository->getValidatedData($model, [
            "admin_id" => [
                'required',
                'exists:admins,admin_id',
                Rule::unique('academic_meeting_participants')->where(function($query) use($scheduleId) {

                    $query->where('academic_meeting_schedule_id', $scheduleId);
                }),
            ],
            "participate_status" => "required",
            "excuse" => "",
        ], [], ["admin_id" => "Participant"]);

        if ($this->repository->isValidData) {
            $model->academic_meeting_schedule_id = $scheduleId;

            $response = $this->repository->saveModel($model);
        } else {
            $response = $model;
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Factory|View
     */
    public function edit($id)
    {
        $model = AcademicMeetingParticipant::with(["AcademicMeeting", "admin"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/academic_meeting_participant/create/" . $model->academic_meeting_schedule_id);
            $urls["listUrl"] = URL::to("/academic/academic_meeting_participant/" . $model->academic_meeting_schedule_id);

            $this->repository->setPageUrls($urls);

            return view('academic::academic_meeting_participant.create', compact('formMode', 'formSubmitUrl', 'record'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Update the specified resource in storage.
     * @param int $id
     * @return JsonResponse
     */
    public function update($id)
    {
        $model = AcademicMeetingParticipant::query()->find($id);

        if ($model) {
            $scheduleId = $model->academic_meeting_schedule_id;
            $fields = [
                "admin_id" => [
                    'required',
                    'exists:admins,admin_id',
                    Rule::unique('academic_meeting_committee_members')->where(function($query) use($id, $scheduleId) {

                        $query->where('id', "!=", $id);
                        $query->where('academic_meeting_schedule_id', $scheduleId);
                    }),
                ],
                "participate_status" => "required",
                "excuse" => "",
            ];

            $model = $this->repository->getValidatedData($model, $fields, [], ["admin_id" => "Participant"]);

            if ($this->repository->isValidData) {
                $response = $this->repository->saveModel($model);
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
        $model = AcademicMeetingParticipant::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = AcademicMeetingParticipant::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return mixed
     */
    public function changeStatus($id)
    {
        $model = AcademicMeetingParticipant::query()->find($id);
        return $this->repository->updateStatus($model, "participate_status");
    }

    /**
     * Store a newly created resource in storage.
     * @param $scheduleId
     * @return JsonResponse
     */
    public function getRecords($scheduleId)
    {
        if (request()->expectsJson()) {

            $schedule = AcademicMeetingSchedule::query()->find($scheduleId);
            if ($schedule) {

                $response["notify"]["status"] = "success";
                $response["data"] = $this->repository->getRecords($scheduleId);
            } else {

                $response["notify"]["status"] = "failed";
                $response["notify"]["notify"] = "Academic Meeting Schedule not available.";
            }

            return $this->repository->handleResponse($response);

        } else {
            abort(404);
        }
    }

    public function changeRSVP($scheduleId)
    {
        $schedule = AcademicMeetingSchedule::with(["academicMeeting"])->find($scheduleId);

        if ($schedule) {
            $schedule = $schedule->toArray();

            //get current record
            $model = AcademicMeetingParticipant::query()
                ->where("academic_meeting_schedule_id", $scheduleId)
                ->where("admin_id", auth("admin")->user()->admin_id)
                ->first();

            if($model) {

                $record = $model->toArray();
                $formSubmitUrl = request()->getPathInfo();

                return view('academic::academic_meeting_schedule.update_rsvp', compact('formSubmitUrl', 'record', 'schedule'));
            } else {
                abort(403, "You are not invited for this meeting.");
            }
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    public function updateRSVP($scheduleId)
    {
        $schedule = AcademicMeetingSchedule::with(["academicMeeting"])->find($scheduleId);

        if ($schedule) {

            //get current record
            $model = AcademicMeetingParticipant::query()
                ->where("academic_meeting_schedule_id", $scheduleId)
                ->where("admin_id", auth("admin")->user()->admin_id)
                ->first();

            if ($model) {
                $fields = [
                    "rsvp_status" => "required",
                    "rsvp_excuse" => "",
                ];

                $model = $this->repository->getValidatedData($model, $fields, [], ["rsvp_status" => "Attending Status", "rsvp_excuse" => "Excuse"]);

                if ($this->repository->isValidData) {

                    $model->excuse = $model->rsvp_excuse;

                    $response = $this->repository->saveModel($model);
                } else {
                    $response = $model;
                }
            } else {
                $response["notify"]["status"] = "failed";
                $response["notify"]["notify"] = "You are not invited for this meeting.";
            }
        } else {

            $response["notify"]["status"] = "failed";
            $response["notify"]["notify"] = "Academic Meeting Schedule not available.";
        }

        echo json_encode($response);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $options = [];
        $options["suffix"] = "Meeting Participant";

        $model = new AcademicMeetingParticipant();
        return $this->repository->recordHistory($model, $modelHash, $id, $options);
    }
}
