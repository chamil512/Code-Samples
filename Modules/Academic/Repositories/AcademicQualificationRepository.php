<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class AcademicQualificationRepository extends BaseRepository
{
    public array $statuses = [
        ["id" =>"1", "name" =>"Enabled", "label"=>"success"],
        ["id" =>"0", "name" =>"Disabled", "label"=>"danger"]
    ];

    protected function beforeDelete($model, $allowed): bool
    {
        $relations = [
            ["relation" => "lecturers", "relationName" => "lecturer"]
        ];

        $isAllowed = $this->checkRelationsBeforeDelete($model, "Academic Qualification", $relations);

        if(!$isAllowed)
        {
            $allowed =false;
        }

        return parent::beforeDelete($model, $allowed);
    }
}
