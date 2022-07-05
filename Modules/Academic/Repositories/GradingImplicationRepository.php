<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class GradingImplicationRepository extends BaseRepository
{
    protected function beforeDelete($model, $allowed): bool
    {
        $relations = [
            ["relation" => "syllabusGradings", "relationName" => "syllabus gradings"]
        ];

        $isAllowed = $this->checkRelationsBeforeDelete($model, "grading implication", $relations);

        if(!$isAllowed)
        {
            $allowed =false;
        }

        return parent::beforeDelete($model, $allowed);
    }
}
