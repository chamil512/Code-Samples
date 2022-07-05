<?php

namespace Modules\Academic\Repositories;

use App\Helpers\Helper;
use App\Repositories\BaseRepository;
use DateInterval;
use DatePeriod;
use DateTime;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicCalendar;
use Modules\Academic\Entities\AcademicTimetable;
use Modules\Academic\Entities\AcademicTimetableInformation;
use Modules\Academic\Entities\AcademicTimetableLecturer;
use Modules\Academic\Entities\AcademicTimetableSpace;
use Modules\Academic\Entities\AcademicTimetableSubgroup;
use Modules\Academic\Entities\Course;
use Modules\Academic\Entities\Department;
use Modules\Academic\Entities\ExamTimetableSpace;
use Modules\Academic\Entities\Group;
use Modules\Academic\Entities\LecturerAvailabilityTerm;
use Modules\Academic\Entities\ModuleDeliveryMode;
use Modules\Academic\Entities\SpaceAllocation;
use Modules\Academic\Entities\Subgroup;
use Modules\Academic\Entities\SubgroupModule;
use Modules\Academic\Entities\SubgroupStudent;
use Modules\Academic\Entities\SyllabusLessonTopic;
use Modules\Academic\Entities\SyllabusModule;
use Modules\Admin\Services\Permission;
use Modules\Settings\Entities\CalendarHoliday;

class AcademicTimetableRepository extends BaseRepository
{
    public array $statuses = [
        ["id" => "1", "name" => "Enabled", "label" => "success"],
        ["id" => "0", "name" => "Disabled", "label" => "danger"]
    ];

    public array $approvalStatuses = [
        ["id" => "", "name" => "Not Sent for Approval", "label" => "info"],

        ["id" => "0", "name" => "Verification Pending of Batch/Semester Coordinator", "label" => "warning"],
        //["id" => "3", "name" => "Verified & Pending Pre-approval of Academic Head of the Department", "label" => "success"],
        ["id" => "3", "name" => "Verified & Pending Final Approval of Senior Assistant Registrar", "label" => "success"],
        ["id" => "4", "name" => "Verification of Batch/Semester Coordinator Declined", "label" => "danger"],

        //["id" => "5", "name" => "Pre-approved & Pending Pre-approval of Senior Assistant Registrar", "label" => "success"],
        /*["id" => "5", "name" => "Pre-approved & Pending Final approval of Senior Assistant Registrar", "label" => "success"],
        ["id" => "6", "name" => "Pre-approval of Academic Head of the Department Declined", "label" => "danger"],*/

        //["id" => "7", "name" => "Pre-approved by Senior Assistant Registrar & Pending Pre-approval of Registrar", "label" => "success"],
        /*["id" => "7", "name" => "Pre-approved by Senior Assistant Registrar & Pending Final Approval of Head/Department of Finance", "label" => "success"],
        ["id" => "8", "name" => "Pre-approval of Senior Assistant Registrar Declined", "label" => "danger"],*/

        /*["id" => "9", "name" => "Pre-approved by Registrar & Pending Pre-approval of Vice Chancellor", "label" => "success"],
        ["id" => "10", "name" => "Pre-approval of Registrar Declined", "label" => "danger"],

        ["id" => "11", "name" => "Pre-approved by Vice Chancellor & Pending Final Approval of Head/Department of Finance", "label" => "success"],
        ["id" => "12", "name" => "Pre-approval of Vice Chancellor Declined", "label" => "danger"],*/

        ["id" => "1", "name" => "Approved", "label" => "success"],
        ["id" => "2", "name" => "Declined", "label" => "danger"],
    ];

    public array $autoGenStatuses = [
        ["id" => "0", "name" => "Pending", "label" => "warning"],
        ["id" => "1", "name" => "In Progress", "label" => "primary"],
        ["id" => "2", "name" => "Completed", "label" => "success"],
        ["id" => "3", "name" => "No Action", "label" => "info"],
        ["id" => "4", "name" => "Error occurred", "danger"]
    ];

    private array $updatingIds = [];
    private array $sgModules = [];
    public bool $prepareLecturerHours = false;
    public array $ttLecturers = [];
    public array $ttLecturerHours = [];
    private ?bool $stdAttendanceUrlPerm = null;
    private bool $formUpdate = false;
    public bool $autoUpdate = false;
    public bool $excludeExamDates = false;
    public ?int $deliveryModeId = null;

    /*
     * Approval properties and methods starts
     */
    public string $approvalField = "approval_status";
    public $approvalDefaultStatus = "0";
    protected array $approvalSteps = [
        [
            "step" => "verification",
            "approvedStatus" => 3,
            "declinedStatus" => 4,
            "route" => "/academic/academic_timetable/verification",
            "permissionRoutes" => [],
        ],
        /*[
            "step" => "pre_approval_hod",
            "approvedStatus" => 5,
            "declinedStatus" => 6,
            "route" => "/academic/academic_timetable/pre_approval_hod",
            "permissionRoutes" => [],
        ],
        [
            "step" => "pre_approval_sar",
            "approvedStatus" => 7,
            "declinedStatus" => 8,
            "route" => "/academic/academic_timetable/pre_approval_sar",
            "permissionRoutes" => [],
        ],
        [
            "step" => "pre_approval_registrar",
            "approvedStatus" => 9,
            "declinedStatus" => 10,
            "route" => "/academic/academic_timetable/pre_approval_registrar",
            "permissionRoutes" => [],
        ],
        [
            "step" => "pre_approval_vc",
            "approvedStatus" => 11,
            "declinedStatus" => 12,
            "route" => "/academic/academic_timetable/pre_approval_vc",
            "permissionRoutes" => [],
        ],*/
        [
            "step" => "approval",
            "approvedStatus" => 1,
            "declinedStatus" => 2,
            "route" => "/academic/academic_timetable/approval",
            "permissionRoutes" => [],
        ]
    ];

    /**
     * @param $model
     * @param $step
     * @return string
     */
    protected function getApprovalStepTitle($model, $step): string
    {
        switch ($step) {
            case "verification" :
                $text = $model->name . " verification of batch/semester coordinator.";
                break;

            case "pre_approval_hod" :
                $text = $model->name . " pre-approval of academic head of the department.";
                break;

            /*case "pre_approval_sar" :
                $text = $model->name . " pre-approval of senior assistant registrar.";
                break;

            case "pre_approval_registrar" :
                $text = $model->name . " pre-approval of registrar.";
                break;

            case "pre_approval_vc" :
                $text = $model->name . " pre-approval of vice chancellor.";
                break;*/

            /*case "approval" :
                $text = $model->name . " final approval of Head/Department of Finance.";
                break;*/

            case "approval" :
                $text = $model->name . " final approval of senior assistant registrar.";
                break;

            default:
                $text = "";
                break;
        }

        return $text;
    }

    /**
     * @param $model
     * @param $step
     * @return string|Application|Factory|View
     */
    protected function getApprovalStepDescription($model, $step): View
    {
        $record = $model->toArray();
        $timetableUrl = URL::to("/academic/academic_timetable/view/" . $model->id);

        return view("academic::academic_timetable.approvals." . $step, compact('record', 'timetableUrl'));
    }

    /**
     * @param $model
     * @param $step
     * @param $previousStatus
     */
    protected function onApproved($model, $step, $previousStatus)
    {
        if ($step === "approval") {
            //check if slave table already exists
            $slave = AcademicTimetable::query()->where(["master_timetable_id" => $model->id])->first();

            if (!$slave) {
                DB::beginTransaction();
                try {
                    $slave = $model->replicate();
                    $slave->master_timetable_id = $model->id;
                    $slave->type = 2;
                    $slave->status = 1;

                    //save new model
                    $slave->push();

                    $criteriaList = $model->criteria()->get();

                    if (count($criteriaList) > 0) {
                        foreach ($criteriaList as $criteria) {
                            $criteriaModel = $criteria->replicate();

                            unset($criteriaModel->id);
                            $criteriaModel->academic_timetable_id = $slave->id;

                            $criteriaModel->push();
                        }
                    }

                    $infoItems = $model->information()->with(["ttInfoLecturers", "ttInfoSubgroups", "ttInfoSpaces"])->get();

                    if (count($infoItems) > 0) {
                        foreach ($infoItems as $infoItem) {
                            $infoModel = $infoItem->replicate();

                            unset($infoModel->academic_timetable_information_id);
                            $infoModel->academic_timetable_id = $slave->id;

                            $infoModel->push();

                            $ttInfoLecturers = $infoItem->ttInfoLecturers()->get();

                            if (count($ttInfoLecturers) > 0) {
                                foreach ($ttInfoLecturers as $infoLec) {
                                    $lecModel = $infoLec->replicate();

                                    unset($lecModel->academic_timetable_lecturer_id);
                                    $lecModel->academic_timetable_information_id = $infoModel->id;

                                    $lecModel->push();
                                }
                            }

                            $ttInfoSubgroups = $infoItem->ttInfoSubgroups()->get();

                            if (count($ttInfoSubgroups) > 0) {
                                foreach ($ttInfoSubgroups as $infoSub) {
                                    $subModel = $infoSub->replicate();

                                    unset($subModel->academic_timetable_subgroup_id);
                                    $subModel->academic_timetable_information_id = $infoModel->id;

                                    $subModel->push();
                                }
                            }

                            $ttInfoSpaces = $infoItem->ttInfoSpaces()->get();

                            if (count($ttInfoSpaces) > 0) {
                                foreach ($ttInfoSpaces as $infoSpace) {
                                    $spaceModel = $infoSpace->replicate();

                                    unset($spaceModel->academic_timetable_space_id);
                                    $spaceModel->academic_timetable_information_id = $infoModel->id;

                                    $spaceModel->push();
                                }
                            }
                        }
                    }

                    $success = true;
                } catch (Exception $error) {
                    $success = false;
                }

                if ($success) {
                    DB::commit();

                    $model->status = 1;
                    $model->save();

                    AcademicTimetableInformationRepository::sendTimetableLectureSchedule($slave);
                } else {
                    DB::rollBack();
                }
            }
        }
    }

