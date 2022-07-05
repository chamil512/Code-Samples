<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\BatchAvailabilityDate;
use Modules\Academic\Entities\BatchAvailabilityRestriction;

class BatchAvailabilityRestrictionRepository extends BaseRepository
{
    /**
     * @param $model
     * @return array
     */
    public function getDates($model): array
    {
        $model->load(["dates"]);
        $records = $model->dates->toArray();

        $orderColumn = array_column($records, 'date');
        array_multisort($orderColumn, SORT_ASC, $records);

        $dates = [];
        if (is_array($records) && count($records) > 0) {
            foreach ($records as $record) {

                $date = [];
                $date["id"] = $record["id"];
                $date["date"] = $record["date"];

                $dates[] = $date;
            }
        }

        return $dates;
    }

    /**
     * @param $batchId
     * @param $academicYearId
     * @param $semesterId
     * @return array
     */
    public function getDatesFromBAS($batchId, $academicYearId, $semesterId): array
    {
        $records = BatchAvailabilityDate::query()
            ->select("date")
            ->whereHas("bar", function ($query) use($batchId, $academicYearId, $semesterId){

                $query->where("batch_id", $batchId)
                    ->where("academic_year_id", $academicYearId)
                    ->where("semester_id", $semesterId);
            })
            ->get()
            ->keyBy("date")
            ->toArray();

        return array_keys($records);
    }
}
