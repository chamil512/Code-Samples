<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\Subgroup;

class SubgroupRepository extends BaseRepository
{
    public static function getRelatedSubgroups($subgroupIds): array
    {
        $records = Subgroup::with(["subgroupOne", "subgroupTwo"])
            ->whereIn("id", $subgroupIds)
            ->get()
            ->toArray();

        $relatedIdsBySG = [];
        $allIds = [];
        if (is_array($records) && count($records) > 0) {

            foreach ($records as $record) {

                $subgroupOnes = $record["subgroup_one"];
                $subgroupTwos = $record["subgroup_two"];

                $relatedIds = [];
                if (is_array($subgroupOnes) && count($subgroupOnes) > 0) {

                    foreach ($subgroupOnes as $subgroupOne) {

                        $relatedIds[] = $subgroupOne["subgroup2_id"];
                        $allIds[] = $subgroupOne["subgroup2_id"];
                    }
                }

                if (is_array($subgroupTwos) && count($subgroupTwos) > 0) {

                    foreach ($subgroupTwos as $subgroupTwo) {

                        $relatedIds[] = $subgroupTwo["subgroup1_id"];
                        $allIds[] = $subgroupTwo["subgroup1_id"];
                    }
                }

                $relatedIdsBySG[$record["id"]] = $relatedIds;
            }
        }

        $data = [];
        if (count($allIds) > 0) {

            $records = Subgroup::with(["subgroupStudents"])
                ->select("id")
                ->where("id", $allIds)
                ->whereHas("subgroupStudents")
                ->get()
                ->keyBy("id")
                ->toArray();

            $havingIds = array_keys($records);

            foreach ($relatedIdsBySG as $id => $relatedIds) {

                foreach ($relatedIds as $relatedId) {

                    if (in_array($relatedId, $havingIds)) {

                        if (!isset($data[$id]) || !in_array($relatedId, $data[$id])) {

                            $data[$id][] = $relatedId;
                        }
                    }
                }
            }
        }

        return $data;
    }
}
