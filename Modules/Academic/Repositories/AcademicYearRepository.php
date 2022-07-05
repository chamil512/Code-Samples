<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class AcademicYearRepository extends BaseRepository
{
    protected function beforeDelete($model, $allowed): bool
    {
        $relations = [
            ["relation" => "courseModules", "relationName" => "course module"]
        ];

        $isAllowed = $this->checkRelationsBeforeDelete($model, "academic year", $relations);

        if(!$isAllowed)
        {
            $allowed =false;
        }

        return parent::beforeDelete($model, $allowed);
    }
}
