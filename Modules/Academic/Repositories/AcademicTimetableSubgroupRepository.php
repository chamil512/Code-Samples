<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Entities\AcademicTimetableInformation;
use Modules\Academic\Entities\AcademicTimetableInformationSubgroup;
use Modules\Academic\Entities\AcademicTimetableSubgroup;
use Modules\Academic\Entities\SubgroupModule;

class AcademicTimetableSubgroupRepository extends BaseRepository
{
    public array $sgModules = [];

    public static function addRecords($timetableInfoId, $ids, $baseModuleId, $sgModules = []): array
    {
        if (isset($ids) && count($ids) > 0) {

            foreach ($ids as $id) {

                $sgModule = ["subgroup_id" => $id, "base_module_id" => $baseModuleId];
                $sgModuleKey = array_search($sgModule, $sgModules);

                if ($sgModuleKey === false) {

                    $moduleId = self::getModuleIdFromSubgroup($id, $baseModuleId);
                    $sgModule["module_id"] = $moduleId;

                    $sgModules[] = $sgModule;
                } else {
                    $sgModule = $sgModules[$sgModuleKey];
                    $moduleId = $sgModule["module_id"];
                }

                if ($moduleId) {

                    $record = new AcademicTimetableSubgroup();
                    $record->academic_timetable_information_id = $timetableInfoId;
                    $record->subgroup_id = $id;
                    $record->module_id = $moduleId;

                    $record->save();
                }
            }
        }

        return $sgModules;
    }

    public static function updateRecords($timetableInfoId, $ids, $baseModuleId, $sgModules = []): array
    {
        //get existing records
        $existingRecords = AcademicTimetableSubgroup::query()
            ->select("subgroup_id")
            ->where("academic_timetable_information_id", $timetableInfoId)
            ->get()->keyBy("subgroup_id")->toArray();

        $existingIds = array_keys($existingRecords);

        $updatingIds = [];
        if (isset($ids) && count($ids) > 0) {
            foreach ($ids as $id) {

                $sgModule = ["subgroup_id" => $id, "base_module_id" => $baseModuleId];
                $sgModuleKey = array_search($sgModule, $sgModules);

                if ($sgModuleKey === false) {

                    $moduleId = self::getModuleIdFromSubgroup($id, $baseModuleId);
                    $sgModule["module_id"] = $moduleId;

                    $sgModules[] = $sgModule;
                } else {
                    $sgModule = $sgModules[$sgModuleKey];
                    $moduleId = $sgModule["module_id"];
                }

                if ($moduleId) {

                    $updatingIds[] = $id;
                    AcademicTimetableSubgroup::query()
                        ->updateOrCreate(["academic_timetable_information_id" => $timetableInfoId, "subgroup_id" => $id], ["module_id" => $moduleId]);
                }
            }
        }

        $deletedIds = array_diff($existingIds, $updatingIds);

        //delete those records
        AcademicTimetableSubgroup::query()
            ->where("academic_timetable_information_id", $timetableInfoId)
            ->whereIn("subgroup_id", $deletedIds)->delete();

        return $sgModules;
    }

    public static function deleteRecords($timetableInfoIds)
    {
        AcademicTimetableSubgroup::query()->whereIn("academic_timetable_information_id", $timetableInfoIds)->delete();
    }

    public static function getModuleIdFromSubgroup($subgroupId, $baseModuleId)
    {
        $modules = SubgroupModule::query()
            ->select("module_id")
            ->where("subgroup_id", $subgroupId)
            ->get()->keyBy("module_id")->toArray();
        $moduleIds = array_keys($modules);

        $moduleId = false;
        if (count($moduleIds) > 0) {

            if (in_array($baseModuleId, $moduleIds)) {

                $moduleId = $baseModuleId;
            } else {

                //get similar module ids
                $similarIds = SimilarCourseModuleRepository::getSimilarIds($baseModuleId);

                if (count($similarIds) > 0) {

                    foreach ($similarIds as $similarId) {

                        if (in_array($similarId, $moduleIds)) {

                            $moduleId = $similarId;
                            break;
                        }
                    }
                }
            }
        }

        return $moduleId;
    }

