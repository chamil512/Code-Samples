<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\LecturerCourseModule;

class LecturerCourseModuleRepository extends BaseRepository
{
    public function update($model)
    {
        $moduleIds = request()->post("module_id");

        $currentIds = $this->getCurrIds($model);
        $updatingIds = [];

        if(is_array($moduleIds) && count($moduleIds)>0)
        {
            $records = [];
            foreach ($moduleIds as $moduleId)
            {
                if(!in_array($moduleId, $currentIds))
                {
                    $record = [];
                    $record["lecturer_id"]=$model->lecturer_id;
                    $record["module_id"]=$moduleId;

                    $records[] = new LecturerCourseModule($record);
                }
                else
                {
                    $updatingIds[] = $moduleId;
                }
            }

            if(count($records)>0)
            {
                $model->modules()->saveMany($records);
            }
        }

        $notUpdatingIds = array_diff($currentIds, $updatingIds);

        if(count($notUpdatingIds)>0)
        {
            $model->modules()->whereIn("module_id", $notUpdatingIds)->delete();
        }
    }

    public function getCurrIds($model): array
    {
        $modules = $model->modules->toArray();

        $ids = [];
        if(is_array($modules) && count($modules)>0)
        {
            foreach ($modules as $module)
            {
                $ids[] = $module["module_id"];
            }
        }

        return $ids;
    }
}