    /**
     * @param $model
     * @param $step
     * @param $previousStatus
     */
    protected function onDeclined($model, $step, $previousStatus)
    {
        if ($step == "approval") {
            //check if slave table already exists
            $slave = AcademicTimetable::query()->where(["master_timetable_id" => $model->id])->first();

            if ($slave) {
                DB::beginTransaction();
                try {
                    $this->deleteExistingRecords($slave);
                    DB::commit();
                } catch (Exception $error) {
                    DB::rollBack();
                }
            }
        }
    }

    /**
     * @param $model
     * @param $step
     * @return array
     */
    protected function getApprovalStepUsers($model, $step): array
    {
        $this->isSpecificUserRequired = false;

        $data = [];
        if ($step === "verification") {

            $this->isSpecificUserRequired = true;
            $data = BatchCoordinatorRepository::getBatchCoordinatorIds($model->batch_id);

        } elseif ($step === "pre_approval_hod") {

            $this->isSpecificUserRequired = true;
            //get department id
            $course = $model->course;
            $deptId = $course->dept_id;

            //get head of the department's id
            $data = DepartmentHeadRepository::getHODAdmins($deptId);
        }

        return $data;
    }
    /*
     * Approval properties and methods ends
     */

    /**
     * @param $timetableInfoId
     * @param string $date
     * @param string $startTime
     * @param string $endTime
     * @param array $lecturerIds
     * @return array
     */
    public function getAvailableLecturerIds($timetableInfoId, string $date, string $startTime, string $endTime, array $lecturerIds): array
    {
        $startTime = date("H:i", strtotime($startTime));
        $endTime = date("H:i", strtotime($endTime));

        //get reserved space ids for academic operations
        $query = AcademicTimetableLecturer::query()
            ->select("lecturer_id")
            ->whereHas("timetableInfo", function ($query) use ($timetableInfoId, $date, $startTime, $endTime) {

                $query->where("academic_timetable_information_id", "!=", $timetableInfoId)
                    ->where("tt_date", $date)
                    ->where("slot_status", 1)
                    ->whereHas("module")
                    ->whereHas("timetable", function ($query) {

                        $query->where(function ($query) {

                            $query->where("type", 1)->whereDoesntHave("academic");
                        })->orWhere(function ($query) {

                            $query->where("type", 2);
                        });
                    })
                    ->where(function ($query) use ($startTime, $endTime) {

                        $query->where(function ($query) use ($startTime, $endTime) {

                            $query->where(DB::raw("start_time"), "<=", DB::raw("'" . $startTime . "'"))
                                ->where(DB::raw("'" . $startTime . "'"), "<", DB::raw("end_time"));
                        })
                            ->orWhere(function ($query) use ($startTime, $endTime) {

                                $query->where(DB::raw("start_time"), "<", DB::raw("'" . $endTime . "'"))
                                    ->where(DB::raw("'" . $endTime . "'"), "<=", DB::raw("end_time"));
                            })
                            ->orWhere(function ($query) use ($startTime, $endTime) {

                                $query->where(DB::raw("'" . $startTime . "'"), "<=", DB::raw("start_time"))
                                    ->where(DB::raw("start_time"), "<", DB::raw("'" . $endTime . "'"));
                            })
                            ->orWhere(function ($query) use ($startTime, $endTime) {

                                $query->where(DB::raw("'" . $startTime . "'"), "<", DB::raw("end_time"))
                                    ->where(DB::raw("end_time"), "<=", DB::raw("'" . $endTime . "'"));
                            });
                    });
            })
            ->whereIn("lecturer_id", $lecturerIds)
            ->groupBy("lecturer_id");

        $records = $query->get()
            ->keyBy("lecturer_id")
            ->toArray();

        $bookedLecturerIds = array_keys($records);
        $availableLecturerIds = array_diff($lecturerIds, $bookedLecturerIds);

        return $this->getHourlyAvailableLectures($availableLecturerIds, $date, $startTime, $endTime);
    }

    /**
     * @param array $lecturerIds
     * @param string $date
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    function getHourlyAvailableLectures(array $lecturerIds, string $date, string $startTime, string $endTime): array
    {
        $data = [];
        if (count($lecturerIds) > 0) {
            $weekDay = "";
            if ($date != "") {
                $weekDay = strtoupper(date("D", strtotime($date)));
            }

            $startTime = date("H:i", strtotime($startTime) + 1);
            $endTime = date("H:i", strtotime($endTime) - 1);

            $records = LecturerAvailabilityTerm::query()
                ->whereIn("lecturer_id", $lecturerIds)
                ->where("date_from", "<=", $date)
                ->where("date_till", ">=", $date)
                ->whereHas("availabilityHours", function ($query) use ($weekDay, $startTime, $endTime) {

                    $query->where("week_day", $weekDay)
                        ->where("time_from", "<=", $startTime)
                        ->where("time_till", ">=", $endTime);
                })
                ->get()
                ->keyBy("lecturer_id")
                ->toArray();

            $data = array_keys($records);
        }

        return $data;
    }

    /**
     * @param $timetableInfoId
     * @param string $date
     * @param string $startTime
     * @param string $endTime
     * @param array $spaceIds
     * @return array
     */
    public function getAvailableSpaceIds($timetableInfoId, string $date, string $startTime, string $endTime, array $spaceIds): array
    {
        if (count($spaceIds) > 0) {

            $fromTime = date("H:i", strtotime($startTime));
            $tillTime = date("H:i", strtotime($endTime));

            //get reserved space ids for academic operations
            $query = AcademicTimetableSpace::query()
                ->select("space_id")
                ->whereHas("timetableInfo", function ($query) use ($timetableInfoId, $date, $fromTime, $tillTime) {

                    $query->where("academic_timetable_information_id", "!=", $timetableInfoId)
                        ->where("tt_date", $date)
                        ->where("slot_status", 1)
                        ->whereHas("module")
                        ->whereHas("timetable", function ($query) {

                            $query->where(function ($query) {

                                $query->where("type", 1)->whereDoesntHave("academic");
                            })->orWhere(function ($query) {

                                $query->where("type", 2);
                            });
                        })
                        ->where(function ($query) use ($fromTime, $tillTime) {

                            $query->where(function ($query) use ($fromTime, $tillTime) {

                                $query->where(DB::raw("start_time"), "<=", DB::raw("'" . $fromTime . "'"))
                                    ->where(DB::raw("'" . $fromTime . "'"), "<", DB::raw("end_time"));
                            })
                                ->orWhere(function ($query) use ($fromTime, $tillTime) {

                                    $query->where(DB::raw("start_time"), "<", DB::raw("'" . $tillTime . "'"))
                                        ->where(DB::raw("'" . $tillTime . "'"), "<=", DB::raw("end_time"));
                                })
                                ->orWhere(function ($query) use ($fromTime, $tillTime) {

                                    $query->where(DB::raw("'" . $fromTime . "'"), "<=", DB::raw("start_time"))
                                        ->where(DB::raw("start_time"), "<", DB::raw("'" . $tillTime . "'"));
                                })
                                ->orWhere(function ($query) use ($fromTime, $tillTime) {

                                    $query->where(DB::raw("'" . $fromTime . "'"), "<", DB::raw("end_time"))
                                        ->where(DB::raw("end_time"), "<=", DB::raw("'" . $tillTime . "'"));
                                });
                        });
                })
                ->whereIn("space_id", $spaceIds);

            $records = $query->get()
                ->keyBy("space_id")
                ->toArray();

            $bookedAcademicSpaceIds = array_keys($records);

            //get reserved space ids for exams
            $query = ExamTimetableSpace::query()
                ->select("space_id")
                ->whereHas("timetableInfo", function ($query) use ($timetableInfoId, $date, $fromTime, $tillTime) {

                    $query->where("academic_timetable_information_id", "!=", $timetableInfoId)
                        ->where("tt_date", $date)
                        ->where("slot_status", 1)
                        ->whereHas("module")
                        ->whereHas("timetable", function ($query) {

                            $query->where(function ($query) {

                                $query->where("type", 1)->whereDoesntHave("academic");
                            })->orWhere(function ($query) {

                                $query->where("type", 2);
                            });
                        })
                        ->where(function ($query) use ($fromTime, $tillTime) {

                            $query->where(function ($query) use ($fromTime, $tillTime) {

                                $query->where(DB::raw("start_time"), "<=", DB::raw("'" . $fromTime . "'"))
                                    ->where(DB::raw("'" . $fromTime . "'"), "<", DB::raw("end_time"));
                            })
                                ->orWhere(function ($query) use ($fromTime, $tillTime) {

                                    $query->where(DB::raw("start_time"), "<", DB::raw("'" . $tillTime . "'"))
                                        ->where(DB::raw("'" . $tillTime . "'"), "<=", DB::raw("end_time"));
                                })
                                ->orWhere(function ($query) use ($fromTime, $tillTime) {

                                    $query->where(DB::raw("'" . $fromTime . "'"), "<=", DB::raw("start_time"))
                                        ->where(DB::raw("start_time"), "<", DB::raw("'" . $tillTime . "'"));
                                })
                                ->orWhere(function ($query) use ($fromTime, $tillTime) {

                                    $query->where(DB::raw("'" . $fromTime . "'"), "<", DB::raw("end_time"))
                                        ->where(DB::raw("end_time"), "<=", DB::raw("'" . $tillTime . "'"));
                                });

                        });
                })
                ->whereIn("space_id", $spaceIds);

            $records = $query->get()
                ->keyBy("space_id")
                ->toArray();

            $bookedExamSpaceIds = array_keys($records);
            $bookedSpaceIds = array_merge($bookedAcademicSpaceIds, $bookedExamSpaceIds);
            $availableSpaceIds = array_diff($spaceIds, $bookedSpaceIds);

            return $this->getAvailableSpaces($availableSpaceIds, $date, $startTime, $endTime);
        } else {
            return [];
        }
    }

