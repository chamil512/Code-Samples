<?php

namespace Modules\Academic\Repositories;

use App\Helpers\Helper;
use App\Repositories\BaseRepository;
use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Entities\AcademicTimetableCriteria;

class AcademicTimetableAutoGenRepository extends BaseRepository
{
    private AcademicTimetableRepository $ttRepo;
    private int $slotSkip = 60; //number of minutes skip to check for the next available slot if current slot is not available
    private array $possibleSlots = [];
    private array $takenSlots = [];
    private int $defaultTimeSlot = 120; //default time slot in minutes
    private int $deliveryModeId;
    private array $deliveryMode;
    private array $topics;
    private string $currDate;

    public function __construct()
    {
        parent::__construct();

        $this->ttRepo = new AcademicTimetableRepository();
    }

    public function autoGenerateTimetable($model)
    {
        $hasError = false;
        $error = "";

        DB::beginTransaction();
        try {

            if ($model && $model->auto_gen_status === 0) {

                $this->updateAutoGenStatus($model, 1, "Timetable auto generation started");

                $acaCalRepo = new AcademicCalendarRepository();
                $academicCalendar = $acaCalRepo->getAcademicCalendarInfo($model->academic_calendar_id);

                if ($academicCalendar) {

                    $criteriaList = AcademicTimetableCriteria::query()
                        ->with(["deliveryMode"])
                        ->where("academic_timetable_id", $model->id)
                        ->get()
                        ->toArray();

                    if (is_array($criteriaList) && count($criteriaList) > 0) {

                        $this->ttRepo->deleteExistingRecords($model);

                        //get lesson topics
                        $lessonPlanId = $model->syllabus_lesson_plan_id;
                        $this->topics = SyllabusLessonTopicRepository::getRecordsByModuleAndMode($lessonPlanId);

                        $noSingleCriteria = true;
                        foreach ($criteriaList as $criteriaListRecord) {

                            $autoGenCriteria = @json_decode($criteriaListRecord["timetable_criteria"], true);

                            if (isset($autoGenCriteria["records"]) && is_array($autoGenCriteria["records"]) && count($autoGenCriteria["records"]) > 0) {

                                $noSingleCriteria = false;

                                $this->deliveryModeId = intval($criteriaListRecord["delivery_mode_id"]);
                                $this->deliveryMode = $criteriaListRecord["delivery_mode"];
                                $criteriaRecords = $autoGenCriteria["records"];

                                //get week days and prepare autogen criteria by week days
                                $weekDays = [];
                                $weekDayData = [];
                                if (intval($model->batch_availability_type) === 1) {

                                    foreach ($criteriaRecords as $criteria) {
                                        $weekDay = $criteria["weekDay"];

                                        if (is_array($criteria["sessions"]) && count($criteria["sessions"]) > 0 && is_array($criteria["modules"]) && count($criteria["modules"]) > 0) {
                                            $weekDays[] = $weekDay;
                                            $weekDayData[$weekDay] = $criteria;
                                        }
                                    }
                                } else {

                                    foreach ($criteriaRecords as $criteria) {
                                        $weekDay = $criteria["nthDay"];

                                        if (is_array($criteria["sessions"]) && count($criteria["sessions"]) > 0 && is_array($criteria["modules"]) && count($criteria["modules"]) > 0) {

                                            $weekDayData[$weekDay] = $criteria;
                                        }
                                    }
                                }

                                $this->ttRepo->excludeExamDates = true;
                                $timetableDates = $this->ttRepo->getTimetableInfo($model, [], $academicCalendar, $weekDays, false, true);

                                if (count($timetableDates) > 0) {

                                    $records = [];
                                    foreach ($timetableDates as $dateData) {

                                        $date = $dateData["date"];
                                        $week = $dateData["week"];

                                        if (intval($model->batch_availability_type) === 1) {

                                            $weekDay = strtoupper(date("D", strtotime($date)));
                                        } else {

                                            $weekDay = $dateData["nthDay"];
                                        }

                                        $this->currDate = $date;
                                        $criteria = $weekDayData[$weekDay] ?? null;

                                        if ($criteria) {

                                            $this->possibleSlots = [];
                                            $this->preparedDataForSingleDate($criteria);

                                            if (count($this->possibleSlots) > 0) {

                                                $maxCount = 0;
                                                $currMax = null;
                                                foreach ($this->possibleSlots as $possibleSlot) {
                                                    $thisCount = count($possibleSlot);
                                                    if ($thisCount > $maxCount) {
                                                        $maxCount = $thisCount;
                                                        $currMax = $possibleSlot;
                                                    }
                                                }

                                                if ($currMax !== null) {
                                                    $slots = $currMax;

                                                    $record = [];
                                                    $record["date"] = $date;
                                                    $record["slots"] = [];

                                                    foreach ($slots as $slot) {

                                                        $spaceIds = [];
                                                        if ($slot["hasPrefSpaces"]) {

                                                            $prefSpaceIds = $slot["prefSpaceIds"];
                                                            $capacity = $slot["capacity"];
                                                            $availableSpaceIds = $this->ttRepo->getAvailableSpaceIds($model->id, $date, $slot["startTime"], $slot["endTime"], $prefSpaceIds);

                                                            $spaceIds = AcademicSpaceRepository::getCapacityMatchingIds($availableSpaceIds, $capacity);
                                                        }

                                                        $slot["date"] = $date;
                                                        $slot["delivery_mode_id"] = $this->deliveryModeId;
                                                        $slot["spaceIds"] = $spaceIds;
                                                        $slot["week"] = $week;
                                                        $slot["hours"] = Helper::getHourDiff($slot["startTime"], $slot["endTime"]);

                                                        $slot = $this->_getPreparedRecord($slot);

                                                        $record["slots"][] = $slot;
                                                    }

                                                    $records[] = $record;
                                                }
                                            }
                                        }
                                    }

                                    $this->ttRepo->autoUpdate = true;
                                    $this->ttRepo->deliveryModeId = $this->deliveryModeId;
                                    $response = $this->ttRepo->updateTimetable($model, $records, $weekDays);

                                    if ($response["notify"]["status"] !== "success") {

                                        $hasError = true;

                                        if (isset($response["notify"]["notify"]) && count($response["notify"]["notify"]) > 0) {

                                            $error = implode('', $response["notify"]["notify"]);
                                        }
                                    }
                                }
                            }
                        }

                        if ($noSingleCriteria) {

                            $hasError = true;
                            $error = "Timetable auto generation criteria has not been set for this timetable";
                        }
                    } else {
                        $hasError = true;
                        $error = "Timetable generation criteria has not been setup for this timetable.";
                    }
                } else {
                    $hasError = true;
                    $error = "Academic calendar has not been set for this timetable";
                }
            }
        } catch (Exception $exception) {

            $hasError = true;
            $error = $exception->getMessage() . " in " . $exception->getFile() . " @ " . $exception->getLine();
        }

        if ($hasError) {

            DB::rollBack();
            $this->updateAutoGenStatus($model, 4, $error);
        } else {

            $this->updateAutoGenStatus($model, 2, "Timetable generated successfully");
            DB::commit();
        }
    }

