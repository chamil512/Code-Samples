<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\SimilarCourseModule;

class SimilarCourseModuleRepository extends BaseRepository
{
    public function update($model)
    {
        $moduleIds = request()->post("module");

        $currentIds = self::getSimilarIds($model->id);

        $updatingIds = [];
        $similarIds = [];
        if (is_array($moduleIds) && count($moduleIds) > 0) {

            $records = [];
            foreach ($moduleIds as $moduleId) {

                $similarIds[] = intval($moduleId);

                if (!in_array($moduleId, $currentIds)) {

                    $record = [];
                    $record["module_id"] = $model->id;
                    $record["similar_module_id"] = $moduleId;

                    $records[] = new SimilarCourseModule($record);
                } else {
                    $updatingIds[] = $moduleId;
                }
            }

            if (count($records) > 0) {
                $model->similarModules()->saveMany($records);
            }
        }

        $similarIds[] = $model->id;

        $deletedIds = array_diff($currentIds, $updatingIds);
        if (count($deletedIds) > 0) {
            //delete, deleted similar ids of each similar of this module
            //including this modules' deleted ids
            SimilarCourseModule::query()
                ->whereIn("module_id", $similarIds)
                ->whereIn("similar_module_id", $deletedIds)
                ->delete();

            //and also delete the other way around
            SimilarCourseModule::query()
                ->whereIn("module_id", $deletedIds)
                ->whereIn("similar_module_id", $similarIds)
                ->delete();
        }

        $this->_updateExistingSimilarModules($similarIds, $model->id);
    }

    /**
     * @param $moduleId
     * @return array
     */
    public static function getSimilarIds($moduleId): array
    {
        //mapping the other way around
        $currentIds = SimilarCourseModule::query()
            ->select("similar_module_id")
            ->where("module_id", "=", $moduleId)
            ->groupBy("similar_module_id")
            ->get()->keyBy("similar_module_id")->toArray();

        return array_keys($currentIds);
    }

    /**
     * @param $similarIds
     * @param $currModuleId
     */
    private function _updateExistingSimilarModules($similarIds, $currModuleId)
    {
        if (count($similarIds) > 0) {

            foreach ($similarIds as $similarId) {

                if ($similarId !== $currModuleId) {

                    //get already mapped ids
                    $mappedIds = SimilarCourseModule::query()
                        ->select("similar_module_id")
                        ->where("module_id", $similarId)
                        ->get()->keyBy("similar_module_id")->toArray();

                    $mappedIds = array_keys($mappedIds);

                    //set current module also as mapped
                    $mappedIds[] = $similarId;

                    $notMappedIds = array_diff($similarIds, $mappedIds);

                    if (count($notMappedIds) > 0) {

                        foreach ($notMappedIds as $notMappedId) {

                            $sMModel = new SimilarCourseModule();
                            $sMModel->module_id = $similarId;
                            $sMModel->similar_module_id = $notMappedId;

                            $sMModel->save();
                        }
                    }
                }
            }
        }
    }

    /*public function getSimilarModuleBySubgroup($moduleIds, $subgroupId)
    {
        $data = [];
        if (is_array($moduleIds) && count($moduleIds) > 0) {

            foreach ($moduleIds as $moduleId) {

                $similarIds = self::getSimilarIds($moduleId);

                if (!isset($data[$moduleId])) {

                }
            }
        }
    }*/
}
