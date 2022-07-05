<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use App\Services\Notify;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\ScrutinyBoardMeetingParticipant;
use Modules\Settings\Repositories\CalendarEventRepository;

class ScrutinyBoardMeetingRepository extends BaseRepository
{
    public string $statusField = "status";

    public array $statuses = [
        ["id" => "1", "name" => "Enabled", "label" => "success"],
        ["id" => "0", "name" => "Disabled", "label" => "danger"]
    ];

    public array $types = [
        ["id" => "1", "name" => "Appointment Request", "label" => "success"],
        ["id" => "2", "name" => "Scheduled Meeting", "label" => "success"],
        ["id" => "3", "name" => "Cancellation Requested", "label" => "warning"],
        ["id" => "4", "name" => "Cancelled Meeting", "label" => "danger"],
    ];

    public array $completeStatuses = [
        ["id" => "1", "name" => "Completed", "label" => "success"],
        ["id" => "0", "name" => "Pending Completion", "label" => "warning"]
    ];

    public array $approvalStatuses = [
        ["id" => "", "name" => "Not Sent for Approval", "label" => "info"],
        ["id" => "0", "name" => "Verification Pending", "label" => "warning"],
        ["id" => "3", "name" => "Verified & Pending Approval", "label" => "success"],
        ["id" => "4", "name" => "Verification Declined", "label" => "danger"],
        ["id" => "1", "name" => "Approved", "label" => "success"],
        ["id" => "2", "name" => "Declined", "label" => "danger"],
    ];

    /*
     * Approval properties and methods starts
     */
    public string $approvalField = "approval_status";
    public $approvalDefaultStatus = "0";
    protected array $approvalSteps = [
        [
            "step" => "verification",
            "approvedStatus" => 3,
            "declinedStatus" => 4,
            "route" => "/academic/scrutiny_board_meeting/verification",
            "permissionRoutes" => [],
        ],
        [
            "step" => "approval",
            "approvedStatus" => 1,
            "declinedStatus" => 2,
            "route" => "/academic/scrutiny_board_meeting/approval",
            "permissionRoutes" => [],
        ],
    ];

    /**
     * @param $model
     * @param $step
     * @return string
     */
    protected function getApprovalStepTitle($model, $step): string
    {
        switch ($step) {
            case "verification" :

                if (intval($model->type) === 3) {

                    $text = $model->name . " cancellation request verification.";
                } else {

                    $text = $model->name . " appointment request verification.";
                }
                break;

            case "approval" :

                if (intval($model->type) === 3) {

                    $text = $model->name . " cancellation request approval.";
                } else {

                    $text = $model->name . " appointment request approval.";
                }
                break;

            default:
                $text = "";
                break;
        }

        return $text;
    }

    /**
     * @param $model
     */
    protected function setApprovalData($model)
    {
        if (intval($model->type) === 3) {

            $this->approvalSteps = [
                [
                    "step" => "verification",
                    "approvedStatus" => 3,
                    "declinedStatus" => 4,
                    "route" => "/academic/scrutiny_board_meeting_schedule/verification",
                    "permissionRoutes" => [],
                ],
                [
                    "step" => "approval",
                    "approvedStatus" => 1,
                    "declinedStatus" => 2,
                    "route" => "/academic/scrutiny_board_meeting_schedule/approval",
                    "permissionRoutes" => [],
                ],
            ];
        }
    }

    /**
     * @param $model
     * @param $step
     * @return array
     */
    protected function getApprovalStepUsers($model, $step): array
    {
        $data = [];

        $academicCalendar = $model->academicCalendar;

        if ($step === "verification") {

            //get department id
            $deptId = $academicCalendar->dept_id;

            //get head of the department's id
            $data = DepartmentHeadRepository::getHODAdmins($deptId);
        } else {

            //get department id
            $facultyId = $academicCalendar->faculty_id;

            //get head of the department's id
            $admin = FacultyDeanRepository::getDeanAdmin($facultyId);

            if ($admin && isset($admin["id"])) {

                $data[] = $admin["id"];
            }
        }

        return $data;
    }

    /**
     * @param $model
     * @param $step
     * @return string|Application|Factory|View
     */
    protected function getApprovalStepDescription($model, $step): View
    {
        $record = $model->toArray();

        $fileName = $step;

        if (intval($model->type) === 3) {

            $directory = "cancellation";

            $url = URL::to("/academic/scrutiny_board_meeting_schedule/view/" . $model->id);
        } else {

            $directory = "appointment";

            $url = URL::to("/academic/scrutiny_board_meeting/view/" . $model->id);
        }

        return view("academic::scrutiny_board_meeting.approvals." . $directory . "." . $fileName, compact('record', 'url'));
    }