    /**
     * @param array $spaceIds
     * @param string $date
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    function getAvailableSpaces(array $spaceIds, string $date, string $startTime, string $endTime): array
    {
        $data = [];
        if (count($spaceIds) > 0) {
            $fromTime = date("Y-m-d H:i:s", strtotime($date . " " . $startTime) + 1);
            $tillTime = date("Y-m-d H:i:s", strtotime($date . " " . $endTime) - 1);

            $records = SpaceAllocation::query()
                ->select("spaces_id")
                ->whereIn("spaces_id", $spaceIds)
                ->whereBetween(DB::raw("UNIX_TIMESTAMP(CONCAT(start_date, ' ', start_time))"), [DB::raw("UNIX_TIMESTAMP(STR_TO_DATE('" . $fromTime . "', '%Y-%m-%d %H:%i:%s'))"), DB::raw("UNIX_TIMESTAMP(STR_TO_DATE('" . $tillTime . "', '%Y-%m-%d %H:%i:%s'))")])
                ->orWhereBetween(DB::raw("UNIX_TIMESTAMP(CONCAT(end_date, ' ', end_time))"), [DB::raw("UNIX_TIMESTAMP(STR_TO_DATE('" . $fromTime . "', '%Y-%m-%d %H:%i:%s'))"), DB::raw("UNIX_TIMESTAMP(STR_TO_DATE('" . $tillTime . "', '%Y-%m-%d %H:%i:%s'))")])
                ->orWhereBetween(DB::raw("UNIX_TIMESTAMP(STR_TO_DATE('" . $fromTime . "', '%Y-%m-%d %H:%i:%s'))"), [DB::raw("UNIX_TIMESTAMP(CONCAT(start_date, ' ', start_time))"), DB::raw("UNIX_TIMESTAMP(CONCAT(end_date, ' ', end_time))")])
                ->orWhereBetween(DB::raw("UNIX_TIMESTAMP(STR_TO_DATE('" . $tillTime . "', '%Y-%m-%d %H:%i:%s'))"), [DB::raw("UNIX_TIMESTAMP(CONCAT(start_date, ' ', start_time))"), DB::raw("UNIX_TIMESTAMP(CONCAT(end_date, ' ', end_time))")])
                ->get()
                ->keyBy("spaces_id")
                ->toArray();

            $bookedSpaceIds = array_keys($records);

            $availableSpaceIds = array_diff($spaceIds, $bookedSpaceIds);

            if (count($availableSpaceIds) > 0) {
                foreach ($availableSpaceIds as $availableSpaceId) {
                    $data[] = $availableSpaceId;
                }
            }
        }

        return $data;
    }

    /**
     * @param $deliveryModeId
     * @param mixed $moduleId
     * @return array
     */
    public function getMatchingSubgroupIds($deliveryModeId, $moduleId = false): array
    {
        $records = DB::table("subgroups", "sg")
            ->select("sg.id", DB::raw("sg.sg_name AS name"), DB::raw("sg.max_students AS capacity"))
            ->join("subgroupes_modules AS sg_mods", "sg.id", "=", "sg_mods.subgroup_id")
            ->whereNull("sg.deleted_at")
            ->whereNull("sg_mods.deleted_at")
            ->where("sg.dm_id", $deliveryModeId);

        if ($moduleId) {

            //get subject group ids which are having this id or similar id
            $similarIds = SimilarCourseModuleRepository::getSimilarIds($moduleId);
            $similarIds[] = $moduleId;

            $records->whereIn("sg_mods.module_id", $similarIds);
        }

        $records = $records->get()->keyBy("id")->toArray();

        return array_keys($records);
    }

    /**
     * @param $timetableInfoId
     * @param string $date
     * @param string $startTime
     * @param string $endTime
     * @param array $subgroupIds
     * @return array
     */
    public function getAvailableSubgroupIds($timetableInfoId, string $date, string $startTime, string $endTime, array $subgroupIds): array
    {
        $data = [];
        if (count($subgroupIds) > 0) {

            //going to make sure related subgroups also haven't booked
            $relatedSubgroups = SubgroupRepository::getRelatedSubgroups($subgroupIds);

            $allSubgroupIds = $subgroupIds;
            if (count($relatedSubgroups) > 0) {

                foreach ($relatedSubgroups as $relatedIds) {

                    foreach ($relatedIds as $relatedId) {

                        if (!in_array($relatedId, $allSubgroupIds)) {

                            $allSubgroupIds[] = $relatedId;
                        }
                    }
                }
            }

            $startTime = date("H:i", strtotime($startTime));
            $endTime = date("H:i", strtotime($endTime));

            $records = AcademicTimetableSubgroup::query()
                ->select("subgroup_id")
                ->whereHas("timetableInfo", function ($query) use ($timetableInfoId, $date, $startTime, $endTime) {

                    $query->where("academic_timetable_information_id", "!=", $timetableInfoId)
                        ->where("tt_date", $date)
                        ->where("slot_status", 1)
                        ->whereHas("module")
                        ->whereHas("timetable", function ($query) {

                            $query->where(function ($query) {

                                $query->where("type", 1)->whereDoesntHave("academic");
                            })->orWhere(function ($query) {

                                $query->where("type", 2);
                            });
                        })
                        ->where(function ($query) use ($startTime, $endTime) {

                            $query->where(function ($query) use ($startTime, $endTime) {

                                $query->where(DB::raw("start_time"), "<=", DB::raw("'" . $startTime . "'"))
                                    ->where(DB::raw("'" . $startTime . "'"), "<", DB::raw("end_time"));
                            })
                                ->orWhere(function ($query) use ($startTime, $endTime) {

                                    $query->where(DB::raw("start_time"), "<", DB::raw("'" . $endTime . "'"))
                                        ->where(DB::raw("'" . $endTime . "'"), "<=", DB::raw("end_time"));
                                })
                                ->orWhere(function ($query) use ($startTime, $endTime) {

                                    $query->where(DB::raw("'" . $startTime . "'"), "<=", DB::raw("start_time"))
                                        ->where(DB::raw("start_time"), "<", DB::raw("'" . $endTime . "'"));
                                })
                                ->orWhere(function ($query) use ($startTime, $endTime) {

                                    $query->where(DB::raw("'" . $startTime . "'"), "<", DB::raw("end_time"))
                                        ->where(DB::raw("end_time"), "<=", DB::raw("'" . $endTime . "'"));
                                });
                        });
                })
                ->whereIn("subgroup_id", $allSubgroupIds);

            $records = $records->get()
                ->keyBy("subgroup_id")
                ->toArray();

            $bookedSubgroupIds = [];
            if (is_array($records) && count($records) > 0) {

                $bookedSubgroupIds = array_keys($records);
            }

            $noneRelatedBookedIds = [];
            if (count($bookedSubgroupIds) > 0) {

                $availableSubgroupIds = array_diff($subgroupIds, $bookedSubgroupIds);
                $noneRelatedBookedIds = $this->getNoneRelatedBookedIds($relatedSubgroups, $bookedSubgroupIds);
            } else {

                $availableSubgroupIds = $subgroupIds;
            }

            if (count($availableSubgroupIds) > 0) {
                foreach ($availableSubgroupIds as $availableSubgroupId) {

                    if (!in_array($availableSubgroupId, $noneRelatedBookedIds)) {

                        $data[] = $availableSubgroupId;
                    }
                }
            }
        }

        return $data;
    }

    public function getNoneRelatedBookedIds($relatedSubgroups, $bookedSubgroupIds): array
    {
        $data = [];
        if (is_array($relatedSubgroups) && count($relatedSubgroups) > 0) {

            foreach ($relatedSubgroups as $subgroupId => $relatedIds) {

                $hasAnyBooked = false;
                foreach ($relatedIds as $relatedId) {

                    if (in_array($relatedId, $bookedSubgroupIds)) {

                        $hasAnyBooked = true;
                        break;
                    }
                }

                if ($hasAnyBooked) {

                    $data[] = $subgroupId;
                }
            }
        }

        return $data;
    }

    public function hasBookedByBatch($timetableInfoId, $date, $startTime, $endTime, $subgroupIds): bool
    {
        //get subgroup ids list
        $availableSubGroupIds = $this->getAvailableSubgroupIds($timetableInfoId, $date, $startTime, $endTime, $subgroupIds);

        if (count($subgroupIds) === count($availableSubGroupIds)) {
            //this means none of the subgroups of this main group has been booked
            return false;
        }

        //this means at least one subgroup of this main group has been booked within this time period
        return true;
    }

