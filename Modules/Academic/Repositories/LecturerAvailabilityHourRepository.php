<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\Lecturer;
use Modules\Academic\Entities\LecturerAvailabilityHour;
use Modules\Academic\Entities\LecturerAvailabilityTerm;

class LecturerAvailabilityHourRepository extends BaseRepository
{
    public function update($term)
    {
        $ids = request()->post("lah_id");
        $weekDays = request()->post("week_day");
        $timesFrom = request()->post("time_from");
        $timesTill = request()->post("time_till");

        $currentIds = $this->getRecordIds($term);
        $updatingIds = [];

        if (is_array($ids) && count($ids) > 0) {
            $records = [];
            foreach ($ids as $key => $id) {
                $record = [];
                $record["week_day"] = $weekDays[$key];
                $record["time_from"] = date("H:i", strtotime($timesFrom[$key]));
                $record["time_till"] = date("H:i", strtotime($timesTill[$key]));

                if ($id == "" || !in_array($id, $currentIds)) {
                    $records[] = new LecturerAvailabilityHour($record);
                } else {
                    $term->availabilityHours()->where("lah_id", $id)->update($record);

                    $updatingIds[] = $id;
                }
            }

            if (count($records) > 0) {
                $term->availabilityHours()->saveMany($records);
            }
        }

        $notUpdatingIds = array_diff($currentIds, $updatingIds);

        if (count($notUpdatingIds) > 0) {
            $term->availabilityHours()->whereIn("lah_id", $notUpdatingIds)->delete();
        }
    }

    public function getRecordIds($term): array
    {
        $availabilityHours = $term->availabilityHours->toArray();

        $ids = [];
        if (is_array($availabilityHours) && count($availabilityHours) > 0) {
            foreach ($availabilityHours as $record) {
                $ids[] = $record["lah_id"];
            }
        }

        return $ids;
    }

    public function bulkUpdate()
    {
        //get all the lecturers
        $lecturers = Lecturer::withTrashed()->select(["id"])
            ->whereHas("availabilityHours")->get()->toArray();

        if (is_array($lecturers) && count($lecturers) > 0) {

            foreach ($lecturers as $lecturer) {

                $lecturerId = $lecturer["id"];

                $lATModel = new LecturerAvailabilityTerm();
                $lATModel->lecturer_id = $lecturerId;
                $lATModel->date_from = "2020-01-01";
                $lATModel->date_till = "2022-12-31";

                if ($lATModel->save()) {

                    $data = [];
                    $data["lecturer_availability_term_id"] = $lATModel->id;

                    LecturerAvailabilityHour::query()
                        ->where("lecturer_id", $lecturerId)
                        ->update($data);
                }
            }
        }
    }
}
