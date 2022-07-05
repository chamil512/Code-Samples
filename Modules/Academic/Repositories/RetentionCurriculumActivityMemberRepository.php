<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\RetentionCurriculumActivityMember;

class RetentionCurriculumActivityMemberRepository extends BaseRepository
{
    public function update($baseModel)
    {
        $ids = request()->post("external_individual");

        $currentIds = $this->getCurrIds($baseModel);
        $updatingIds = [];

        if (is_array($ids) && count($ids) > 0) {
            $records = [];
            foreach ($ids as $key => $id) {

                if (!in_array($id, $currentIds)) {

                    $record = [];
                    $record["rc_activity_id"] = $baseModel->id;
                    $record["external_individual_id"] = $id;

                    $records[] = new RetentionCurriculumActivityMember($record);
                } else {
                    $updatingIds[] = $id;
                }
            }

            if (count($records) > 0) {
                $baseModel->rcActivityMembers()->saveMany($records);
            }
        }

        $notUpdatingIds = array_diff($currentIds, $updatingIds);

        if (count($notUpdatingIds) > 0) {
            $baseModel->rcActivityMembers()->whereIn("external_individual_id", $notUpdatingIds)->delete();
        }
    }

    public function getCurrIds($baseModel)
    {
        $results = $baseModel->rcActivityMembers->keyBy("id")->toArray();

        return array_keys($results);
    }

    public function getCurrRecords($baseModel)
    {
        $results = $baseModel->rcActivityMembers->toArray();

        $data = [];
        if (is_array($results) && count($results) > 0) {

            foreach ($results as $result) {

                $record = [];
                $record["id"] = $result["external_individual"]["id"];
                $record["name"] = $result["external_individual"]["name"];

                $data[] = $record;
            }
        }

        return $data;
    }
}