    /**
     * @param $model
     * @param array $records
     * @param array $calendar
     * @param array $weekDays
     * @param bool $currentRecords
     * @param bool $onlyValidDates
     * @return array
     */
    public function getTimetableInfo($model, array $records, array $calendar = [], array $weekDays = [], bool $currentRecords = true, bool $onlyValidDates = false): array
    {
        //get delivery mode
        $deliveryMode = ModuleDeliveryMode::query()->find($this->deliveryModeId);

        $modeType = "default";
        if ($deliveryMode) {

            $modeType = $deliveryMode->type ?? "default";
        }

        $acaStartDate = $calendar["academic_start_date"];
        $acaEndDate = $calendar["academic_end_date"];

        $midVacationDates = Helper::getDatesBetweenTwoDates($calendar["mid_vac_start_date"], $calendar["mid_vac_end_date"]);
        $vacationDates = Helper::getDatesBetweenTwoDates($calendar["vac_start_date"], $calendar["vac_end_date"]);
        $examDates = Helper::getDatesBetweenTwoDates($calendar["exam_start_date"], $calendar["exam_end_date"]);
        $caExamDates = Helper::getDatesBetweenTwoDates($calendar["ca_exam_start_date"], $calendar["ca_exam_end_date"]);
        $emgVacationDates = Helper::getDatesBetweenTwoDates($calendar["emg_vac_start_date"], $calendar["emg_vac_end_date"]);

        $aCDates = [];
        $academicCalendar = AcademicCalendar::query()->find($model->academic_calendar_id);
        if ($academicCalendar) {

            $aCRepo = new AcademicCalendarRepository();
            $aCDates = $aCRepo->getDates($academicCalendar, true);
        }

        //get holidays from calendar
        $holidays = $this->getHolidays();

        $nonSelectedWeekDays = [];
        if (intval($model->batch_availability_type) === 1) {

            if ($modeType === "exam") {
                $dates = array_unique(array_merge($examDates, $caExamDates), SORT_REGULAR);
            } else {
                $dates = Helper::getDatesBetweenTwoDates($acaStartDate, $acaEndDate);

                if ($this->excludeExamDates) {

                    $dates = array_diff($dates, $examDates);
                }
            }

            if (empty($weekDays)) {

                $weekDays = AcademicTimetableCriteriaRepository::getWeekDays($model->id, $this->deliveryModeId);
            }

            if (count($dates) > 0) {

                foreach ($dates as $date) {
                    $weekDay = strtoupper(date("D", strtotime($date)));

                    if (!in_array($weekDay, $weekDays)) {
                        $nonSelectedWeekDays[] = $date;
                    }
                }
            }

            //prepare unavailable dates
            $unavailableDates = array_merge($holidays, $midVacationDates, $vacationDates, $emgVacationDates, $nonSelectedWeekDays);
            $availableDates = array_diff($dates, $unavailableDates);
        } else {

            //get specific dates
            $bADRepo = new BatchAvailabilityRestrictionRepository();
            $dates = $bADRepo->getDatesFromBAS($model->batch_id, $model->academic_year_id, $model->semester_id);

            $availableDates = array_diff($dates, $holidays);
        }

        //add academic timetable additional/extra dates
        $dates = array_merge($dates, $aCDates);
        $availableDates = array_merge($availableDates, $aCDates);

        //get current data of this timetable
        $recordsByDate = [];
        $recordDates = [];

        if ($currentRecords) {
            if (count($records)) {
                foreach ($records as $record) {
                    if (!in_array($record["tt_date"], $recordDates)) {

                        $recordDates[] = $record["tt_date"];
                    }
                    $recordsByDate[$record["tt_date"]][] = $this->getCleanRecord($record);
                }
            }
        }

        $datesForWeeks = array_unique(array_merge($dates, $recordDates), SORT_REGULAR);
        $weekNumbers = $this->getWeekNumbers($datesForWeeks);
        $foundDates = [];
        $data = [];
        if (count($dates)) {

            $prevWeekNo = null;
            $nthDay = 0;
            foreach ($dates as $date) {

                $weekNo = $weekNumbers[$date];
                $weekDay = $this->getWeekDay($date);

                if (in_array($date, $availableDates)) {
                    $foundDates[] = $date;

                    $period = "Academic";
                    if ($modeType == "exam") {
                        $period = "Examination";
                    }

                    if ($prevWeekNo === null || $prevWeekNo !== $weekNo) {

                        $prevWeekNo = $weekNo;
                        $nthDay = 0;
                    }

                    $nthDay++;

                    $record = [
                        "date" => $date,
                        "period" => $period,
                        "weekDay" => $weekDay,
                        "week" => $weekNo,
                        "nthDay" => $nthDay,
                        "slots" => [],
                        "valid" => "yes",
                    ];

                    if ($currentRecords) {

                        if (isset($recordsByDate[$date])) {
                            if (!$onlyValidDates) {

                                $record["slots"] = $recordsByDate[$date];
                            }
                        }
                    }

                    $data[] = $record;
                } else {
                    if ($currentRecords) {
                        if (!$onlyValidDates) {
                            if (isset($recordsByDate[$date])) {
                                //check if already have added dates data before changing academic calendar and holiday data
                                $foundDates[] = $date;

                                $period = "";
                                if (in_array($date, $midVacationDates)) {
                                    $period = "midVacation";
                                } else if (in_array($date, $vacationDates)) {
                                    $period = "vacation";
                                } else if (in_array($date, $emgVacationDates)) {
                                    $period = "emgVacation";
                                } else if (in_array($date, $holidays)) {
                                    $period = "holiday";
                                } else if (in_array($date, $nonSelectedWeekDays)) {
                                    $period = "nonWeekDay";
                                }

                                $record = [
                                    "date" => $date,
                                    "period" => $period,
                                    "weekDay" => $weekDay,
                                    "week" => $weekNo,
                                    "nthDay" => $nthDay,
                                    "slots" => $recordsByDate[$date],
                                    "valid" => "no",
                                ];

                                $data[] = $record;
                            }
                        }
                    }
                }
            }
        }

        if (!$onlyValidDates) {
            $restDates = array_diff($recordDates, $foundDates);
            if (count($restDates) > 0) {
                foreach ($restDates as $date) {
                    $period = "none";
                    if ($date < $acaStartDate) {
                        $period = "Before Academic";
                    } else if ($date > $acaEndDate) {
                        $period = "After Academic";
                    }

                    $weekNo = $weekNumbers[$date];
                    $weekDay = $this->getWeekDay($date);

                    $data[] = ["date" => $date, "period" => $period, "slots" => $recordsByDate[$date], "valid" => "no", "weekDay" => $weekDay, "week" => $weekNo];
                }
            }
        }

        $orderColumn = array_column($data, "date");
        array_multisort($orderColumn, SORT_ASC, $data);

        return $data;
    }

    function getWeekDay($date)
    {
        return date("l", strtotime($date));
    }

    public function getCleanRecord($record): array
    {
        $timetable = $record["timetable"];

        $ttData = [];
        $ttData["id"] = $timetable["id"];
        $ttData["name"] = $timetable["name"];
        $ttData["type"] = $timetable["type"];

        $data = [];
        $data["id"] = $record["id"];
        $data["timetable"] = $ttData;
        $data["academic_timetable_id"] = $record["academic_timetable_id"];
        $data["date"] = $record["tt_date"];
        $data["tt_date"] = $record["tt_date"];
        $data["start_time"] = date("h:i A", strtotime($record["start_time"]));
        $data["end_time"] = date("h:i A", strtotime($record["end_time"]));
        $data["hours"] = $record["hours"];
        $data["slot_status"] = $record["slot_status"];
        $data["slot_type"] = $record["slot_type"];
        $data["approval_status"] = $record["approval_status"];
        $data["payable_status"] = $record["payable_status"];

        $module = [];
        if (isset($record["module"]["id"])) {

            $module["id"] = $record["module"]["id"];
            $module["name"] = $record["module"]["name"];
            $module["module_name"] = $record["module"]["module_name"];
            $module["color"] = $record["module"]["module_color_code"];
            $module["text_color"] = $this->getTextColor($record["module"]["module_color_code"]);
        }

        $lessonTopic = [];
        if (isset($record["lesson_topic"]["id"])) {

            $lessonTopic["id"] = $record["lesson_topic"]["id"];
            $lessonTopic["name"] = $record["lesson_topic"]["name"];
            $lessonTopic["hours"] = $record["lesson_topic"]["hours"];
            $lessonTopic["name_with_hours"] = $record["lesson_topic"]["name_with_hours"];
        }

        $deliveryMode = [];
        if (isset($record["delivery_mode"]["id"])) {

            $deliveryMode["id"] = $record["delivery_mode"]["id"];
            $deliveryMode["name"] = $record["delivery_mode"]["name"];
        }

        $deliveryModeSpecial = [];
        if (isset($record["delivery_mode_special"]["id"])) {
            $deliveryModeSpecial["id"] = $record["delivery_mode_special"]["id"];
            $deliveryModeSpecial["name"] = $record["delivery_mode_special"]["name"];
        }

        $examType = [];
        if (isset($record["exam_type"]["id"])) {
            $examType["id"] = $record["exam_type"]["id"];
            $examType["name"] = $record["exam_type"]["name"];
        }

        $examCategory = [];
        if (isset($record["exam_category"]["id"])) {
            $examCategory["id"] = $record["exam_category"]["id"];
            $examCategory["name"] = $record["exam_category"]["name"];
        }

        $lecturers = [];
        if (isset($record["lecturers"]) && count($record["lecturers"]) > 0) {
            foreach ($record["lecturers"] as $lec) {

                $lecturer = [];
                $lecturer["id"] = $lec["id"];
                $lecturer["name"] = $lec["name"];
                $lecturers[] = $lecturer;

                if ($this->prepareLecturerHours) {

                    $this->_setLecturerHours($lecturer, $module["id"], $record["hours"]);
                }
            }
        }

        $subgroups = [];
        if (isset($record["subgroups"]) && count($record["subgroups"]) > 0) {
            foreach ($record["subgroups"] as $sg) {

                $subgroup = [];
                $subgroup["id"] = $sg["id"];
                $subgroup["name"] = $sg["name"];
                $subgroup["max_students"] = $sg["max_students"];
                $subgroups[] = $subgroup;
            }
        }

        $spaces = [];
        if (isset($record["spaces"]) && count($record["spaces"]) > 0) {
            foreach ($record["spaces"] as $sp) {

                $space = [];
                $space["id"] = $sp["id"];
                $space["name"] = $sp["name"];
                $space["space_name"] = $sp["common_name"];
                $space["capacity"] = $sp["std_count"];
                $spaces[] = $space;
            }
        }

        $attendance = [];
        $attendance["student"] = 0;
        $attendance["lecturer"] = 0;
        if (isset($record["attendance"])) {
            if (isset($record["attendance"]["student"]) && $record["attendance"]["student"] === 1) {
                $attendance["student"] = 1;
            }

            if (isset($record["attendance"]["lecturer"]) && $record["attendance"]["lecturer"] === 1) {
                $attendance["lecturer"] = 1;
            }
        }

        $department = [];
        if (isset($record["department"]["id"])) {
            $department["id"] = $record["department"]["id"];
            $department["name"] = $record["department"]["name"];
        }

        $course = [];
        if (isset($record["course"]["id"])) {
            $course["id"] = $record["course"]["id"];
            $course["name"] = $record["course"]["name"];
        }

        $batch = [];
        if (isset($record["batch"]["id"])) {
            $batch["id"] = $record["batch"]["id"];
            $batch["name"] = $record["batch"]["name"];
        }

        $data["module"] = $module;
        $data["lessonTopic"] = $lessonTopic;
        $data["deliveryMode"] = $deliveryMode;
        $data["deliveryModeSpecial"] = $deliveryModeSpecial;
        $data["examType"] = $examType;
        $data["examCategory"] = $examCategory;
        $data["lecturers"] = $lecturers;
        $data["subgroups"] = $subgroups;
        $data["spaces"] = $spaces;
        $data["attendance"] = $attendance;
        $data["department"] = $department;
        $data["course"] = $course;
        $data["batch"] = $batch;

        if ($this->stdAttendanceUrlPerm === null) {

            $this->stdAttendanceUrlPerm = Permission::haveActionPermission("/academic/academic_timetable/access-student-attendance-url");
        }

        $data["std_portal_url"] = "";
        if ($timetable["type"] !== 1 && $this->stdAttendanceUrlPerm) {

            $data["std_portal_url"] = env('STUDENT_PORTAL_URL') . "/attendance/verify/" . base64_encode($data["id"]);
        }

        return $data;
    }

