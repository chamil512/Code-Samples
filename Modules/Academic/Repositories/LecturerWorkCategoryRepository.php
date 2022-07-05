<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\LecturerWorkCategory;

class LecturerWorkCategoryRepository extends BaseRepository
{
    public array $statuses = [
        ["id" =>"1", "name" =>"Enabled", "label"=>"success"],
        ["id" =>"0", "name" =>"Disabled", "label"=>"danger"]
    ];

    public $categoryTypes = [
        ["id" =>"1", "name" =>"Lecture", "label"=>"primary"],
        ["id" =>"0", "name" =>"Other", "label"=>"primary"]
    ];

    protected function beforeDelete($model, $allowed): bool
    {
        $relations = [
            ["relation" => "workSchedules", "relationName" => "lecturer work schedule"]
        ];

        $isAllowed = $this->checkRelationsBeforeDelete($model, "Lecturer work category", $relations);

        if(!$isAllowed)
        {
            $allowed =false;
        }

        return parent::beforeDelete($model, $allowed);
    }

    public static function getLectureWorkCategory()
    {
        $model = LecturerWorkCategory::query()->where("category_type", 1)->first();

        $data = false;
        if ($model) {

            $data = $model->toArray();
        }

        return $data;
    }
}
