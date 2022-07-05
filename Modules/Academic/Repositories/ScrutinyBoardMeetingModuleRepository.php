<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\ScrutinyBoardMeetingModule;
use Modules\Admin\Entities\Admin;

class ScrutinyBoardMeetingModuleRepository extends BaseRepository
{
    public function getRecords($meetingId): array
    {
        $results = ScrutinyBoardMeetingModule::query()->where(["scrutiny_board_meeting_id" => $meetingId])->get()->toArray();

        $data = [];
        if ($results) {
            $adminIds = [];
            foreach ($results as $result) {

                $adminIds[] = $result["module_id"];
            }

            $admins = $this->getParticipantAdminsById($adminIds);

            foreach ($results as $result) {

                if (isset($admins[$result["module_id"]])) {

                    $record = [];
                    $record["id"] = $result["id"];
                    $record["name"] = $admins[$result["module_id"]];
                    $record["status"] = $result["participate_status"];
                    $record["excuse"] = $result["excuse"];

                    $data[] = $record;
                }
            }
        }

        return $data;
    }

    public function getParticipantAdminsById($adminIds): array
    {
        $data = [];

        if (is_array($adminIds) && count($adminIds) > 0) {

            $data = Admin::query()
                ->select("module_id", "name")
                ->whereIn("module_id", $adminIds)->get()->keyBy("id")->toArray();
        }

        return $data;
    }

    public function update($meetingId)
    {
        $updatingIds = [];
        $currentIds = $this->getCurrentRecordsIds($meetingId);

        $records = request()->post("modules");

        if (is_array($records) && count($records) > 0) {

            $dataBulk = [];
            foreach ($records as $moduleId) {

                if (!in_array($moduleId, $currentIds)) {

                    $data = [];
                    $data["scrutiny_board_meeting_id"] = $meetingId;
                    $data["module_id"] = $moduleId;

                    $dataBulk[] = $data;
                }
            }

            if (count($dataBulk) > 0) {
                ScrutinyBoardMeetingModule::query()->insert($dataBulk);
            }
        }

        //deleting not updating records
        $notUpdatingIds = array_diff($currentIds, $updatingIds);
        ScrutinyBoardMeetingModule::query()->whereIn("id", $notUpdatingIds)->delete();
    }

    public function getCurrentRecordsIds($meetingId)
    {
        $results = ScrutinyBoardMeetingModule::query()->select("id")
            ->where(["scrutiny_board_meeting_id" => $meetingId])
            ->get()->keyBy("id")->toArray();

        return array_keys($results);
    }
}