    private function _setLecturerHours($lecturer, $moduleId, $hours)
    {
        $lecturerId = $lecturer["id"];
        if (!isset($this->ttLecturerHours[$lecturerId])) {

            $this->ttLecturers[] = $lecturer;
        }

        if (!isset($this->ttLecturerHours[$lecturerId][$moduleId])) {

            $this->ttLecturerHours[$lecturerId][$moduleId] = 0;
        }

        $this->ttLecturerHours[$lecturerId][$moduleId] += floatval($hours);
    }

    /**
     * @param $color
     * @return string
     */
    function getTextColor($color): string
    {
        $color = str_replace('#', '', $color);

        $red = hexdec(substr($color, 0, 2));
        $green = hexdec(substr($color, 2, 2));
        $blue = hexdec(substr($color, 4, 2));

        $color = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        if ($color > 130) {

            $color = "#000000";
        } else {

            $color = "#FFFFFF";
        }

        return $color;
    }

    /**
     * @return array
     */
    function getHolidays(): array
    {
        //get only the holidays which does not allow to conduct degree programs
        $results = CalendarHoliday::query()
            ->select("holiday_date")
            ->where("holiday_status", 1)
            ->where("allow_conduct_programmes", 0)
            ->get()
            ->keyBy("holiday_date")->toArray();

        return array_keys($results);
    }

    public function updateTimetable($model, $timetableData, $weekDays = []): array
    {
        DB::beginTransaction();

        $error = "";
        try {
            $currentIds = $this->_getCurrentRecordIds($model->id);

            $this->updatingIds = [];
            $this->formUpdate = true;
            if (is_array($timetableData) && count($timetableData) > 0) {

                //prepare subgroup modules array to avoid multiple db queries for same subgroup and module id
                $this->sgModules = [];
                foreach ($timetableData as $ttData) {
                    if (isset($ttData["date"]) && isset($ttData["slots"]) && is_array($ttData["slots"]) && count($ttData["slots"]) > 0) {
                        foreach ($ttData["slots"] as $slot) {

                            $record = new AcademicTimetableInformation();
                            $this->updateSlotInfo($record, $model, $slot, $ttData["date"]);
                        }
                    }
                }
            }

            $deletingIds = array_diff($currentIds, $this->updatingIds);
            if (count($deletingIds) > 0) {

                if ($model->type === 1) {

                    AcademicTimetableInformation::destroy($deletingIds);
                }
            }
            $success = true;
        } catch (Exception $exception) {
            $error = $exception->getMessage() . " in " . $exception->getFile() . " @ " . $exception->getLine();
            $success = false;
        }

        if ($success) {
            DB::commit();

            $acaCalRepo = new AcademicCalendarRepository();
            $academicCalendar = $acaCalRepo->getAcademicCalendarInfo($model->academic_calendar_id);

            $notify["status"] = "success";
            $notify["notify"][] = "Successfully saved the details.";

            $records = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic",
                "deliveryMode", "deliveryModeSpecial", "examType",
                "examCategory", "lecturers", "subgroups", "spaces", "attendance"])
                ->where("academic_timetable_id", $model->id)
                ->where("delivery_mode_id", $this->deliveryModeId)
                ->where(function ($query) {

                    //active
                    $query->where("slot_status", 1)
                        ->orWhere(function ($query) {

                            //or pending reschedule, revision, relief
                            $query->whereIn("slot_type", [3, 4, 5])
                                ->whereIn("approval_status", [0, 3, 5, 7, 9, 11]);
                        });
                })
                ->get()->toArray();

