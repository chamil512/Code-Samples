<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class RetentionCurriculumActivityTypeRepository extends BaseRepository
{
    public array $statuses = [
        ["id" =>"1", "name" =>"Enabled", "label"=>"success"],
        ["id" =>"0", "name" =>"Disabled", "label"=>"danger"]
    ];

    protected function beforeDelete($model, $allowed): bool
    {
        $relations = [
            ["relation" => "rcActivities", "relationName" => "Retention curriculum activity"]
        ];

        $isAllowed = $this->checkRelationsBeforeDelete($model, "Retention curriculum activity type", $relations);

        if(!$isAllowed)
        {
            $allowed =false;
        }

        return parent::beforeDelete($model, $allowed);
    }
}
