<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use App\Services\Notify;
use Illuminate\Support\Facades\URL;
use Modules\Academic\Entities\AcademicMeetingParticipant;
use Modules\Academic\Entities\AcademicMeetingSchedule;
use Modules\Admin\Services\Permission;

class AcademicMeetingScheduleRepository extends BaseRepository
{
    public array $statuses = [
        ["id" =>"1", "name" =>"Enabled", "label"=>"success"],
        ["id" =>"0", "name" =>"Disabled", "label"=>"danger"]
    ];

    public function displayScheduleInfoAs()
    {
        return view("academic::academic_meeting_schedule.datatable.schedule_info_ui");
    }

    public function displayDocInfoAs()
    {
        $url = URL::to("/academic/academic_meeting_document/");
        if(!Permission::haveUrlPermission($url)) {
            $url = "";
        }

        return view("academic::academic_meeting_schedule.datatable.doc_info_ui", compact('url'));
    }

    public static function generateMeetingNo($meetingId)
    {
        //get max meeting no
        $meetingNo = AcademicMeetingSchedule::withTrashed()->where("academic_meeting_id", $meetingId)->max("meeting_no");

        if ($meetingNo != null) {
            $meetingNo = intval($meetingNo);
            $meetingNo++;

            if ($meetingNo < 10) {
                $meetingNo = "0" . $meetingNo;
            }
        } else {
            $meetingNo = "01";
        }

        return $meetingNo;
    }

    protected function beforeDelete($model, $allowed): bool
    {
        $relations = [
            ["relation" => "meetingParticipants", "relationName" => "academic meeting participants"],
            ["relation" => "documentSubmissions", "relationName" => "academic meeting document submissions"],
        ];

        $isAllowed = $this->checkRelationsBeforeDelete($model, "academic meeting schedule", $relations);

        if (!$isAllowed) {
            $allowed = false;
        }

        return parent::beforeDelete($model, $allowed);
    }

    /**
     * @param $model
     * @param bool $oldData
     * @param bool $notInvitedOnly
     */
    public function sendInvitationEmail($model, $oldData = false, $notInvitedOnly = false)
    {
        $adminIds = $this->getMeetingParticipantIds($model->id, $notInvitedOnly);

        if (count($adminIds) > 0) {

            $academicMeeting = $model->academic_meeting;

            //prepare invitation
            $invitation = $this->getShortCodedMessage($model);

            $title = $academicMeeting->name . " (" . $model->meeting_no . ")";

            if ($notInvitedOnly) {
                $title .= " meeting scheduled.";
            } else {

                if ($oldData) {

                    if ($oldData["meeting_date"] !== $model->meeting_date || $oldData["meeting_time"] !== $model->meeting_time) {

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

                AcademicMeetingParticipantRepository::updateInviteStatus($model, $adminIds);
            }
        }
    }

    private function getMeetingParticipantIds($scheduleId, $notInvitedOnly = false)
    {
        $statuses = [0, 1];

        if ($notInvitedOnly) {

            $statuses = [0];
        }

        //get notified admins
        $admins = AcademicMeetingParticipant::query()
            ->select("admin_id")
            ->where("academic_meeting_schedule_id", $scheduleId)
            ->where("invite_status", $statuses)
            ->get()
            ->keyBy("admin_id")
            ->toArray();

        return array_keys($admins);
    }

    public function getShortCodedMessage($model)
    {
        //prepare invitation
        $schedule = $model->toArrray();
        $academicSpace = $model->space->toArray();
        $academicMeeting = $model->academic_meeting->toArray();

        $shortCodes = [];
        $shortCodes["~meeting_name~"] = $academicMeeting["name"];
        $shortCodes["~meeting_no~"] = $schedule["meeting_no"];
        $shortCodes["~meeting_date~"] = $schedule["meeting_date"];
        $shortCodes["~meeting_time~"] = date("h:i A", strtotime($schedule["meeting_time"]));
        $shortCodes["~venue~"] = $academicSpace["common_name"];
        $shortCodes["~doc_submit_deadline~"] = date("d M, Y @ h:i A", strtotime($schedule["doc_submit_deadline"]));
        $shortCodes["~doc_submit_url~"] = URL::to("/academic/academic_meeting_document/submit/" . $schedule["id"]);
        $shortCodes["~rsvp_url~"] = URL::to("/academic_meeting_participant/update_rsvp/" . $schedule["id"]);

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

    public function getShortCodes()
    {
        $data = [];

        $group = [];
        $group["name"] = "Meeting Schedule";

        $options[] = ["code" => "~meeting_name~", "label" => "Meeting Name"];
        $options[] = ["code" => "~meeting_no~", "label" => "Meeting No"];
        $options[] = ["code" => "~meeting_date~", "label" => "Meeting Date"];
        $options[] = ["code" => "~meeting_time~", "label" => "Meeting Name"];
        $options[] = ["code" => "~venue~", "label" => "Venue"];
        $options[] = ["code" => "~doc_submit_deadline~", "label" => "Document Submit Deadline"];
        $options[] = ["code" => "~doc_submit_url~", "label" => "Document Submit URL"];
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
}