            $response["data"] = $this->getTimetableInfo($model, $records, $academicCalendar, $weekDays);
        } else {
            DB::rollBack();

            $notify["status"] = "failed";
            $notify["notify"][] = "Error occurred while saving timetable data.";
            $notify["notify"][] = $error;
        }

        $response["notify"] = $notify;

        return $response;
    }

    public function updateSlotInfo($record, $model, $slot, $date = "")
    {
        if (!empty($slot["start_time"]) && !empty($slot["end_time"]) &&
            (isset($slot["examType"]["id"]) || (isset($slot["lecturers"]) && count($slot["lecturers"]) > 0))) {

            if (isset($slot["id"]) && $slot["id"] !== "") {
                $record = AcademicTimetableInformation::query()->find($slot["id"]);
            }

            if ($date === "") {

                if (isset($slot["date"])) {
                    $date = $slot["date"];
                } elseif (isset($slot["tt_date"])) {
                    $date = $slot["tt_date"];
                }
            }

            $record->tt_date = $date;
            $record->start_time = date("H:i", strtotime($slot["start_time"]));
            $record->end_time = date("H:i", strtotime($slot["end_time"]));
            $record->hours = Helper::getHourDiff($slot["start_time"], $slot["end_time"]);

            if (isset($slot["slot_type_remarks"]) && $slot["slot_type_remarks"] !== "") {
                $record->slot_type_remarks = $slot["slot_type_remarks"];
            }

            if (isset($slot["payable_status"]) && isset($slot["payable_status"]["id"])) {
                $record->payable_status = $slot["payable_status"]["id"];
            }

            if (isset($slot["week"])) {
                $record->week = $slot["week"];
            }

            $moduleId = $slot["module"]["id"];
            $record->module_id = $moduleId;
            $record->lesson_topic_id = $slot["lessonTopic"]["id"] ?? null;
            $record->lesson_topic_id = $record->lesson_topic_id === "" ? null : $record->lesson_topic_id;
            $record->delivery_mode_id = $this->deliveryModeId;

            $record->delivery_mode_id_special = null;
            if (isset($slot["deliveryModeSpecial"]) && isset($slot["deliveryModeSpecial"]["id"]) && $slot["deliveryModeSpecial"]["id"] != "") {
                $record->delivery_mode_id_special = $slot["deliveryModeSpecial"]["id"];
            }

            $record->exam_type_id = null;
            if (isset($slot["examType"]) && isset($slot["examType"]["id"]) && $slot["examType"]["id"] != "") {
                $record->exam_type_id = $slot["examType"]["id"];
            }

            $record->exam_category_id = null;
            if (isset($slot["examCategory"]) && isset($slot["examCategory"]["id"]) && $slot["examCategory"]["id"] != "") {
                $record->exam_category_id = $slot["examCategory"]["id"];
            }

            $lecturerIds = [];
            if (isset($slot["lecturerId"])) {

                $lecturerIds[] = $slot["lecturerId"];
            } elseif (isset($slot["lecturers"]) && count($slot["lecturers"]) > 0) {

                foreach ($slot["lecturers"] as $lecturer) {
                    $lecturerIds[] = $lecturer["id"];
                }
            }

            $spaceIds = [];
            if (isset($slot["spaceIds"])) {

                $spaceIds = $slot["spaceIds"];
            } elseif (isset($slot["spaces"]) && count($slot["spaces"]) > 0) {

                foreach ($slot["spaces"] as $space) {

                    $spaceIds[] = $space["id"];
                }
            }

            $subgroupIds = [];
            if (isset($slot["subgroupIds"])) {

                $subgroupIds = $slot["subgroupIds"];
            } elseif (isset($slot["subgroups"]) && count($slot["subgroups"]) > 0) {

                foreach ($slot["subgroups"] as $subgroup) {

                    $subgroupIds[] = $subgroup["id"];
                }
            }

            if (isset($slot["id"]) && !empty($slot["id"])) {
                $this->updatingIds[] = $slot["id"];

                if ($record->save()) {
                    AcademicTimetableLecturerRepository::updateRecords($slot["id"], $lecturerIds);
                    $this->sgModules = AcademicTimetableSubgroupRepository::updateRecords($slot["id"], $subgroupIds, $moduleId, $this->sgModules);
                    AcademicTimetableSpaceRepository::updateRecords($slot["id"], $spaceIds);
                }
            } else {

                //only the master timetable slots are created or reschedule/relief/revision or academic timetable remaining slots
                if (!$this->formUpdate || $this->autoUpdate ||
                    ($this->formUpdate && (isset($slot["slot_type"]["id"]) && intval($slot["slot_type"]["id"]) === 1))) {

                    $record = $model->information()->save($record);

                    if ($record) {
                        AcademicTimetableLecturerRepository::addRecords($record->id, $lecturerIds);
                        $this->sgModules = AcademicTimetableSubgroupRepository::addRecords($record->id, $subgroupIds, $moduleId, $this->sgModules);
                        AcademicTimetableSpaceRepository::addRecords($record->id, $spaceIds);
                    }
                }
            }
        }

        return $record;
    }

    private function _getCurrentRecordIds($timetableId): array
    {
        $records = AcademicTimetableInformation::query()
            ->where("academic_timetable_id", $timetableId)
            ->where("delivery_mode_id", $this->deliveryModeId)
            ->select("academic_timetable_information_id")
            ->get()
            ->keyBy("academic_timetable_information_id")
            ->toArray();

        return array_keys($records);
    }

    public function getTimetableBaseInfo($model, $deliveryModeId): array
    {
        $data = [];

        $syllabusId = $model->syllabus_id;
        $academicYearId = $model->academic_year_id;
        $semesterId = $model->semester_id;

        //get syllabus modules
        $syllabusModules = SyllabusModule::with(["module", "deliveryModes"])
            ->where("syllabus_id", $syllabusId)
            ->whereHas("deliveryModes", function ($query) use ($deliveryModeId) {

                $query->where("delivery_mode_id", $deliveryModeId);
            })
            ->whereHas("module", function ($query) use ($academicYearId, $semesterId) {

                $query->where("academic_year_id", $academicYearId);
                $query->where("semester_id", $semesterId);
            })->get();

        $modules = [];
        $moduleIds = [];
        if (count($syllabusModules) > 0) {

            foreach ($syllabusModules as $sm) {

                $moduleModel = $sm->module;
                $moduleId = $moduleModel->id;

                $moduleIds[] = $moduleId;

                $sMDModes = $sm->deliveryModes;

                $hours = 0;
                foreach ($sMDModes as $sMDM) {

                    if ($sMDM->delivery_mode_id === $deliveryModeId) {

                        $hours = $sMDM["hours"];
                        break;
                    }
                }

                $mod = $moduleModel->toArray();

                $module = [];
                $module["id"] = $mod["id"];
                $module["name"] = $mod["name"];
                $module["code"] = $mod["module_code"];
                $module["hours"] = $hours;

                $modules[] = $module;
            }
        }

        $lessonTopics = SyllabusLessonTopic::query()
            ->where("syllabus_lesson_plan_id", $model->syllabus_lesson_plan_id)
            ->whereIn("module_id", $moduleIds)
            ->with(["lecturer"])
            ->get()
            ->toArray();

        $moduleLecturers = [];
        if (is_array($lessonTopics) && count($lessonTopics) > 0) {
            foreach ($lessonTopics as $lt) {

                if (!isset($moduleLecturers[$lt["module_id"]])) {

                    $moduleLecturers[$lt["module_id"]] = [];
                }

                if (isset($lt["lecturer"]["id"])) {

                    if (!isset($moduleLecturers[$lt["module_id"]][$lt["lecturer_id"]])) {

                        $lecturer = [];
                        $lecturer["id"] = $lt["lecturer"]["id"];
                        $lecturer["name"] = $lt["lecturer"]["name"];
                        $lecturer["hours"] = $lt["hours"];
                    } else {

                        $lecturer = $moduleLecturers[$lt["module_id"]][$lt["lecturer_id"]];
                        $lecturer["hours"] += $lt["hours"];
                    }

                    $moduleLecturers[$lt["module_id"]][$lt["lecturer_id"]] = $lecturer;
                }
            }
        }

        //get syllabus modules
        $data["batchAvailabilityType"] = intval($model->batch_availability_type);
        $data["modules"] = $modules;
        $data["moduleIds"] = $moduleIds;
        $data["moduleLecturers"] = $moduleLecturers;
        $data["academicSpaces"] = AcademicSpaceRepository::getAcademicSpaces();

        return $data;
    }

    /**
     * @param array $records
     * @return array
     */
    public function getTimetable(array $records): array
    {
        $recordsByDate = [];
        $minStartTime = "08:00 AM";
        $maxEndTime = "06:00 PM";
        if (count($records) > 0) {
            foreach ($records as $record) {
                $record = $this->getCleanRecord($record);

                if ($minStartTime == "") {
                    $minStartTime = $record["start_time"];
                } else {
                    if (strtotime($minStartTime) > strtotime($record["start_time"])) {
                        $minStartTime = $record["start_time"];
                    }
                }

                if ($maxEndTime == "") {
                    $maxEndTime = $record["end_time"];
                } else {
                    if (strtotime($maxEndTime) < strtotime($record["end_time"])) {
                        $maxEndTime = $record["end_time"];
                    }
                }

                $record["starTs"] = strtotime($record["start_time"]);

                $recordsByDate[$record["date"]][] = $record;
            }
        }

        $records = [];
        if (count($recordsByDate) > 0) {
            $dates = array_keys($recordsByDate);
            $weekNumbers = $this->getWeekNumbers($dates);

            foreach ($recordsByDate as $date => $slots) {
                $slotOrderColumn = array_column($slots, "starTs");
                array_multisort($slotOrderColumn, SORT_ASC, $slots);

                $record = [];
                $record["date"] = $date;
                $record["week"] = $weekNumbers[$date];
                $record["weekDay"] = $this->getWeekDay($date);
                $record["slots"] = $slots;

                $records[] = $record;
            }
        }

        $orderColumn = array_column($records, "date");
        array_multisort($orderColumn, SORT_ASC, $records);

        $data = [];
        $data["records"] = $records;
        $data["minStartTime"] = $minStartTime;
        $data["maxEndTime"] = $maxEndTime;

        return $data;
    }

    /**
     * @param $dates
     * @return array
     */
    private function getWeekNumbers($dates): array
    {
        $data = [];
        if (is_array($dates) && count($dates) > 0) {

            $minDate = min($dates);
            $minDateTS = strtotime($minDate);
            $baseYear = date('Y', $minDateTS);
            $baseWeek = date('W', $minDateTS);
            $baseWeek = $baseWeek - 1;

            $maxWeeks = 0;
            $currYear = $baseYear;

            sort($dates);

            if (is_array($dates) && count($dates) > 0) {

                foreach ($dates as $date) {

                    $currTS = strtotime($date);

                    $year = date('Y', $currTS);
                    $month = date('m', $currTS);
                    $weekNo = date('W', $currTS);

                    if ($month == 1 && $weekNo > 50) {

                        //rare situation
                        $weekNo = 1;
                    }

                    if ($year > $currYear) {

                        $maxWeeks += date("W", strtotime($currYear . "-12-31"));
                        $currYear = $year;
                    }

                    $weekNo = $weekNo - $baseWeek + $maxWeeks;
                    if ($weekNo < 10) {
                        $weekNo = "0" . $weekNo;
                    }

                    $data[$date] = $weekNo;
                }
            }
        }

        return $data;
    }

    public function isMasterTimetable($model): bool
    {
        if ($model->type === 1 && $model->master_timetable_id === 0) {
            return true;
        }

        return false;
    }

    public function hasAcademicTimetable($id)
    {
        $model = AcademicTimetable::query()->where("type", 2)->where("master_timetable_id", $id)->first();

        if ($model) {
            return $model;
        }

        return false;
    }

    public function beforeRestore($model, $allowed): bool
    {
        $query = AcademicTimetable::query();
        $query->where("academic_calendar_id", $model->academic_calendar_id);
        $query->where("syllabus_id", $model->syllabus_id);
        $query->where("master_timetable_id", $model->master_timetable_id);

        $acaTTModel = $query->first();

        if ($acaTTModel) {
            $allowed = false;

            $this->setErrors("This timetable restoration is not allowed.");
            $this->setErrors("Timetable already exists for this academic calendar and syllabus.");
        }

        return parent::beforeRestore($model, $allowed);
    }

    public function deleteExistingRecords($model)
    {
        //deleting ids list
        $query = $model->information()->setEagerLoads([])->select("academic_timetable_information_id");

        $results = $query->get()->keyBy("id")->toArray();
        $deletingIds = array_keys($results);

        AcademicTimetableLecturerRepository::deleteRecords($deletingIds);
        AcademicTimetableSubgroupRepository::deleteRecords($deletingIds);
        AcademicTimetableSpaceRepository::deleteRecords($deletingIds);

        $model->information()->delete();
    }

    public function getStudentSubGroupIds($studentId, $groupIds = [], $subgroupIds = []): array
    {
        $query = SubgroupStudent::query()
            ->select(DB::raw("DISTINCT sg_id"))
            ->where("std_id", $studentId);

        if (is_array($subgroupIds) && count($subgroupIds) > 0) {

            $query->whereIn("sg_id", $subgroupIds);
        } elseif (is_array($groupIds) && count($groupIds) > 0) {

            //get subgroups ids where having syllabus ids of these course ids having
            $sgIds = Subgroup::query()
                ->select("id")
                ->whereIn("main_gid", $groupIds)
                ->get()
                ->keyBy("id")
                ->toArray();

            $sgIds = array_keys($sgIds);

            $query->whereIn("sg_id", $sgIds);
        }

        $results = $query->get()->keyBy("sg_id")->toArray();

        return array_keys($results);
    }

    /**
     * @return array
     */
    public function getFilteredSubgroupIds(): array
    {
        $request = request();

        $facultyId = $request->post("faculty_id");
        $deptId = $request->post("dept_id");
        $courseId = $request->post("course_id");
        $groupId = $request->post("group_id");
        $deliveryModeId = $request->post("delivery_mode_id");
        $subgroupIds = $request->post("subgroup_id");
        $studentId = $request->post("student_id");
        $dateFrom = $request->post("date_from");
        $dateTill = $request->post("date_till");

        $dmSgIds = [];
        if ($deliveryModeId !== null) {

            $dmSgIds = Subgroup::query()
                ->select("id")
                ->whereIn("dm_id", $deliveryModeId)
                ->get()
                ->keyBy("id")
                ->toArray();

            $dmSgIds = array_keys($dmSgIds);
        }

        $data = [];
        if ($studentId !== null) {

            $data = $this->getStudentSubGroupIds($studentId, $groupId);
        } else if (is_array($subgroupIds) && count($subgroupIds) > 0) {

            if (count($dmSgIds) > 0) {

                foreach ($dmSgIds as $sgId) {

                    if (in_array($sgId, $subgroupIds)) {

                        $data[] = $sgId;
                    }
                }
            } else {
                $data = $subgroupIds;
            }
        } else {

            $groupIds = [];
            if ($groupId === null) {

                $courseIds = [];
                if ($courseId === null) {

                    $deptIds = [];
                    if ($deptId === null) {

                        if ($facultyId !== null) {

                            $deptIds = Department::query()
                                ->select("dept_id")
                                ->where("faculty_id", $facultyId)
                                ->where("dept_status", 1)
                                ->get()
                                ->keyBy("dept_id")
                                ->toArray();

                            $deptIds = array_keys($deptIds);
                        }
                    } else {

                        $deptIds[] = $deptId;
                    }

                    if (count($deptIds) > 0) {

                        $courseIds = Course::query()
                            ->select("course_id")
                            ->whereIn("dept_id", $deptIds)
                            ->where("course_status", 1)
                            ->get()
                            ->keyBy("course_id")
                            ->toArray();

                        $courseIds = array_keys($courseIds);
                    }
                } else {

                    $courseIds[] = $courseId;
                }

                if (count($courseIds) > 0) {

                    $groupIds = Group::query()
                        ->select("GroupID")
                        ->whereIn("CourseID", $courseIds)
                        ->get()
                        ->keyBy("GroupID")
                        ->toArray();

                    $groupIds = array_keys($groupIds);
                }
            } else {

                $groupIds = $groupId;
            }

            if (count($groupIds) > 0) {

                $sgIds = Subgroup::query()
                    ->select("id")
                    ->whereIn("main_gid", $groupIds)
                    ->get()
                    ->keyBy("id")
                    ->toArray();

                $sgIds = array_keys($sgIds);

                if (count($dmSgIds) > 0) {

                    foreach ($dmSgIds as $sgId) {

                        if (in_array($sgId, $sgIds)) {

                            $data[] = $sgId;
                        }
                    }
                } else {

                    $data = $sgIds;
                }
            } else {

                if ($dateFrom !== null && $dateTill !== null) {

                    //check for date range and pick the subgroup ids according to that date range
                    $records = AcademicTimetableSubgroup::query()->select("subgroup_id")
                        ->whereHas("timetableInfo", function ($query) use ($dateFrom, $dateTill) {

                            $query->whereDate("tt_date", ">=", $dateFrom);
                            $query->whereDate("tt_date", "<=", $dateTill);
                        })->get()->keyBy("subgroup_id")->toArray();

                    $subgroupIds = array_keys($records);

                    if (count($dmSgIds) > 0) {

                        foreach ($dmSgIds as $sgId) {

                            if (in_array($sgId, $subgroupIds)) {

                                $data[] = $sgId;
                            }
                        }
                    } else {
                        $data = $subgroupIds;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param $model
     * @param null $deliveryModeId
     * @return array
     */
    public function getPickedBatchModuleHours($model, $deliveryModeId = null): array
    {
        $timetableIdsNot = [];
        $timetableIdsNot[] = $model->id;

        if ($model->type === 2) {

            //exclude master timetable slots
            $timetableIdsNot[] = $model->master_timetable_id;
        } else {

            //exclude academic timetable slots if exists
            if ($model->academic) {

                $timetableIdsNot[] = $model->academic->id;
            }
        }

        $query = AcademicTimetableSubgroup::with(["timetableInfo"])
            ->select(["academic_timetable_information_id", "module_id", "subgroup_id"])
            ->whereHas("subgroup", function ($query) use ($model, $deliveryModeId) {

                $query->where("batch_id", $model->batch_id);
                $query->where("year", $model->academic_year_id);
                $query->where("semester", $model->semester_id);

                if ($deliveryModeId) {

                    $query->where("dm_id", $deliveryModeId);
                }
            })
            ->whereHas("timetableInfo", function ($query) use ($timetableIdsNot) {

                $query->where("slot_status", 1)
                    ->whereNotIn("academic_timetable_id", $timetableIdsNot)
                    ->whereHas("module")
                    ->whereHas("timetable", function ($query) {

                        $query->where(function ($query) {

                            $query->where("type", 1)->whereDoesntHave("academic");
                        })->orWhere(function ($query) {

                            $query->where("type", 2);
                        });
                    });
            });

        $results = $query->get()->toArray();

        $data = [];
        if (count($results) > 0) {

            foreach ($results as $result) {

                if (isset($result["timetable_info"]["hours"])) {

                    if (!isset($data[$result["module_id"]][$result["subgroup_id"]])) {

                        $data[$result["module_id"]][$result["subgroup_id"]] = 0;
                    }

                    $data[$result["module_id"]][$result["subgroup_id"]] += $result["timetable_info"]["hours"];
                }
            }
        }

        return $data;
    }

    /**
     * @param array $relations
     * @return array
     */
    public function getFilteredTimetableData(array $relations = []): array
    {
        $request = request();

        $dateFrom = $request->post("date_from");
        $dateTill = $request->post("date_till");
        $examOnly = $request->post("exam_only");
        $studentId = $request->post("student_id");
        $lecturerId = $request->post("lecturer_id");
        $spaceIds = $request->post("space_id");
        $deliveryModeIds = $request->post("delivery_mode_id");
        $upcomingOnly = $request->post("upcoming_only");

        $subgroupIds = $this->getFilteredSubgroupIds();

        if (count($subgroupIds) > 0 || $lecturerId) {

            $query = AcademicTimetableInformation::query()
                ->select(DB::raw("academic_timetable_information.*"))
                ->where("slot_status", 1);

            if (is_array($relations) && count($relations) > 0) {

                $query->with($relations);
            } else {

                $query->with(["timetable", "module", "deliveryMode", "deliveryModeSpecial", "examType",
                    "examCategory", "lecturers", "subgroups", "spaces", "attendance"]);
            }

            if (count($subgroupIds) > 0) {

                $query->whereHas("ttInfoSubgroups", function ($query) use ($subgroupIds) {

                    $query->whereIn("subgroup_id", $subgroupIds);
                });
            }

            if ($lecturerId) {

                $query->whereHas("ttInfoLecturers", function ($query) use ($lecturerId) {

                    $query->where("lecturer_id", $lecturerId);
                });
            }

            if ($spaceIds) {

                $query->whereHas("ttInfoSpaces", function ($query) use ($spaceIds) {

                    $query->whereIn("space_id", $spaceIds);
                });
            }

            $query->whereHas("module");
            $query->whereHas("timetable", function ($query) use ($request) {

                $timetableType = $request->post("timetable_type");
                $academicYearId = $request->post("academic_year_id");
                $semesterId = $request->post("semester_id");

                $query->where("auto_gen_status", "!=", 1);
                $query->whereNull("deleted_at");

                if ($timetableType === "academic") {
                    $query->where("status", 1);
                    $query->where("type", "=", 2);
                    $query->where("master_timetable_id", "!=", 0);
                } elseif ($timetableType === "master") {

                    $query->where("type", "=", 1);
                    $query->where("master_timetable_id", "=", 0);
                } else {
                    $query->where(function ($query) {

                        $query->where(function ($query) {

                            $query->where("type", 1)->where("master_timetable_id", 0)->where(function ($query) {

                                $query->where("approval_status", "!=", 1)->orWhereNull("approval_status");
                            });
                        })->orWhere(function ($query) {

                            $query->where("type", 2)->where("master_timetable_id", "!=", 0);
                        });
                    });
                }

                if ($academicYearId) {

                    $query->whereIn("academic_year_id", $academicYearId);
                }

                if ($semesterId) {

                    $query->whereIn("semester_id", $semesterId);
                }
            });

            if ($examOnly === "yes") {

                $query->where("exam_type_id", "!=", 0);
            }

            if (is_array($deliveryModeIds) && count($deliveryModeIds) > 0) {
                $query->where(function ($query) use ($deliveryModeIds) {

                    $query->where(function ($query) use ($deliveryModeIds) {

                        $query->whereIn("delivery_mode_id", $deliveryModeIds)->whereNull("delivery_mode_id_special");
                    });

                    $query->orWhereIn("delivery_mode_id_special", $deliveryModeIds);
                });
            }

            if ($dateFrom !== null && $dateTill !== null) {

                $query->whereDate("tt_date", ">=", $dateFrom);
                $query->whereDate("tt_date", "<=", $dateTill);
            } else {

                if ($upcomingOnly === "yes") {

                    $today = date("Y-m-d", time());
                    $query->whereDate("tt_date", ">=", $today);
                }
            }

            $records = $query->groupBy("academic_timetable_information_id")->get()->toArray();

            if (count($records) > 0) {

                $notify["status"] = "success";
                $response["notify"] = $notify;
                $response["data"]["timetable"] = $this->getTimetable($records);
            } else {
                $notify["status"] = "failed";
                if ($studentId !== null) {

                    $notify["notify"][] = "Timetable not found for the student";

                } else {

                    $notify["notify"][] = "Timetable information not found for the selected criteria";

                }
                $response["notify"] = $notify;
            }
        } else {
            $notify["status"] = "failed";
            if ($studentId !== null) {

                $notify["notify"][] = "Timetable not found for the student";
            } else {

                $notify["notify"][] = "Timetable information not found for the selected criteria";
            }
            $response["notify"] = $notify;
        }

        return $response;
    }

    /**
     * @param array $relations
     * @param array $options In this array it can replace post variables with custom variables
     * @return array
     */
    public function getFilteredData(array $relations = [], array $options = []): array
    {
        $request = request();

        $groupIds = $request->post("group_id");
        $subgroupIds = $request->post("subgroup_id");
        $deliveryModeIds = $request->post("delivery_mode_id");
        $spaceIds = $request->post("space_id");
        $dateFrom = $request->post("date_from");
        $dateTill = $request->post("date_till");
        $examOnly = $request->post("exam_only");
        $upcomingOnly = $request->post("upcoming_only");
        $directOnly = $request->post("direct_only"); //direct slots only, picked slots not considered
        $withCancelled = $request->post("with_cancelled");
        $cancelledOnly = $request->post("cancelled_only");

        $studentId = $request->post("student_id");
        $lecturerIds = $request->post("lecturer_id");
        $staffType = $request->post("staff_type");

        extract($options);

        $query = AcademicTimetableInformation::query();

        if (is_array($relations) && count($relations) > 0) {

            $query->with($relations);
        } else {

            $query->with(["timetable", "module", "deliveryMode", "deliveryModeSpecial", "examType",
                "examCategory", "lecturers", "subgroups", "spaces", "attendance"]);
        }

        if ($cancelledOnly === "yes") {

            $query->where("slot_type", 2)->where("slot_status", 0);

        } elseif ($withCancelled === "yes") {

            $query->where(function ($query) {

                $query->where("slot_status", 1)
                    ->orWhere(function ($query) {

                        $query->where("slot_type", 2)->where("slot_status", 0);
                    });
            });
        } else {

            $query->where("slot_status", 1);
        }

        if ($directOnly !== "yes") {

            if ($studentId) {

                $subgroupIds = $this->getStudentSubGroupIds($studentId, $groupIds, $subgroupIds);
            }

            if (count($subgroupIds) > 0) {

                $query->whereHas("ttInfoSubgroups", function ($query) use ($request, $subgroupIds, $options) {

                    $query->whereHas("subgroup", function ($query) use ($request, $subgroupIds, $options) {

                        $facultyId = $request->post("faculty_id");
                        $deptId = $request->post("dept_id");
                        $courseId = $request->post("course_id");
                        $batchIds = $request->post("batch_id");
                        $groupIds = $request->post("group_id");
                        $academicYearIds = $request->post("academic_year_id");
                        $semesterIds = $request->post("semester_id");

                        extract($options);

                        if ($subgroupIds) {

                            $query->whereIn("id", $subgroupIds);
                        }

                        if ($facultyId) {

                            if (is_numeric($facultyId)) {

                                $query->where("faculty_id", $facultyId);
                            } else {

                                $query->whereIn("faculty_id", $facultyId);
                            }
                        }

                        if ($deptId) {

                            if (is_numeric($deptId)) {

                                $query->where("dept_id", $deptId);
                            } else {

                                $query->whereIn("dept_id", $deptId);
                            }
                        }

                        if ($courseId) {

                            if (is_numeric($deptId)) {

                                $query->where("course_id", $courseId);
                            } else {

                                $query->whereIn("course_id", $courseId);
                            }
                        }

                        if ($batchIds) {

                            if (is_numeric($batchIds)) {

                                $query->where("batch_id", $batchIds);
                            } else {

                                $query->whereIn("batch_id", $batchIds);
                            }
                        }

                        if ($groupIds) {

                            $query->whereIn("main_gid", $groupIds);
                        }

                        if ($academicYearIds) {

                            $query->whereIn("year", $academicYearIds);
                        }

                        if ($semesterIds) {

                            $query->whereIn("semester", $semesterIds);
                        }
                    });
                });
            }
        }

        if ($lecturerIds || $staffType) {

            $query->whereHas("ttInfoLecturers", function ($query) use ($lecturerIds, $staffType) {

                if ($lecturerIds) {

                    $query->whereIn("lecturer_id", $lecturerIds);
                }

                if ($staffType) {

                    $query->whereHas("lecturer", function ($query) use ($staffType) {

                        $query->where("staff_type", $staffType);
                    });
                }
            });
        }

        if ($spaceIds) {

            $query->whereHas("ttInfoSpaces", function ($query) use ($spaceIds) {

                $query->whereIn("space_id", $spaceIds);
            });
        }

        $query->whereHas("module");
        $query->whereHas("timetable", function ($query) use ($request, $directOnly, $options) {

            $timetableType = $request->post("timetable_type");

            if ($directOnly === "yes") {

                $facultyId = $request->post("faculty_id");
                $deptId = $request->post("dept_id");
                $courseId = $request->post("course_id");
                $batchIds = $request->post("batch_id");
                $academicYearIds = $request->post("academic_year_id");
                $semesterIds = $request->post("semester_id");

                extract($options);

                if ($facultyId) {

                    if (is_numeric($facultyId)) {

                        $query->where("faculty_id", $facultyId);
                    } else {

                        $query->whereIn("faculty_id", $facultyId);
                    }
                }

                if ($deptId) {

                    if (is_numeric($deptId)) {

                        $query->where("dept_id", $deptId);
                    } else {

                        $query->whereIn("dept_id", $deptId);
                    }
                }

                if ($courseId) {

                    if (is_numeric($deptId)) {

                        $query->where("course_id", $courseId);
                    } else {

                        $query->whereIn("course_id", $courseId);
                    }
                }

                if ($batchIds) {

                    if (is_numeric($batchIds)) {

                        $query->where("batch_id", $batchIds);
                    } else {

                        $query->whereIn("batch_id", $batchIds);
                    }
                }

                if ($academicYearIds) {

                    $query->whereIn("academic_year_id", $academicYearIds);
                }

                if ($semesterIds) {

                    $query->whereIn("semester_id", $semesterIds);
                }
            }

            $query->where("auto_gen_status", "!=", 1);
            $query->whereNull("deleted_at");

            if ($timetableType === "academic") {
                $query->where("status", 1);
                $query->where("type", "=", 2);
                $query->where("master_timetable_id", "!=", 0);
            } elseif ($timetableType === "master") {

                $query->where("type", "=", 1);
                $query->where("master_timetable_id", "=", 0);
            } else {
                $query->where(function ($query) {

                    $query->where(function ($query) {

                        $query->where("type", 1)->where("master_timetable_id", 0)->where(function ($query) {

                            $query->where("approval_status", "!=", 1)->orWhereNull("approval_status");
                        });
                    })->orWhere(function ($query) {

                        $query->where("type", 2)->where("master_timetable_id", "!=", 0);
                    });
                });
            }
        });

        if ($examOnly === "yes") {

            $query->where("exam_type_id", "!=", 0);
        }

        if (is_array($deliveryModeIds) && count($deliveryModeIds) > 0) {
            $query->where(function ($query) use ($deliveryModeIds) {

                $query->where(function ($query) use ($deliveryModeIds) {

                    $query->whereIn("delivery_mode_id", $deliveryModeIds)->whereNull("delivery_mode_id_special");
                });

                $query->orWhereIn("delivery_mode_id_special", $deliveryModeIds);
            });
        }

        if ($dateFrom !== null && $dateTill !== null) {

            $query->whereDate("tt_date", ">=", $dateFrom);
            $query->whereDate("tt_date", "<=", $dateTill);
        } else {

            if ($upcomingOnly === "yes") {

                $today = date("Y-m-d", time());
                $query->whereDate("tt_date", ">=", $today);
            }
        }

        return $query->get()->toArray();
    }

    public function getTimetableModuleSubgroups($model, $deliveryModeId = null): array
    {
        $query = SubgroupModule::query()
            ->with(["subgroup"])
            ->select(["subgroup_id", "module_id"])
            ->whereHas("subgroup", function ($query) use ($model, $deliveryModeId) {

                $query->where("batch_id", $model->batch_id)
                    ->where("syllabus_id", $model->syllabus_id);

                if ($deliveryModeId) {

                    $query->where("dm_id", $deliveryModeId);
                }
            });

        $results = $query->get()->toArray();

        $data = [];
        if (is_array($results) && count($results) > 0) {

            foreach ($results as $result) {

                if (!isset($data[$result["module_id"]])) {

                    $data[$result["module_id"]] = [];
                }

                if (isset($result["subgroup"]["id"])) {

                    $data[$result["module_id"]][] = [
                        "id" => $result["subgroup"]["id"],
                        "name" => $result["subgroup"]["name"],
                        "dm_id" => $result["subgroup"]["dm_id"],
                    ];
                }
            }
        }

        return $data;
    }

    public function getDepartmentIdFromCourseId($courseId)
    {
        $record = Course::query()->find($courseId);

        if ($record) {

            return $record->dept_id;
        }

        return null;
    }

    public function getFacultyIdFromDepartmentId($deptId)
    {
        $record = Department::query()->find($deptId);

        if ($record) {

            return $record->faculty_id;
        }

        return null;
    }

    public function updateFacultyDepartmentBulk()
    {
        $records = AcademicTimetable::withTrashed()->select(["academic_timetable_id", "course_id"])->get()->toArray();

        if (count($records) > 0) {

            foreach ($records as $record) {

                $deptId = $this->getDepartmentIdFromCourseId($record["course_id"]);
                $facultyId = $this->getFacultyIdFromDepartmentId($deptId);

                $data = [];
                $data["dept_id"] = $deptId;
                $data["faculty_id"] = $facultyId;

                AcademicTimetable::withTrashed()
                    ->where("academic_timetable_id", $record["id"])
                    ->update($data);
            }
        }
    }

    public function updateACBulk()
    {
        $records = AcademicTimetable::withTrashed()
            ->select(["academic_timetable_id", "course_id", "academic_year_id", "semester_id", "batch_id"])
            ->get()->toArray();

        if (count($records) > 0) {

            foreach ($records as $record) {

                $aCModel = AcademicCalendar::query()
                    ->where("course_id", $record["course_id"])
                    ->where("academic_year_id", $record["academic_year_id"])
                    ->where("semester_id", $record["semester_id"])
                    ->where("batch_id", $record["batch_id"])
                    ->first();

                if ($aCModel) {

                    $data = [];
                    $data["academic_calendar_id"] = $aCModel->id;

                    AcademicTimetable::withTrashed()
                        ->where("academic_timetable_id", $record["id"])
                        ->update($data);
                }
            }
        }
    }

    public function updateATBulk()
    {
        $records = AcademicTimetable::withTrashed()
            ->select(["academic_timetable_id", "subgroup_id"])
            ->get();

        if (count($records) > 0) {

            foreach ($records as $record) {

                $sGModel = Subgroup::withTrashed()->select(["syllabus_id"])->find($record->subgroup_id);

                if ($sGModel) {

                    $record->syllabus_id = $sGModel->syllabus_id;

                    $record->save();
                }
            }
        }
    }
}
