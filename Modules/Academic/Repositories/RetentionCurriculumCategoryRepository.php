<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class RetentionCurriculumCategoryRepository extends BaseRepository
{
    public array $statuses = [
        ["id" =>"1", "name" =>"Enabled", "label"=>"success"],
        ["id" =>"0", "name" =>"Disabled", "label"=>"danger"]
    ];

    protected function beforeDelete($model, $allowed): bool
    {
        $relations = [
            ["relation" => "curricula", "relationName" => "curricula"]
        ];

        $isAllowed = $this->checkRelationsBeforeDelete($model, "Retention curriculum category", $relations);

        if(!$isAllowed)
        {
            $allowed =false;
        }

        return parent::beforeDelete($model, $allowed);
    }
}
