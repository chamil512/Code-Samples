<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class AcademicQualificationLevelRepository extends BaseRepository
{
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
