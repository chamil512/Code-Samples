<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\LecturerRosterShift;

class LecturerRosterShiftRepository extends BaseRepository
{
    public function update($baseModel)
    {
        $ids = request()->post("shift_id");
        $shiftDates = request()->post("shift_date");
        $hours = request()->post("hours");
        $statuses = request()->post("status");
        $restrictShifts = request()->post("restrict_shift");
        $startTimes = request()->post("start_time");
        $endTimes = request()->post("end_time");

        $currentIds = $this->getRecordIds($baseModel);
        $updatingIds = [];

        if(is_array($ids) && count($ids)>0)
        {
            foreach ($ids as $key => $id) {

                $status = $statuses[$key];
                $startTime = $startTimes[$key];
                $endTime = $endTimes[$key];

                if ($status === "1" && $startTime !== "" && $endTime !== "") {

                    if($id === "" || !in_array($id, $currentIds)) {

                        $record = new LecturerRosterShift();
                        $record->lecturer_roster_id = $baseModel->id;
                        $record->lecturer_id = $baseModel->lecturer_id;
                        $record->shift_date = $shiftDates[$key];
                        $record->start_time = date("H:i", strtotime($startTime));
                        $record->end_time = date("H:i", strtotime($endTime));
                        $record->hours = $hours[$key];
                        $record->restrict_shift = $restrictShifts[$key];
                    } else {

                        $record = LecturerRosterShift::query()->find($id);
                        $record->shift_date = $shiftDates[$key];
                        $record->start_time = date("H:i", strtotime($startTime));
                        $record->end_time = date("H:i", strtotime($endTime));
                        $record->hours = $hours[$key];
                        $record->restrict_shift = $restrictShifts[$key];

                        $updatingIds[] = $id;
                    }

                    $record->save();
                }
            }
        }

        $notUpdatingIds = array_diff($currentIds, $updatingIds);

        if(count($notUpdatingIds)>0)
        {
            $baseModel->rosterShifts()->whereIn("id", $notUpdatingIds)->delete();
        }
    }

    public function getRecordIds($baseModel)
    {
        $records = $baseModel->rosterShifts()->get()->toArray();

        $ids = [];
        if(is_array($records) && count($records)>0)
        {
            foreach ($records as $record)
            {
                $ids[] = $record["id"];
            }
        }

        return $ids;
    }

    private function getSameDatesInOtherRosters($baseModel)
    {
        $rosterId = false;

        if (isset($baseModel->id)) {

            $rosterId = $baseModel->id;
        }

        $query = LecturerRosterShift::query()
            ->select("shift_date")
            ->whereBetween("shift_Date", [$baseModel->roster_from, $baseModel->roster_till])
            ->where("lecturer_id", $baseModel->lecturer_id);

        if ($rosterId) {

            $query->where("lecturer_roster_id", "!=", $baseModel->id);
        }

        $records = $query->get()->keyBy("shift_date")->toArray();

        return array_keys($records);
    }

    /**
     * @param $baseModel
     * @return bool
     */
    public function isValidRoster($baseModel)
    {
        $shiftDates = request()->post("shift_date");

        $reservedDates = $this->getSameDatesInOtherRosters($baseModel);

        $isValid = true;
        foreach ($shiftDates as $shiftDate)
        {
            if (in_array($shiftDate, $reservedDates)) {

                $isValid = false;

                break;
            }
        }

        return $isValid;
    }

    public function updateAttendance($baseModel)
    {
        $ids = request()->post("shift_id");
        $shiftDates = request()->post("shift_date");
        $startTimes = request()->post("actual_start_time");
        $endTimes = request()->post("actual_end_time");
        $actualHours = request()->post("actual_hours");
        //$attendStatuses = request()->post("attend_status");

        $currentIds = $this->getRecordIds($baseModel);

        if(is_array($ids) && count($ids)>0)
        {
            $currTS = time();

            foreach ($ids as $key => $id) {

                if (isset($startTimes[$key]) && isset($endTimes[$key])) {

                    if($id !== "" && in_array($id, $currentIds)) {

                        $startTime = $startTimes[$key];
                        $endTime = $endTimes[$key];
                        $hours = $actualHours[$key];

                        $shiftDate = $shiftDates[$key]. " " . $endTime;
                        $shiftTS = strtotime($shiftDate);

                        if ($startTime !== "" && $endTime !== "") {

                            $startTime = date("H:i", strtotime($startTime));
                            $endTime = date("H:i", strtotime($endTime));
                            $attendStatus = 1;
                        } else {

                            $startTime = "";
                            $endTime = "";
                            $hours = 0;
                            $attendStatus = 0;
                        }

                        if ($shiftTS <= $currTS) {

                            $record = LecturerRosterShift::query()->find($id);
                            $record->actual_start_time = $startTime;
                            $record->actual_end_time = $endTime;
                            $record->actual_hours = $hours;
                            $record->attend_status = $attendStatus;

                            $record->save();
                        }
                    }
                }
            }
        }
    }

    public static function getCalculatedTotal($lecturerId, $fixedAmount, $dateForm, $dateTill)
    {
        $records = LecturerRosterShift::query()
            ->where("lecturer_id", $lecturerId)
            ->whereBetween("shift_date", [$dateForm, $dateTill])
            ->get()->toArray();

        $total = 0;
        if(is_array($records) && count($records)>0) {

            $plannedHours = 0;
            $validHours = 0;
            foreach ($records as $record) {

                $hours = $record["hours"];
                $actualHours = $record["actual_hours"];

                $plannedHours += $hours;

                if ($record["attend_status"] === 1) {

                    if ($record["restrict_shift"] === 1) {

                        $plannedStartTime = strtotime($record["start_time"]);
                        $plannedEndTime = strtotime($record["end_time"]);
                        $actualStartTime = strtotime($record["actual_start_time"]);
                        $actualEndTime = strtotime($record["actual_end_time"]);

                        if ($plannedStartTime > $actualStartTime) {

                            $startTime = $plannedStartTime;
                        } else {

                            $startTime = $actualStartTime;
                        }

                        if ($plannedEndTime > $actualEndTime) {

                            $endTime = $actualEndTime;
                        } else {

                            $endTime = $plannedEndTime;
                        }

                        $validHours += ($endTime-$startTime)/3600;

                    } else {
                        $validHours += $actualHours;
                    }
                }
            }

            $percentage = ($validHours/$plannedHours)*100;
            $total = ($percentage/100) * $fixedAmount;
        }

        return $total;
    }
}
