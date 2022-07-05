<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class AcademicMeetingRepository extends BaseRepository
{
    public array $statuses = [
        ["id" =>"1", "name" =>"Enabled", "label"=>"success"],
        ["id" =>"0", "name" =>"Disabled", "label"=>"danger"]
    ];

    protected function beforeDelete($model, $allowed): bool
    {
        $relations = [
            ["relation" => "committeeMembers", "relationName" => "committee members"],
            ["relation" => "meetingSchedules", "relationName" => "academic meeting schedules"],
        ];

        $isAllowed = $this->checkRelationsBeforeDelete($model, "academic meeting", $relations);

        if(!$isAllowed)
        {
            $allowed =false;
        }

        return parent::beforeDelete($model, $allowed);
    }
}
