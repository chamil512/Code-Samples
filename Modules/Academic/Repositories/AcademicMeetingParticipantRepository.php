<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\AcademicMeetingParticipant;
use Modules\Academic\Entities\ScrutinyBoardMeetingParticipant;
use Modules\Admin\Entities\Admin;

class AcademicMeetingParticipantRepository extends BaseRepository
{
    public array $statuses = [
        ["id" =>"1", "name" =>"Participated", "label" => "success"],
        ["id" =>"0", "name" =>"Absent", "label" => "danger"]
    ];

    public $inviteStatuses = [
        ["id" =>"1", "name" =>"Invitation Sent", "label" => "success"],
        ["id" =>"0", "name" =>"Pending", "label" => "danger"]
    ];

    public $rsvpStatuses = [
        ["id" =>"1", "name" =>"Participating", "label" => "success"],
        ["id" =>"0", "name" =>"Not Participating", "label" => "danger"]
    ];

    public function getRecords($scheduleId)
    {
        $results = AcademicMeetingParticipant::query()->where(["academic_meeting_schedule_id" => $scheduleId])->get()->toArray();

        $data = [];
        if ($results) {
            $adminIds = [];
            foreach ($results as $result) {

                $adminIds[] = $result["admin_id"];
            }

            $admins = $this->getParticipantAdminsById($adminIds);

            foreach ($results as $result) {

                if (isset($admins[$result["admin_id"]])) {

                    $record = [];
                    $record["id"] = $result["id"];
                    $record["name"] = $admins[$result["admin_id"]];
                    $record["status"] = $result["participate_status"];
                    $record["excuse"] = $result["excuse"];

                    $data[] = $record;
                }
            }
        }

        return $data;
    }

    public function getParticipantAdminsById($adminIds)
    {
        $data = [];

        if (is_array($adminIds) && count($adminIds) > 0) {

            $data = Admin::query()
                ->select("admin_id", "name")
                ->whereIn("admin_id", $adminIds)->get()->keyBy("id")->toArray();
        }

        return $data;
    }

    public function updateRecords($scheduleId)
    {
        $updatingIds = [];
        $currentIds = $this->getCurrentRecordsIds($scheduleId);

        $records = request()->post("participants");
        $records = json_decode($records, true);

        if (is_array($records) && count($records) > 0) {

            $dataBulk = [];
            foreach ($records as $record) {

                $id = $record["id"];
                $admin = $record["name"];

                if (isset($admin["id"])) {

                    $data = [];
                    $data["academic_meeting_schedule_id"] = $scheduleId;
                    $data["admin_id"] = $admin["id"];

                    if(isset($record["status"]["id"])) {

                        $data["participate_status"] = $record["status"]["id"];
                    }
                    if(isset($record["excuse"])) {

                        $data["excuse"] = $record["excuse"];
                    }

                    if ($id === "") {
                        $dataBulk[] = $data;
                    } else {
                        $updatingIds[] = $id;
                        AcademicMeetingParticipant::query()->where(["id" => $id])->update($data);
                    }
                }
            }

            if (count($dataBulk) > 0) {
                AcademicMeetingParticipant::query()->insert($dataBulk);
            }
        }

        //deleting not updating records
        $notUpdatingIds = array_diff($currentIds, $updatingIds);
        AcademicMeetingParticipant::query()->whereIn("id", $notUpdatingIds)->delete();
    }

    public function getCurrentRecordsIds($scheduleId)
    {
        $results = AcademicMeetingParticipant::query()->select("id")
            ->where(["academic_meeting_schedule_id" => $scheduleId])
            ->get()->keyBy("id")->toArray();

        return array_keys($results);
    }

    public static function updateInviteStatus($scheduleId, $adminIds)
    {
        $data = [];
        $data["invite_status"] = 1;

        AcademicMeetingParticipant::query()
            ->where(["academic_meeting_schedule_id" => $scheduleId])
            ->whereIn("admin_id", $adminIds)
            ->update($data);
    }
}
