<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Entities\AcademicSpace;
use Modules\Academic\Entities\Space;

class AcademicSpaceRepository extends BaseRepository
{
    public static function getAcademicSpaces(): array
    {
        $academicSpaces = AcademicSpace::query()->select("space_id")->get()->keyBy("space_id")->toArray();
        $academicSpaceIds = array_keys($academicSpaces);

        return DB::table("spaces_assign", "space")
            ->select("space.id", DB::raw("CONCAT(space_name.name, ' [', space_type.type_name, ']', ' [', space.std_count, ' Max]') AS name"), "space.std_count AS capacity")
            ->join("space_categoryname AS space_name", "space.cn_id", "=", "space_name.id")
            ->join("space_categorytypes AS space_type", "space.type_id", "=", "space_type.id")
            ->whereIn("space.id", $academicSpaceIds)
            ->whereNull("space.deleted_at")
            ->whereNull("space_name.deleted_at")
            ->whereNull("space_type.deleted_at")
            ->get()->toArray();
    }

    public static function getAcademicSpaceIds()
    {
        $academicSpaces = AcademicSpace::query()->select("space_id")->get()->keyBy("space_id")->toArray();

        return array_keys($academicSpaces);
    }

    public static function getAcademicSpaceIdsByCapacity($academicSpaceIds, $capacity)
    {
        $spaces =  Space::query()
            ->select("id")
            ->whereIn("id", $academicSpaceIds)
            ->where("std_count", ">=", $capacity)
            ->get()->keyBy("id")->toArray();

        return array_keys($spaces);
    }

    public static function getCapacityMatchingIds($spaceIds, $expectedCapacity): array
    {
        $query = DB::table("spaces_assign", "space")
            ->select(["space.id", DB::raw("space.std_count AS capacity")])
            ->join("space_categoryname AS space_name", "space.cn_id", "=", "space_name.id")
            ->join("space_categorytypes AS space_type", "space.type_id", "=", "space_type.id")
            ->whereNull("space.deleted_at")
            ->whereNull("space_name.deleted_at")
            ->whereNull("space_type.deleted_at")
            ->whereIn("space.id", $spaceIds)
            ->orderBy("space.std_count", "desc");

        $results = $query->get();

        $data = [];
        $capacity = 0;
        if (count($results) > 0) {

            foreach ($results as $result) {

                $data[] = $result->id;
                $capacity += $result->capacity;

                if ($capacity >= $expectedCapacity) {

                    break;
                }
            }
        }

        if ($capacity < $expectedCapacity) {

            $data = [];
        }

        return $data;
    }
}