    private function _getPreparedRecord($slot): array
    {
        $record = [];
        $record["module"]["id"] = $slot["moduleId"];
        $record["lessonTopic"]["id"] = $slot["topicId"];
        $record["start_time"] = $slot["startTime"];
        $record["end_time"] = $slot["endTime"];
        $record["hours"] = $slot["hours"];
        $record["week"] = $slot["week"];
        $record["payable_status"] = 1;
        $record["slot_type"] = 1;
        $record["lecturerId"] = $slot["lecturerId"];
        $record["spaceIds"] = $slot["spaceIds"];
        $record["subgroupIds"] = $slot["subgroupIds"];

        if ($slot["lecturerId"] != "") {
            $record["lecturers"] = [];
            $record["lecturers"][] = ["id" => $slot["lecturerId"]];
        }

        return $record;
    }

    private function preparedDataForSingleDate($criteria, $tryCount = 1)
    {
        if ($tryCount === 1) {
            $this->takenSlots = [];
        }

        $modules = $criteria["modules"];
        $moduleCount = count($modules);

        if ($moduleCount > 0) {

            $sessions = $criteria["sessions"];

            $slots = [];
            $allModsSet = true; //assume all modules will be available
            foreach ($modules as $module) {

                if (isset($module["selected"]["id"]) && $module["selected"]["id"] == "1") {

                    $moduleId = $module["id"];

                    $modTopics = $this->topics[$moduleId][$this->deliveryModeId] ?? [];
                    if (is_array($modTopics) && count($modTopics) > 0) {

                        $capacity = 0;
                        $subgroupIds = [];

                        $prefSubgroups = $module["subgroups"] ?? [];
                        if (is_array($prefSubgroups) && count($prefSubgroups) > 0) {

                            foreach ($prefSubgroups as $prefSubgroup) {

                                $subgroupIds[] = $prefSubgroup["id"];
                                $capacity += $prefSubgroup["max_students"];
                            }
                        }

                        $prefSpaceIds = [];
                        $hasPrefSpaces = false;
                        $academicSpaces = $module["preferred"]["academicSpaces"];

                        if (is_array($academicSpaces) && count($academicSpaces) > 0) {

                            $hasPrefSpaces = true;
                            foreach ($academicSpaces as $academicSpace) {
                                $prefSpaceIds[] = $academicSpace["id"];
                            }
                        }

                        //get preferred data
                        $validSlots = $this->getTimeSlots($module["preferred"]["timeSlots"]);
                        $maxSlotMinutes = max($validSlots);

                        $restTopics = $modTopics;
                        foreach ($modTopics as $modTopic) {

                            $topicId = $modTopic["id"];
                            $topicLecturerId = $modTopic["lecturer_id"];

                            $topicMinutes = Helper::convertHoursToMinutes($modTopic["hours"]);

                            if ($maxSlotMinutes >= $topicMinutes) {

                                $slot = $this->getAvailableSlot($sessions, $topicMinutes, $topicLecturerId, $subgroupIds);

                                if ($slot) {

                                    //remove first element from the rest array
                                    array_shift($restTopics);

                                    $maxSlotMinutes = $maxSlotMinutes - $topicMinutes;

                                    $slot["topicId"] = $topicId;
                                    $slot["moduleId"] = $moduleId;
                                    $slot["hasPrefSpaces"] = $hasPrefSpaces;
                                    $slot["prefSpaceIds"] = $prefSpaceIds;
                                    $slot["capacity"] = $capacity;
                                    $slot["subgroupIds"] = $subgroupIds;

                                    $slots[] = $slot;
                                } else {
                                    $allModsSet = false;
                                    break;
                                }
                            } else {

                                break;
                            }
                        }

                        $this->topics[$moduleId][$this->deliveryModeId] = $restTopics;
                    }
                }
            }

            if (count($slots) > 0) {
                $this->possibleSlots[] = $slots;
            }

            if (!$allModsSet) {
                if ($moduleCount !== $tryCount) {
                    //take top module and add as the last module
                    $topModule = array_shift($modules);
                    $modules[] = $topModule;

                    $criteria["modules"] = $modules;

                    $tryCount++;

                    $this->preparedDataForSingleDate($criteria, $tryCount);
                }
            }
        }
    }