    public function updateExistingSubgroupRecords()
    {
        //this should be called from laravel tinker
        $results = AcademicTimetableInformation::with("subgroups")
            ->select(DB::raw("academic_timetable_information_id"), DB::raw("module_id"))
            ->get()
            ->toArray();

        if (count($results) > 0) {

            foreach ($results as $result) {

                $subgroups = $result["subgroups"];

                $sgIds = [];
                foreach ($subgroups as $subgroup) {

                    $sgIds[] = $subgroup["id"];
                }

                $this->sgModules = self::updateRecords($result["id"], $sgIds, $result["module_id"], $this->sgModules);
            }
        }
    }

    /**
     * @param array $relations
     * @param bool $prepared //raw data or prepared data
     * @return array|Builder[]|Collection
     */
    public function getFilteredData($relations = [], $prepared = true)
    {
        $request = request();

        $facultyIds = $request->post("faculty_id");
        $deptIds = $request->post("dept_id");
        $courseIds = $request->post("course_id");
        $batchIds = $request->post("batch_id");
        $groupIds = $request->post("group_id");
        $deliveryModeIds = $request->post("delivery_mode_id");
        $subgroupIds = $request->post("subgroup_id");
        $spaceIds = $request->post("space_id");
        $studentId = $request->post("student_id");
        $lecturerIds = $request->post("lecturer_id");
        $dateFrom = $request->post("date_from");
        $dateTill = $request->post("date_till");
        $examOnly = $request->post("exam_only");
        $upcomingOnly = $request->post("upcoming_only");
        $uniqueOnly = $request->post("unique_only");

        $query = AcademicTimetableInformationSubgroup::query()
            ->where("slot_status", 1)
            ->orderBy("tt_date");

        if (is_array($relations) && count($relations) > 0) {

            $query->with($relations);
        } else {

            $query->with(["timetable", "module", "deliveryMode", "deliveryModeSpecial", "examType",
                "examCategory", "lecturers", "subgroups", "spaces", "attendance", "subgroup", "course", "batch"]);
        }

        if ($facultyIds) {

            if (is_array($facultyIds)) {

                $query->whereIn("faculty_id", $facultyIds);
            } else {

                $query->where("faculty_id", $facultyIds);
            }
        }

        if ($deptIds) {
            if (is_array($deptIds)) {

                $query->whereIn("dept_id", $deptIds);
            } else {

                $query->where("dept_id", $deptIds);
            }
        }

        if ($courseIds) {
            if (is_array($courseIds)) {

                $query->whereIn("course_id", $courseIds);
            } else {

                $query->where("course_id", $courseIds);
            }
        }

        if ($batchIds) {

            if (is_array($batchIds)) {

                $query->whereIn("batch_id", $batchIds);
            } else {

                $query->where("batch_id", $batchIds);
            }
        }

        if ($groupIds) {

            $query->whereIn("group_id", $groupIds);
        }

        if ($subgroupIds) {

            $query->whereHas("ttInfoSubgroups", function ($query) use ($subgroupIds) {

                $query->whereIn("subgroup_id", $subgroupIds);
            });
        }

        if ($spaceIds) {

            $query->whereHas("ttInfoSpaces", function ($query) use ($spaceIds) {

                $query->whereIn("space_id", $spaceIds);
            });
        }

        if ($lecturerIds) {

            $query->whereHas("ttInfoLecturers", function ($query) use ($lecturerIds) {

                $query->whereIn("lecturer_id", $lecturerIds);
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

        if ($deliveryModeIds) {
            $query->where(function ($query) use ($deliveryModeIds) {

                $query->where(function ($query) use ($deliveryModeIds) {

                    $query->whereIn("delivery_mode_id", $deliveryModeIds)
                        ->where("delivery_mode_id_special", 0);
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

        if ($uniqueOnly === "yes") {

            $query->groupBy("academic_timetable_information_id");
        }

        if ($prepared) {

            $records = $query->get()->toArray();

            if (count($records) > 0) {

                $aTRepo = new AcademicTimetableRepository();
                $data = $aTRepo->getTimetable($records);

                $notify["status"] = "success";

                $response["notify"] = $notify;
                $response["data"] = $data;
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

            $response = $query->get();
        }

        return $response;
    }
}
