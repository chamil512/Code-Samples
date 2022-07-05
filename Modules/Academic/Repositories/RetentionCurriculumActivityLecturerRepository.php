<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\RetentionCurriculumActivityLecturer;

class RetentionCurriculumActivityLecturerRepository extends BaseRepository
{
    public function update($baseModel)
    {
        $ids = request()->post("lecturer");

        $currentIds = $this->getCurrIds($baseModel);
        $updatingIds = [];

        if (is_array($ids) && count($ids) > 0) {
            $records = [];
            foreach ($ids as $key => $id) {

                if (!in_array($id, $currentIds)) {

                    $record = [];
                    $record["rc_activity_id"] = $baseModel->id;
                    $record["lecturer_id"] = $id;

                    $records[] = new RetentionCurriculumActivityLecturer($record);
                } else {
                    $updatingIds[] = $id;
                }
            }

            if (count($records) > 0) {
                $baseModel->rcActivityLecturers()->saveMany($records);
            }
        }

        $notUpdatingIds = array_diff($currentIds, $updatingIds);

        if (count($notUpdatingIds) > 0) {
            $baseModel->rcActivityLecturers()->whereIn("lecturer_id", $notUpdatingIds)->delete();
        }
    }

    public function getCurrIds($baseModel)
    {
        $results = $baseModel->rcActivityLecturers->keyBy("id")->toArray();

        return array_keys($results);
    }

    public function getCurrRecords($baseModel)
    {
        $results = $baseModel->rcActivityLecturers->toArray();

        $data = [];
        if (is_array($results) && count($results) > 0) {

            foreach ($results as $result) {

                $record = [];
                $record["id"] = $result["lecturer"]["id"];
                $record["name"] = $result["lecturer"]["name"];

                $data[] = $record;
            }
        }

        return $data;
    }
}
