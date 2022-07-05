<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class SyllabusModuleExamTypeRepository extends BaseRepository
{
    public function getExamTypeExamCategories($syllabusModule, $examTypeId)
    {
        $examTypes = $syllabusModule->examTypes->where("exam_type_id", $examTypeId)->keyBy("exam_category_id")->toArray();
        return array_keys($examTypes);
    }
}
