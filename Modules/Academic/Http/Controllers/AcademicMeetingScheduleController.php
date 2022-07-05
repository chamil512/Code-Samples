<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicMeetingSchedule;
use Modules\Academic\Repositories\AcademicMeetingParticipantRepository;
use Modules\Academic\Repositories\AcademicMeetingScheduleRepository;

class AcademicMeetingScheduleController extends Controller
{
    private AcademicMeetingScheduleRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new AcademicMeetingScheduleRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $pageTitle = "Academic Schedules";
        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new AcademicMeetingSchedule());

        $this->repository->setColumns("id", "meeting_no", "academic_meeting", "meeting_date", "doc_submit_deadline", "participants", "schedule_status", "created_at")
            ->setColumnLabel("doc_submit_deadline", "Document Submissions")
            ->setColumnLabel("schedule_status", "Status")
            ->setColumnDisplay("academic_meeting", array($this->repository, 'displayRelationAs'), ["academic_meeting", "academic_meeting_id", "meeting_name"])
            ->setColumnDisplay("schedule_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnDisplay("participants", array($this->repository, 'displayListButtonAs'), ["Participants", URL::to("/academic/academic_meeting_participant/")])
            ->setColumnDBField("participants", $this->repository->primaryKey)
            ->setColumnFilterMethod("meeting_no")

            ->setColumnDisplay("meeting_date", array($this->repository, 'displayScheduleInfoAs'))
            ->setColumnDisplay("doc_submit_deadline", array($this->repository, 'displayDocInfoAs'))

            ->setColumnFilterMethod("meeting_date", "date_between")
            ->setColumnFilterMethod("schedule_status", "select", $this->repository->statuses)
            ->setColumnFilterMethod("academic_meeting", "select", URL::to("/academic/academic_meeting/search_data"))

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false)
            ->setColumnDBField("academic_meeting", "academic_meeting_id")
            ->setColumnFKeyField("academic_meeting", "academic_meeting_id")
            ->setColumnRelation("academic_meeting", "academic_meeting", "meeting_name");

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = "Academic Schedules | Trashed";

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = "Academic Schedules";

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export");
        }

        $query = $query->with(["academicMeeting"]);

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
        $model = new AcademicMeetingSchedule();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/academic_meeting_schedule");

        $participantDataUrl = URL::to("/academic/academic_meeting_participant/get_records");
        $adminFetchUrl = URL::to("/admin/admin/search_data");
        $committeeFetchUrl = URL::to("/academic/academic_meeting_committee_member/search_data");
        $shortCodes = $this->repository->getShortCodes();

        $this->repository->setPageUrls($urls);

        return view('academic::academic_meeting_schedule.create',
            compact('formMode', 'formSubmitUrl', 'adminFetchUrl', 'committeeFetchUrl', 'participantDataUrl', 'shortCodes', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     */
    public function store()
    {
        $model = new AcademicMeetingSchedule();

        $model = $this->repository->getValidatedData($model, [
            "academic_meeting_id" => "required|exists:academic_meetings,id",
            "meeting_date" => "required|date",
            "meeting_time" => "required",
            "space_id" => "required|exists:spaces_assign,id",
            "doc_submit_deadline" => "required",
            "invitation" => "required",
            "invite_status" => "required",
            "schedule_status" => "required",
            "remarks" => "",
        ], [], ["space_id" => "Venue", "academic_meeting_id" => "Academic Schedule name"]);

        if ($this->repository->isValidData) {
            $model->meeting_no = $this->repository->generateMeetingNo($model->academic_meeting_id);
            $response = $this->repository->saveModel($model);

            if ($response["notify"]["status"] === "success") {
                $ampRepo = new AcademicMeetingParticipantRepository();
                $ampRepo->updateRecords($model->id);

                if($model->invite_status == "1") {

                    $this->repository->sendInvitationEmail($model);
                }

                $response["data"] = $ampRepo->getRecords($model->id);
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
        $model = AcademicMeetingSchedule::query()->find($id);

        if ($model) {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/academic_meeting/create");
            $urls["listUrl"] = URL::to("/academic/academic_meeting_schedule");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_meeting_schedule.view', compact('record'));
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
        $model = AcademicMeetingSchedule::with(["academicMeeting", "space"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/academic_meeting/create");
            $urls["listUrl"] = URL::to("/academic/academic_meeting_schedule");

            $participantDataUrl = URL::to("/academic/academic_meeting_participant/get_records/" . $id);
            $adminFetchUrl = URL::to("/admin/admin/search_data");
            $committeeFetchUrl = URL::to("/academic/academic_meeting_committee_member/search_data");
            $shortCodes = $this->repository->getShortCodes();

            $this->repository->setPageUrls($urls);

            return view('academic::academic_meeting_schedule.create', compact('formMode', 'formSubmitUrl', 'record', 'adminFetchUrl', 'committeeFetchUrl', 'participantDataUrl', 'shortCodes'));
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
        $model = AcademicMeetingSchedule::query()->find($id);

        if ($model) {

            $currentData = $model->toArray();
            $resendStatus = request()->post("invite_resend");

            $model = $this->repository->getValidatedData($model, [
                "academic_meeting_id" => "required|exists:academic_meetings,id",
                "meeting_date" => "required|date",
                "meeting_time" => "required",
                "space_id" => "required|exists:spaces_assign,id",
                "doc_submit_deadline" => "required",
                "invitation" => "required",
                "invite_status" => "required",
                "schedule_status" => "required",
                "remarks" => "",
            ], [], ["space_id" => "Venue", "academic_meeting_id" => "Academic Schedule name"]);

            if ($this->repository->isValidData) {
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] === "success") {
                    $ampRepo = new AcademicMeetingParticipantRepository();
                    $ampRepo->updateRecords($id);

                    if($currentData["invite_status"] == "0" && $model->invite_status == "1") {

                        $this->repository->sendInvitationEmail($model);
                    } else if($currentData["invite_status"] == "1" && $resendStatus == "1") {

                        //resend invitation
                        $this->repository->sendInvitationEmail($model, $currentData);
                    } else if($currentData["invite_status"] == "1" && $resendStatus == "2") {

                        //resend invitation to only to not sent participants
                        $this->repository->sendInvitationEmail($model, false, true);
                    }

                    $response["data"] = $ampRepo->getRecords($id);
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
        $model = AcademicMeetingSchedule::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = AcademicMeetingSchedule::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return mixed
     */
    public function changeStatus($id)
    {
        $model = AcademicMeetingSchedule::query()->find($id);
        return $this->repository->updateStatus($model, "schedule_status");
    }

    public function updateAttendance($id)
    {
        $model = AcademicMeetingSchedule::with(["academicMeeting"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/academic_meeting/create");
            $urls["listUrl"] = URL::to("/academic/academic_meeting_schedule");

            $participantDataUrl = URL::to("/academic/academic_meeting_participant/get_records/" . $id);
            $adminFetchUrl = URL::to("/admin/admin/search_data");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_meeting_schedule.update_attendance', compact('record', 'adminFetchUrl', 'participantDataUrl'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new AcademicMeetingSchedule();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