    protected function onApproved($model, $step, $previousStatus)
    {
        if ($step === "approval") {

            if (intval($model->type) === 1) {

                $model->{$this->statusField} = 1;
                $model->save();

                if ($step === "approval") {

                    if ($previousStatus !== 1) {

                        if ($model->scheduledMeeting) {

                            $meeting = $model->scheduledMeeting;
                            if ($meeting->delete()) {

                                $this->deleteEvent($meeting);
                            }
                        }

                        $meeting = $this->replicate($model);
                        $meeting->type = 2;
                        $meeting->{$this->statusField} = 1;
                        $meeting->{$this->approvalField} = null;
                        $meeting->sb_meeting_appointment_id = $model->id;
                        $meeting->save();

                        $this->updateEvent($meeting);
                        $this->updateParticipants($meeting);
                    }
                }
            } elseif (intval($model->type) === 3) {

                $model->{$this->statusField} = 0;
                $model->type = 4;
                if ($model->save()) {

                    $this->deleteEvent($model);
                }
            }
        }
    }

    protected function onDeclined($model, $step, $previousStatus)
    {
        if ($step === "approval") {

            if (intval($model->type) === 1) {

                if ($model->{$this->statusField} === 1) {

                    $model->{$this->statusField} = 0;
                    $model->save();

                    if ($model->scheduledMeeting) {

                        $meeting = $model->scheduledMeeting;
                        $meeting->{$this->statusField} = 0;

                        if ($meeting->save()) {

                            $this->deleteEvent($meeting);
                        }
                    }
                }
            } elseif (intval($model->type) === 3) {

                if ($model->{$this->statusField} !== 1) {

                    $model->{$this->statusField} = 1;
                    if ($model->save()) {

                        $this->updateEvent($model);
                    }
                }
            }
        }
    }

    public function onStartFailed($model, $response)
    {
        if (intval($model->type) === 3) {

            $model->type = 2;
            $model->cancellation_remarks = "";

            $model->save();
        }
    }

    /*
     * Approval properties and methods ends
     */

    /**
     * @param $model
     * @return mixed
     */
    public function replicate($model)
    {
        $replica = $model->replicate();

        $replica->push();

        $modules = $model->modules()->get();

        if (count($modules) > 0) {

            foreach ($modules as $module) {

                $modModel = $module->replicate();
                $modModel->scrutiny_board_meeting_id = $replica->id;

                $modModel->push();
            }
        }

        return $replica;
    }

    /**
     * @param $model
     * @param $statusField
     * @param $status
     * @param bool $allowed
     * @return bool
     */
    protected function isStatusUpdateAllowed($model, $statusField, $status, bool $allowed = true): bool
    {
        $approvalField = $this->approvalField;

        if ($model->{$approvalField} !== 1) {

            $errors = [];
            $errors[] = "This record should have been approved to be eligible to update the status.";

            $this->setErrors($errors);
            $allowed = false;
        }

        return parent::isStatusUpdateAllowed($model, $statusField, $status, $allowed);
    }

    public function displayScheduleInfoAs()
    {
        return view("academic::scrutiny_board_meeting.datatable.schedule_info_ui");
    }

    /**
     * @param $model
     */
    protected function afterDelete($model)
    {
        //delete calendar event
        $this->deleteEvent($model);
    }

    /**
     * @param $model
     */
    protected function afterRestore($model)
    {
        //update calendar event
        $this->updateEvent($model);
    }

    /**
     * @param $model
     */
    public function updateEvent($model)
    {
        $data = [];
        $data["event_name"] = $model->name;
        $data["event_description"] = $model->meeting_desc;
        $data["start_time"] = $model->start_time;
        $data["end_time"] = $model->end_time;
        $data["full_day_event"] = 0;
        $data["event_status"] = 1;

        //delete calendar event
        $calEVRepo = new CalendarEventRepository();
        $calEVRepo->updateEvent($model, "sb_meeting", $data);
    }

    /**
     * @param $model
     */
    public function deleteEvent($model)
    {
        //delete calendar event
        $calEVRepo = new CalendarEventRepository();
        $calEVRepo->deleteEvent($model, "sb_meeting");
    }

    /**
     * @param $model
     * @param ?array $oldData
     * @param bool $notInvitedOnly
     */
    public function sendInvitationEmail($model, $oldData = null, $notInvitedOnly = false)
    {
        $adminIds = $this->getMeetingParticipantIds($model->id, $notInvitedOnly);

        if (count($adminIds) > 0) {

            //prepare invitation
            $invitation = $this->getShortCodedMessage($model);

            $title = $model->name;

            if ($notInvitedOnly) {
                $title .= " meeting scheduled.";
            } else {

                if ($oldData) {

                    if ($oldData["meeting_date"] !== $model->meeting_date || $oldData["start_time"] !== $model->start_time) {

                        $title .= " meeting rescheduled.";
                    } else {
                        $title .= " meeting reminder.";
                    }
                } else {

                    $title .= " meeting scheduled.";
                }
            }

            $response = Notify::queue($title, $invitation, "", $adminIds);

            if ($response["status"] === "success") {

                ScrutinyBoardMeetingParticipantRepository::updateInviteStatus($model, $adminIds);
            }
        }
    }