    private function getAvailableSlot($sessions, $topicMinutes, $topicLecturerId, $subgroupIds)
    {
        $modeType = $this->deliveryMode["type"];
        $lecturerId = false;
        $availableSlot = [];

        //check if matching sessions exist
        foreach ($sessions as $session) {

            $startTime = $session["startTime"];
            $endTime = $session["endTime"];

            $startTS = strtotime($startTime);
            $endTS = strtotime($endTime);

            $slotSeconds = $topicMinutes * 60;
            $slotPeriod = $this->isSlotAvailable($startTS, $slotSeconds, $endTS, $subgroupIds);

            if ($slotPeriod) {

                //check lecturer availability
                $currStartTime = $slotPeriod["startTime"];
                $currEndTime = $slotPeriod["endTime"];

                if ($modeType === "exam") {
                    //lecturers are not required for exams. So set lecturerId as true
                    $lecturerId = true;
                    $availableSlot = $this->getPreparedSlot(null, $currStartTime, $currEndTime);
                } else {
                    $availableLecturerIds = $this->ttRepo->getAvailableLecturerIds(null, $this->currDate, $currStartTime, $currEndTime, [$topicLecturerId]);

                    if (count($availableLecturerIds) > 0) {
                        $lecturerId = $topicLecturerId;

                        $availableSlot = $this->getPreparedSlot($lecturerId, $currStartTime, $currEndTime);
                    }
                }
            }

            if ($lecturerId) {
                break;
            }
        }

        if (!$lecturerId) {
            $availableSlot = false;
        }

        return $availableSlot;
    }

