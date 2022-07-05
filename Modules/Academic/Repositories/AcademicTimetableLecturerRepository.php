<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\AcademicTimetableLecturer;

class AcademicTimetableLecturerRepository extends BaseRepository
{
    public static function addRecords($timetableInfoId, $ids)
    {
        if(isset($ids) && count($ids)>0)
        {
            foreach ($ids as $id)
            {
                $record = new AcademicTimetableLecturer();
                $record->academic_timetable_information_id = $timetableInfoId;
                $record->lecturer_id = $id;

                $record->save();
            }
        }
    }

    public static function updateRecords($timetableInfoId, $ids)
    {
        //get existing records
        $existingRecords = AcademicTimetableLecturer::query()
            ->select("lecturer_id")
            ->where("academic_timetable_information_id", $timetableInfoId)
            ->get()->keyBy("lecturer_id")->toArray();

        $existingIds = array_keys($existingRecords);

        $updatingIds = [];
        if(isset($ids) && count($ids)>0)
        {
            foreach ($ids as $id)
            {
                $updatingIds[]=$id;
                AcademicTimetableLecturer::query()
                    ->updateOrCreate(["academic_timetable_information_id" => $timetableInfoId, "lecturer_id" => $id]);
            }
        }

        $deletedIds = array_diff($existingIds, $updatingIds);

        //delete those records
        AcademicTimetableLecturer::query()
            ->where("academic_timetable_information_id", $timetableInfoId)
            ->whereIn("lecturer_id", $deletedIds)->delete();
    }

    public static function deleteRecords($timetableInfoIds)
    {
        AcademicTimetableLecturer::query()->whereIn("academic_timetable_information_id", $timetableInfoIds)->delete();
    }
}
