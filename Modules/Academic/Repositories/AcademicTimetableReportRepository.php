<?php
namespace Modules\Academic\Repositories;

use App\Helpers\Helper;
use App\Repositories\BaseRepository;

class AcademicTimetableReportRepository extends BaseRepository
{
    public string $reportType = "";
    public array $timetableTypes = [
        ["id" => 1, "name" => "Master", "label" => "info"],
        ["id" => 2, "name" => "Academic", "label" => "info"]
    ];

    /**
     * @return array
     */
    public function getDepBatchScheduleData(): array
    {
        $relations = ["ttInfoLecturers", "ttInfoSpaces", "ttInfoSubgroups", "deliveryMode", "deliveryModeSpecial"];

        $aTSRepo = new AcademicTimetableSubgroupRepository();
        $ttRecords = $aTSRepo->getFilteredData($relations, false);

        $data = [];
        if ($ttRecords) {

            foreach ($ttRecords as $ttRecord) {

                $record = [];
                $record["id"] = $ttRecord->id;
                $record["date"] = $ttRecord->tt_date;
                $record["start_time"] = $ttRecord->start_time;
                $record["end_time"] = $ttRecord->end_time;
                $record["hours"] = Helper::getMinutesDiff($ttRecord->start_time, $ttRecord->end_time);
                $record["hours"] = Helper::convertMinutesToHumanTime($record["hours"]);
                $record["lecturers"] = [];
                $record["spaces"] = [];

                if ($ttRecord->deliveryModeSpecial) {

                    $deliveryModeSpecial = $ttRecord->deliveryModeSpecial;

                    $record["dm_id"] = $deliveryModeSpecial->id;
                    $record["dm_name"] = $deliveryModeSpecial->name;
                } else {

                    if ($ttRecord->deliveryMode) {

                        $deliveryMode = $ttRecord->deliveryMode;

                        $record["dm_id"] = $deliveryMode->id;
                        $record["dm_name"] = $deliveryMode->name;
                    }
                }

                if ($ttRecord->ttInfoLecturers) {

                    foreach ($ttRecord->ttInfoLecturers as $ttInfoLecturer) {

                        if ($ttInfoLecturer->lecturer) {

                            $lecturer = [];
                            $lecturer["id"] = $ttInfoLecturer->lecturer->id;
                            $lecturer["name"] = $ttInfoLecturer->lecturer->name;

                            $record["lecturers"][] = $lecturer;
                        }
                    }
                }

                if ($ttRecord->ttInfoSpaces) {

                    foreach ($ttRecord->ttInfoSpaces as $ttInfoSpace) {

                        if ($ttInfoSpace->space) {

                            $space = [];
                            $space["id"] = $ttInfoSpace->space->id;
                            $space["name"] = $ttInfoSpace->space->name;

                            $record["spaces"][] = $space;
                        }
                    }
                }

                if ($ttRecord->ttInfoSubgroups) {

                    foreach ($ttRecord->ttInfoSubgroups as $ttInfoSubgroup) {

                        if ($ttInfoSubgroup->module) {

                            $module = $ttInfoSubgroup->module;

                            $record["module_id"] = $module->id;
                            $record["module_name"] = $module->name;
                        } elseif ($ttRecord->module) {

                            $record["module_id"] = $ttRecord->module->id;
                            $record["module_name"] = $ttRecord->module->name;
                        } else {

                            $record["module_id"] = "";
                            $record["module_name"] = "";
                        }

                        if ($ttInfoSubgroup->subgroup) {

                            $subgroup = $ttInfoSubgroup->subgroup;

                            $record["subgroup_id"] = $subgroup->id;
                            $record["subgroup_name"] = $subgroup->sg_name;

                            $batch = false;
                            if ($subgroup->batch) {
                                $batch = $subgroup->batch;
                            }

                            if ($subgroup->department) {
                                $dep = $subgroup->department;

                                if (!isset($data[$dep->id])) {

                                    $depRecord = [];
                                    $depRecord["id"] = $dep->id;
                                    $depRecord["name"] = $dep->dept_name;
                                    $depRecord["batches"] = [];

                                    $data[$dep->id] = $depRecord;
                                }

                                if ($batch) {

                                    if (!isset($data[$dep->id]["batches"][$batch->id])) {

                                        $batchRecord = [];
                                        $batchRecord["id"] = $batch->id;
                                        $batchRecord["name"] = $batch->batch_name;
                                        $batchRecord["records"] = [];

                                        $data[$dep->id]["batches"][$batch->id] = $batchRecord;
                                    }

                                    $data[$dep->id]["batches"][$batch->id]["records"][] = $record;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }

    public function getRecordPrepared($record)
    {
        if ($this->reportType === "lecture_schedule") {

            $record["start_time"] = date("h:i A", strtotime($record["start_time"]));
            $record["end_time"] = date("h:i A", strtotime($record["end_time"]));

            if (isset($record["attendance"])) {

                if (isset($record["attendance"]["start_time"])) {

                    $record["attendance"]["start_time"] = date("h:i A", strtotime($record["attendance"]["start_time"]));
                }

                if (isset($record["attendance"]["end_time"])) {

                    $record["attendance"]["end_time"] = date("h:i A", strtotime($record["attendance"]["end_time"]));
                }
            } else {

                $record["attendance"]["start_time"] = "";
                $record["attendance"]["end_time"] = "";
                $record["attendance"]["lecturer_attendance"] = "Pending";
                $record["attendance"]["student_attendance"] = "Pending";
            }
        }

        return parent::getRecordPrepared($record);
    }
}
