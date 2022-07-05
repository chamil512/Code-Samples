<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class AcademicSemesterRepository extends BaseRepository
{
    protected function beforeDelete($model, $allowed): bool
    {
        $relations = [
            ["relation" => "courseModules", "relationName" => "course module"]
        ];

        $isAllowed = $this->checkRelationsBeforeDelete($model, "academic semester", $relations);

        if(!$isAllowed)
        {
            $allowed =false;
        }

        return parent::beforeDelete($model, $allowed);
    }
}