    private function getMeetingParticipantIds($meetingId, $notInvitedOnly = false): array
    {
        $statuses = [0, 1];

        if ($notInvitedOnly) {

            $statuses = [0];
        }

        //get notified admins
        $admins = ScrutinyBoardMeetingParticipant::query()
            ->select("admin_id")
            ->where("scrutiny_board_meeting_id", $meetingId)
            ->where("invite_status", $statuses)
            ->get()
            ->keyBy("admin_id");

        $data = [];
        if ($admins) {

            $admins = $admins->toArray();
            $data = array_keys($admins);
        }

        return $data;
    }

    public function getShortCodedMessage($model)
    {
        //prepare invitation
        $academicSpace = $model->space->toArray();

        $shortCodes = [];
        $shortCodes["~meeting_name~"] = $model->name;
        $shortCodes["~meeting_date~"] = $model->meeting_date;
        $shortCodes["~start_time~"] = date("h:i A", strtotime($model->start_time));
        $shortCodes["~end_time~"] = date("h:i A", strtotime($model->end_time));
        $shortCodes["~venue~"] = $academicSpace["common_name"] ?? "";
        $shortCodes["~rsvp_url~"] = "<a href='" . URL::to("/academic/scrutiny_board_meeting_participant/update_rsvp/" . $model->id) . "'>RSVP LINK</a>";

        $keys = array_keys($shortCodes);
        $values = array_values($shortCodes);

        return str_replace($keys, $values, $model->invitation);
    }

    public function getShortCodedMessageWithParticipant($invitation, $participant)
    {
        $shortCodes = [];
        $shortCodes["~participant_name~"] = $participant["name"];
        $shortCodes["~participant_email~"] = $participant["email"];

        $keys = array_keys($shortCodes);
        $values = array_values($shortCodes);

        return str_replace($keys, $values, $invitation);
    }

    public function getShortCodes(): array
    {
        $data = [];

        $group = [];
        $group["name"] = "Scrutiny Board Meeting";

        $options[] = ["code" => "~meeting_name~", "label" => "Meeting Name"];
        $options[] = ["code" => "~meeting_date~", "label" => "Meeting Date"];
        $options[] = ["code" => "~start_time~", "label" => "Meeting Start Time"];
        $options[] = ["code" => "~end_time~", "label" => "Meeting End Time"];
        $options[] = ["code" => "~venue~", "label" => "Venue"];
        $options[] = ["code" => "~rsvp_url~", "label" => "RSVP URL"];

        $group["options"] = $options;
        $data[] = $group;

        /*$group = [];
        $group["name"] = "Participant Details";

        $options[] = ["code" => "~participant_name~", "label" => "Participant Name"];
        $options[] = ["code" => "~participant_email~", "label" => "Participant Email"];

        $group["options"] = $options;
        $data[] = $group;*/

        return $data;
    }

    public function updateParticipants($model)
    {
        //prepare participants
        $data = [];

        $academicCalendar = $model->academicCalendar;

        //get department id
        $facultyId = $academicCalendar->faculty_id;

        //get dean
        $dean = FacultyDeanRepository::getDeanAdmin($facultyId);

        $record = [];
        $record["admin"] = $dean;

        $data[] = $record;

        //get department id
        $deptId = $academicCalendar->dept_id;

        //get head of the department's id
        $hod = DepartmentHeadRepository::getHODAdmin($deptId);

        $record = [];
        $record["admin"] = $hod;

        $data[] = $record;

        //get scrutiny board people
        $modules = $model->modules()->get();

        $moduleIds = [];
        if ($modules) {

            $modules = $modules->toArray();

            foreach ($modules as $module) {

                $moduleIds[] = $module["module_id"];
            }
        }

        $sbPeople = ScrutinyBoardRepository::getSBPeople($model->scrutiny_board_id, $moduleIds);

        if (is_array($sbPeople) && count($sbPeople) > 0) {

            $adminIds = [];
            foreach ($sbPeople as $sbPerson) {

                if (!in_array($sbPerson["admin_id"], $adminIds)) {

                    $record = [];
                    $record["admin"] = ["id" => $sbPerson["admin_id"]];

                    $data[] = $record;

                    $adminIds[] = $sbPerson["admin_id"];
                }
            }
        }

        $sbmRepo = new ScrutinyBoardMeetingParticipantRepository();
        $sbmRepo->update($model->id, $data);
    }
}
