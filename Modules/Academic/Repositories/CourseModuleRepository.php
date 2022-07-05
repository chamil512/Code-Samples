<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class CourseModuleRepository extends BaseRepository
{
    public array $statuses = [
        ["id" =>"1", "name" =>"Enabled", "label"=>"success"],
        ["id" =>"0", "name" =>"Disabled", "label"=>"danger"]
    ];

    public function getSimilarModules($model)
    {
        $records = $model->similarModules->toArray();

        $data = [];
        if (is_array($records) && count($records) > 0) {

            foreach ($records as $record) {

                $module = $record["similar_module"];
                $name = $module["name"];

                if (isset($module["course"]["name"])) {

                    $course = $module["course"];
                    $name .= " [" . $course["name"] . "]";
                }

                $id = $module["id"];
                $data[] = ["id" => $id, "name" => $name];
            }
        }

        return $data;
    }
}