    private function isSlotAvailable($currentStartTS, $slotSeconds, $endTS, $subgroupIds): ?array
    {
        $currentEndTS = $currentStartTS + $slotSeconds;

        $data = null;
        if ($currentEndTS <= $endTS) {
            $currStartTime = date("H:i", $currentStartTS);
            $currEndTime = date("H:i", $currentEndTS);

            if ($this->hasTaken($currStartTime, $currEndTime)) {
                $skipMinutes = $this->slotSkip;
                $skipSeconds = $skipMinutes * 60;
                $currentStartTS += $skipSeconds;

                $data = $this->isSlotAvailable($currentStartTS, $slotSeconds, $endTS, $subgroupIds);
            } else {

                if ($this->hasBookedByBatch($currStartTime, $currEndTime, $subgroupIds)) {
                    $skipMinutes = $this->slotSkip;
                    $skipSeconds = $skipMinutes * 60;
                    $currentStartTS += $skipSeconds;

                    $data = $this->isSlotAvailable($currentStartTS, $slotSeconds, $endTS, $subgroupIds);
                } else {
                    $data = ["startTime" => $currStartTime, "endTime" => $currEndTime];
                }
            }
        }

        return $data;
    }

    private function hasTaken($startTime, $endTime): bool
    {
        $taken = false;
        $takenSlots = $this->takenSlots;
        if (count($takenSlots) > 0) {
            $startTS = strtotime($startTime);
            $endTS = strtotime($endTime);

            foreach ($takenSlots as $takenSlot) {
                $slotStartTs = strtotime($takenSlot["startTime"]);
                $slotEndTs = strtotime($takenSlot["endTime"]);

                if ($slotStartTs <= $startTS && $startTS < $slotEndTs) {
                    $taken = true;
                    break;
                } else if ($slotStartTs < $endTS && $endTS <= $slotEndTs) {
                    $taken = true;
                    break;
                } else if ($startTS <= $slotStartTs && $slotStartTs < $endTS) {
                    $taken = true;
                    break;
                } else if ($startTS < $slotEndTs && $slotEndTs <= $endTS) {
                    $taken = true;
                    break;
                }
            }
        }

        return $taken;
    }

    private function hasBookedByBatch($startTime, $endTime, $subgroupIds): bool
    {
        //check availability
        $ttRepo = new AcademicTimetableRepository();

        return $ttRepo->hasBookedByBatch(null, $this->currDate, $startTime, $endTime, $subgroupIds);
    }

    private function getPreparedSlot($lecturerId, $startTime, $endTime): array
    {
        $slot = [];
        $slot["startTime"] = $startTime;
        $slot["endTime"] = $endTime;

        $this->takenSlots[] = $slot;
        $slot["lecturerId"] = $lecturerId;

        return $slot;
    }

    private function getTimeSlots($timeSlots): array
    {
        $data = [];
        if (count($timeSlots) > 0) {
            foreach ($timeSlots as $timeSlot) {
                $minutes = intval($timeSlot["id"]);
                $data[] = $minutes;
            }
        } else {
            //check if this slot is lower than default slot
            $minutes = $this->defaultTimeSlot;

            $data[] = $minutes;
        }

        return $data;
    }

    private function updateAutoGenStatus($model, $status, $note)
    {
        $model->auto_gen_status = $status;
        $model->auto_gen_note = $note;

        $this->saveModel($model);
    }
}
