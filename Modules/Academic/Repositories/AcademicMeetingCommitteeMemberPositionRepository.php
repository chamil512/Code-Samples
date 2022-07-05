<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\AcademicMeetingCommitteeMemberPosition;

class AcademicMeetingCommitteeMemberPositionRepository extends BaseRepository
{
    public function update($model)
    {
        $positionIds = request()->post("position_id");

        $currentIds = $this->getCurrIds($model);
        $updatingIds = [];

        if (is_array($positionIds) && count($positionIds) > 0) {
            $records = [];
            foreach ($positionIds as $positionId) {
                if (!in_array($positionId, $currentIds)) {

                    $record = [];
                    $record["academic_meeting_committee_member_id"] = $model->id;
                    $record["academic_meeting_committee_position_id"] = $positionId;

                    $records[] = new AcademicMeetingCommitteeMemberPosition($record);
                } else {
                    $updatingIds[] = $positionId;
                }
            }

            if (count($records) > 0) {
                $model->committeePositions()->saveMany($records);
            }
        }

        $notUpdatingIds = array_diff($currentIds, $updatingIds);

        if (count($notUpdatingIds) > 0) {
            $model->committeePositions()->whereIn("academic_meeting_committee_position_id", $notUpdatingIds)->delete();
        }
    }

    public function getCurrIds($model): array
    {
        $committeePositions = $model->committeePositions->toArray();

        $ids = [];
        if (is_array($committeePositions) && count($committeePositions) > 0) {
            foreach ($committeePositions as $position) {
                $ids[] = $position["academic_meeting_committee_position_id"];
            }
        }

        return $ids;
    }
}
