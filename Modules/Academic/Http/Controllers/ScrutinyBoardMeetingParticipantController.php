<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\ScrutinyBoardMeeting;
use Modules\Academic\Entities\ScrutinyBoardMeetingParticipant;
use Modules\Academic\Repositories\ScrutinyBoardMeetingParticipantRepository;

class ScrutinyBoardMeetingParticipantController extends Controller
{
    private ScrutinyBoardMeetingParticipantRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new ScrutinyBoardMeetingParticipantRepository();
    }

    /**
     * Display a listing of the resource.
     * @param int $meetingId
     * @return Factory|View
     */
    public function index($meetingId)
    {
        $cc = ScrutinyBoardMeeting::query()->find($meetingId);

        $ccTitle = "";
        if ($cc && $cc["type"] !== 1) {

            $ccTitle = $cc["meeting_name"];
        } else {
            abort(404, "Academic Meeting Schedule not available");
        }

        $pageTitle = $ccTitle . " | Participants";

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new ScrutinyBoardMeetingParticipant());

        $this->repository->setColumns("id", "member_name", "invite_status", "rsvp_status", "rsvp_excuse", "participate_status", "excuse", "created_at")
            ->setColumnLabel("member_name", "Participant")
            ->setColumnLabel("participate_status", "Attending/Attendance Status")
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

            $this->repository->setUrl("list", "/academic/scrutiny_board_meeting_participant/" . $meetingId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("edit", "delete");
        } else {
            $query = $this->repository->model::query();
            $tableTitle = $ccTitle . " | Participants";

            $this->repository->setCustomControllerUrl("/academic/scrutiny_board_meeting_participant", ["list"], false)
                ->setUrl("trashList", "/academic/scrutiny_board_meeting_participant/trash/" . $meetingId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("view", "trashList", "trash", "export");
        }

        $this->repository->setUrl("add", "/academic/scrutiny_board_meeting_participant/create/" . $meetingId);
        $query = $query->where(["scrutiny_board_meeting_id" => $meetingId]);

        $query = $query->with(["participant"]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param int $meetingId
     * @return Factory|View
     */
    public function trash($meetingId)
    {
        $this->trash = true;
        return $this->index($meetingId);
    }

    /**
     * Show the form for creating a new resource.
     * @param $meetingId
     * @return Factory|View
     */
    public function create($meetingId)
    {
        $scrutiny_board_meeting = ScrutinyBoardMeeting::query()->find($meetingId);
        if (!$scrutiny_board_meeting) {
            abort(404, "Academic Meeting Schedule not available");
        }

        $model = new ScrutinyBoardMeetingParticipant();
        $model->scrutiny_board_meeting = $scrutiny_board_meeting;
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/scrutiny_board_meeting_participant/" . $meetingId);

        $this->repository->setPageUrls($urls);

        return view('academic::scrutiny_board_meeting_participant.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @param $meetingId
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store($meetingId): JsonResponse
    {
        $scrutiny_board_meeting = ScrutinyBoardMeeting::query()->find($meetingId);
        if (!$scrutiny_board_meeting) {
            abort(404, "Academic Meeting Schedule not available");
        }

        $model = new ScrutinyBoardMeetingParticipant();
        $model = $this->repository->getValidatedData($model, [
            "admin_id" => 'required|exists:admins,admin_id',
            "participate_status" => "required",
            "excuse" => "",
        ], [], ["admin_id" => "Participant"]);

        if ($this->repository->isValidData) {
            $model->scrutiny_board_meeting_id = $meetingId;

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
        $model = ScrutinyBoardMeetingParticipant::with(["meeting", "admin"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/scrutiny_board_meeting_participant/create/" . $model->scrutiny_board_meeting_id);
            $urls["listUrl"] = URL::to("/academic/scrutiny_board_meeting_participant/" . $model->scrutiny_board_meeting_id);

            $this->repository->setPageUrls($urls);

            return view('academic::scrutiny_board_meeting_participant.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = ScrutinyBoardMeetingParticipant::query()->find($id);

        if ($model) {

            $fields = [
                "admin_id" => 'required|exists:admins,admin_id',
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
        $model = ScrutinyBoardMeetingParticipant::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = ScrutinyBoardMeetingParticipant::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return mixed
     */
    public function changeStatus($id)
    {
        $model = ScrutinyBoardMeetingParticipant::query()->find($id);
        return $this->repository->updateStatus($model, "participate_status");
    }

    /**
     * Store a newly created resource in storage.
     * @param $meetingId
     * @return JsonResponse
     */
    public function getRecords($meetingId): JsonResponse
    {
        if (request()->expectsJson()) {

            $meeting = ScrutinyBoardMeeting::query()->find($meetingId);
            if ($meeting) {

                $response["notify"]["status"] = "success";
                $response["data"] = $this->repository->getRecords($meetingId);
            } else {

                $response["notify"]["status"] = "failed";
                $response["notify"]["notify"] = "Academic Meeting Schedule not available.";
            }

            return $this->repository->handleResponse($response);

        } else {
            abort(404);
        }
    }

    public function changeRSVP($meetingId)
    {
        $meeting = ScrutinyBoardMeeting::with(["scrutinyBoard"])->find($meetingId);

        if ($meeting) {
            $meeting = $meeting->toArray();

            //get current record
            $model = ScrutinyBoardMeetingParticipant::query()
                ->where("scrutiny_board_meeting_id", $meetingId)
                ->where("admin_id", auth("admin")->user()->admin_id)
                ->first();

            if($model) {

                $record = $model->toArray();
                $formSubmitUrl = request()->getPathInfo();

                return view('academic::scrutiny_board_meeting.update_rsvp', compact('formSubmitUrl', 'record', 'meeting'));
            } else {
                abort(403, "You are not invited for this meeting.");
            }
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    public function updateRSVP($meetingId)
    {
        $meeting = ScrutinyBoardMeeting::with(["scrutinyBoard"])->find($meetingId);

        if ($meeting) {

            //get current record
            $model = ScrutinyBoardMeetingParticipant::query()
                ->where("scrutiny_board_meeting_id", $meetingId)
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

        $model = new ScrutinyBoardMeetingParticipant();
        return $this->repository->recordHistory($model, $modelHash, $id, $options);
    }
}
