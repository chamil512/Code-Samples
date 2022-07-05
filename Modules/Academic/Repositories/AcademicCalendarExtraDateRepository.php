<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\AcademicCalendarExtraDate;

class AcademicCalendarExtraDateRepository extends BaseRepository
{
    public function update($bar)
    {
        $ids = request()->post("aced_id");
        $dates = request()->post("date");

        $currentIds = $this->getRecordIds($bar);
        $updatingIds = [];

        if (is_array($ids) && count($ids) > 0) {
            $records = [];
            foreach ($ids as $key => $id) {

                $record = [];
                $record["date"] = $dates[$key];

                if ($id) {
                    $bar->dates()->where("id", $id)->update($record);

                    $updatingIds[] = $id;
                } else {
                    $records[] = new AcademicCalendarExtraDate($record);
                }
            }

            if (count($records) > 0) {
                $bar->dates()->saveMany($records);
            }
        }

        $notUpdatingIds = array_diff($currentIds, $updatingIds);

        if (count($notUpdatingIds) > 0) {
            $bar->dates()->whereIn("id", $notUpdatingIds)->delete();
        }
    }

    public function getRecordIds($bar): array
    {
        $records = $bar->dates->toArray();

        $ids = [];
        if (is_array($records) && count($records) > 0) {
            foreach ($records as $record) {
                $ids[] = $record["id"];
            }
        }

        return $ids;
    }
}
