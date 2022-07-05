<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class LecturerWorkTypeRepository extends BaseRepository
{
    public array $statuses = [
        ["id" =>"1", "name" =>"Enabled", "label"=>"success"],
        ["id" =>"0", "name" =>"Disabled", "label"=>"danger"]
    ];

    protected function beforeDelete($model, $allowed): bool
    {
        $relations = [
            ["relation" => "lecturerWorkSchedule", "relationName" => "Lecturer Work Schedule"]
        ];

        $isAllowed = $this->checkRelationsBeforeDelete($model, "Lecturer work type", $relations);

        if(!$isAllowed)
        {
            $allowed =false;
        }

        return parent::beforeDelete($model, $allowed);
    }
}
