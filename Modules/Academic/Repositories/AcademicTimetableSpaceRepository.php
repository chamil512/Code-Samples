<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\AcademicTimetableSpace;

class AcademicTimetableSpaceRepository extends BaseRepository
{
    public static function addRecords($timetableInfoId, $ids)
    {
        if(isset($ids) && count($ids)>0)
        {
            foreach ($ids as $id)
            {
                $record = new AcademicTimetableSpace();
                $record->academic_timetable_information_id = $timetableInfoId;
                $record->space_id = $id;

                $record->save();
            }
        }
    }

    public static function updateRecords($timetableInfoId, $ids)
    {
        //get existing records
        $existingRecords = AcademicTimetableSpace::query()
            ->select("space_id")
            ->where("academic_timetable_information_id", $timetableInfoId)
            ->get()->keyBy("space_id")->toArray();

        $existingIds = array_keys($existingRecords);

        $updatingIds = [];
        if(isset($ids) && count($ids)>0)
        {
            foreach ($ids as $id)
            {
                $updatingIds[]=$id;
                AcademicTimetableSpace::query()
                    ->updateOrCreate(["academic_timetable_information_id" => $timetableInfoId, "space_id" => $id]);
            }
        }

        $deletedIds = array_diff($existingIds, $updatingIds);

        //delete those records
        AcademicTimetableSpace::query()
            ->where("academic_timetable_information_id", $timetableInfoId)
            ->whereIn("space_id", $deletedIds)->delete();
    }

    public static function deleteRecords($timetableInfoIds)
    {
        AcademicTimetableSpace::query()->whereIn("academic_timetable_information_id", $timetableInfoIds)->delete();
    }
}
