<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\ScrutinyBoardMeetingParticipant;

class ScrutinyBoardMeetingParticipantRepository extends BaseRepository
{
    public array $statuses = [
        ["id" => "0", "name" => "Pending", "label" => "warning"],
        ["id" => "1", "name" => "Participated", "label" => "success"],
        ["id" => "2", "name" => "Absent", "label" => "danger"],
    ];

    public array $inviteStatuses = [
        ["id" => "1", "name" => "Invitation Sent", "label" => "success"],
        ["id" => "0", "name" => "Pending", "label" => "danger"]
    ];

    public array $rsvpStatuses = [
        ["id" => "0", "name" => "Pending", "label" => "warning"],
        ["id" => "1", "name" => "Participating", "label" => "success"],
        ["id" => "2", "name" => "Not Participating", "label" => "danger"],
    ];

    public function getRecords($meetingId): array
    {
        $results = ScrutinyBoardMeetingParticipant::with(["admin"])
            ->select(["id", "admin_id"])
            ->where(["scrutiny_board_meeting_id" => $meetingId])->get()->toArray();

        $data = [];
        if ($results) {

            foreach ($results as $result) {

                $record = [];
                $record["id"] = $result["id"];
                $record["admin"] = [];
                $record["admin"]["id"] = $result["admin"]["id"];
                $record["admin"]["name"] = $result["admin"]["name"];

                $data[] = $record;
            }
        }

        return $data;
    }

    public function update($meetingId, $participants = [])
    {
        $updatingIds = [];
        $currentIds = $this->getCurrentRecordsIds($meetingId);

        if (empty($participants)) {

            $participants = request()->post("participants");
            $participants = json_decode($participants, true);
        }

        if (is_array($participants) && count($participants) > 0) {

            $dataBulk = [];
            foreach ($participants as $participant) {

                $id = $participant["id"] ?? "";

                if (isset($participant["admin"]["id"])) {

                    $admin = $participant["admin"];

                    $data = [];
                    $data["scrutiny_board_meeting_id"] = $meetingId;
                    $data["admin_id"] = $admin["id"];

                    if (isset($participant["status"]["id"])) {

                        $data["participate_status"] = $participant["status"]["id"];
                    }
                    if (isset($participant["excuse"])) {

                        $data["excuse"] = $participant["excuse"];
                    }

                    if ($id === "") {
                        $dataBulk[] = $data;
                    } else {
                        $updatingIds[] = $id;
                        ScrutinyBoardMeetingParticipant::query()->where(["id" => $id])->update($data);
                    }
                }
            }

            if (count($dataBulk) > 0) {
                ScrutinyBoardMeetingParticipant::query()->insert($dataBulk);
            }
        }

        //deleting not updating records
        $notUpdatingIds = array_diff($currentIds, $updatingIds);
        ScrutinyBoardMeetingParticipant::query()->whereIn("id", $notUpdatingIds)->delete();
    }

    public function getCurrentRecordsIds($meetingId)
    {
        $results = ScrutinyBoardMeetingParticipant::query()->select("id")
            ->where(["scrutiny_board_meeting_id" => $meetingId])
            ->get()->keyBy("id")->toArray();

        return array_keys($results);
    }

    public static function updateInviteStatus($meetingId, $adminIds)
    {
        $data = [];
        $data["invite_status"] = 1;

        ScrutinyBoardMeetingParticipant::query()
            ->where(["scrutiny_board_meeting_id" => $meetingId])
            ->whereIn("admin_id", $adminIds)
            ->update($data);
    }
}
