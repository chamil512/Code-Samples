<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\AcademicTimetableCriteria;

class AcademicTimetableCriteriaRepository extends BaseRepository
{
    /**
     * @param $timetableId
     * @param $deliveryModeId
     * @return array
     */
    public static function getWeekDays($timetableId, $deliveryModeId): array
    {
        $record = AcademicTimetableCriteria::query()
            ->where("academic_timetable_id", $timetableId)
            ->where("delivery_mode_id", $deliveryModeId)
            ->get()
            ->first();

        $data = [];
        if ($record) {

            $autoGenCriteria = @json_decode($record->timetable_criteria, true);
            $records = $autoGenCriteria["records"] ?? [];

            if (count($records) > 0) {

                foreach ($records as $record) {

                    $data[] = $record["weekDay"];
                }
            }
        }

        return $data;
    }
}
