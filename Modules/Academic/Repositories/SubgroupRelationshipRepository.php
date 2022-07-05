<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\SubgroupRelationship;

class SubgroupRelationshipRepository extends BaseRepository
{
    public function getRelatedSubgroups($subgroupId): array
    {
        $records = SubgroupRelationship::query()
            ->select(["subgroup1_id", "subgroup2_id"])
            ->where("subgroup1_id", $subgroupId)
            ->orWhere("subgroup2_id", $subgroupId)
            ->get()
            ->toArray();

        $data = [];
        if (is_array($records) && count($records) > 0) {

            foreach ($records as $record) {

                if ($record["subgroup1_id"] === $subgroupId) {

                    $data[] = $record["subgroup2_id"];
                } else {

                    $data[] = $record["subgroup1_id"];
                }
            }
        }

        return $data;
    }
}
