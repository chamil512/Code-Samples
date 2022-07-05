<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Entities\AcademicTimetableInformation;
use Modules\Academic\Entities\SyllabusModule;
use Modules\Academic\Entities\TimetableCompletedModule;
use Modules\QualityAssurance\Repositories\BridgingR;

class TimetableCompletedModuleRepository extends BaseRepository
{
    public string $statusField = "status";

    public array $statuses = [
        ["id" => "1", "name" => "Completed", "label" => "success"],
        ["id" => "0", "name" => "Pending", "label" => "danger"]
    ];

    private array $recordStatuses = [];
    private array $lecturers = [];
    private array $subgroupStudents = [];
    private array $lecturerSubgroups = [];
    private array $lecturerStudents = [];

    /**
     * @param $timetable
     * @return array
     */
    public function getTimetableModules($timetable): array
    {
        $data = [];
        $query = SyllabusModule::with(["module"]);

        if (!empty($timetable->delivery_mode_id)) {

            $query->whereHas("deliveryModes", function ($query) use ($timetable) {

                $query->where("delivery_mode_id", $timetable->delivery_mode_id);
            });
        } else {

            $query->where("syllabus_id", $timetable->syllabus_id);
        }

        $syllabusModules = $query->whereHas("module", function ($query) use ($timetable) {

            $query->where("academic_year_id", $timetable->academic_year_id);
            $query->where("semester_id", $timetable->semester_id);
        })->get()->toArray();

        if (count($syllabusModules) > 0) {

            $moduleIds = [];
            foreach ($syllabusModules as $sm) {

                if (!in_array($sm["module"]["id"], $moduleIds)) {

                    $moduleIds[] = $sm["module"]["id"];

                    $module = [];
                    $module["module_id"] = $sm["module"]["id"];
                    $module["name"] = $sm["module"]["name"];

                    $data[] = $module;
                }
            }
        }

        return $data;
    }

    /**
     * @param $timetable
     * @return array
     */
    public function getData($timetable): array
    {
        $currentData = TimetableCompletedModule::query()
            ->with(["module"])
            ->where("academic_timetable_id", $timetable->id)
            ->get()
            ->keyBy("module_id")
            ->toArray();

        $modules = $this->getTimetableModules($timetable);

        $data = [];
        if (count($modules) > 0) {

            foreach ($modules as $module) {

                if (isset($currentData[$module["module_id"]])) {

                    $module["id"] = $currentData[$module["module_id"]]["id"];
                    $module["status"] = ["id" => 0, "name" => "Pending"];

                    if ($currentData[$module["module_id"]]["status"] === 1) {

                        $module["status"] = ["id" => 1, "name" => "Completed"];
                    }
                } else {

                    $module["id"] = "";
                    $module["status"] = ["id" => 0, "name" => "Pending"];
                }

                $data[] = $module;
            }
        }

        return $data;
    }

    /**
     * @param $timetable
     * @return array
     */
    public function updateData($timetable): array
    {
        $success = false;
        $error = "";

        DB::beginTransaction();
        try {

            $records = request()->post("data");
            $records = json_decode($records, true);

            $currentIds = $this->getRecordIds($timetable);
            $updatingIds = [];

            if (is_array($records) && count($records) > 0) {
                foreach ($records as $record) {

                    $id = $record["id"];
                    $moduleId = $record["module_id"];
                    $status = $record["status"]["id"] ?? 0;

                    if ($id === "" || !in_array($id, $currentIds)) {

                        $record = new TimetableCompletedModule();
                        $record->academic_timetable_id = $timetable->id;
                        $record->module_id = $moduleId;
                        $record->status = $status;
                    } else {

                        $record = TimetableCompletedModule::query()->find($id);
                        $record->status = $status;

                        $updatingIds[] = $id;
                    }

                    $record->save();
                }
            }

            $notUpdatingIds = array_diff($currentIds, $updatingIds);

            if (count($notUpdatingIds) > 0) {

                TimetableCompletedModule::query()->whereIn("id", $notUpdatingIds)->delete();
            }

            $success = true;
        } catch (Exception $exception) {

            $error = $exception->getMessage() . " in " . $exception->getFile() . " @ " . $exception->getLine();
        }

        $response = [];
        if ($success) {

            DB::commit();

            $data = $this->getData($timetable);

            $this->triggerSurvey($timetable->id, $data);

            $response["status"] = "success";
            $response["notify"][] = "Successfully saved the details.";
            $response["data"] = $data;
        } else {

            DB::rollBack();

            $response["status"] = "failed";
            $response["notify"][] = "Error occurred while saving the details. Please try again";
            $response["error"] = $error;
        }

        return $response;
    }

    /**
     * @param $timetable
     * @return array
     */
    public function getRecordIds($timetable): array
    {
        $results = TimetableCompletedModule::query()
            ->select("id", "status")
            ->where("academic_timetable_id", $timetable->id)
            ->get()
            ->keyBy("id")
            ->toArray();

        $this->recordStatuses = $results;

        return array_keys($results);
    }

    /**
     * @param $timetableId
     * @param $records
     * @return void
     */
    public function triggerSurvey($timetableId, $records)
    {
        if (count($records) > 0) {

            foreach ($records as $record) {

                if ($record["status"]["id"] === 1) {

                    if (!isset($this->recordStatuses[$record["id"]]) || $this->recordStatuses[$record["id"]]["status"] !== 1) {

                        //trigger survey here
                        $lecturers = $this->getTimetableModuleLecturerStudents($timetableId, $record["module_id"]);
                        // echo '<pre>';
                        // print_r($lecturers);
                        // echo '</pre>';
                        // dd($lecturers);
                        BridgingR::triggerSurveySave($record["module_id"], $lecturers);
                    }
                }
            }
        }
    }

    /**
     * @param $timetableId
     * @param $moduleId
     * @return array
     */
    public function getTimetableModuleLecturerStudents($timetableId, $moduleId): array
    {
        $results = AcademicTimetableInformation::query()
            ->with(["ttInfoLecturers", "ttInfoSubgroups.subgroup.subgroupStudents"])
            ->where("academic_timetable_id", $timetableId)
            ->where("module_id", $moduleId)
            ->where("slot_status", 1)
            ->get()
            ->toArray();

        if (is_array($results) && count($results) > 0) {

            foreach ($results as $result) {

                $subgroupIds = [];
                if (is_array($result["tt_info_subgroups"]) && count($result["tt_info_subgroups"]) > 0) {

                    foreach ($result["tt_info_subgroups"] as $infoSubgroup) {

                        $subgroupIds[] = $infoSubgroup["subgroup_id"];

                        if (!isset($this->subgroupStudents[$infoSubgroup["subgroup_id"]])) {

                            $this->subgroupStudents[$infoSubgroup["subgroup_id"]] = [];

                            if (
                                isset($infoSubgroup["subgroup"]["subgroup_students"]) &&
                                is_array($infoSubgroup["subgroup"]["subgroup_students"]) &&
                                count($infoSubgroup["subgroup"]["subgroup_students"]) > 0
                            ) {

                                foreach ($infoSubgroup["subgroup"]["subgroup_students"] as $student) {

                                    if ($student["status"] === 0 && $student["student_status"] === 0) {

                                        $this->subgroupStudents[$infoSubgroup["subgroup_id"]][] = $student["std_id"];
                                    }
                                }
                            }
                        }
                    }
                }

                if (
                    count($subgroupIds) > 0 && is_array($result["tt_info_lecturers"]) &&
                    count($result["tt_info_lecturers"]) > 0
                ) {

                    foreach ($result["tt_info_lecturers"] as $lecturer) {

                        $this->lecturers[] = $lecturer["lecturer_id"];

                        if (!isset($this->lecturerSubgroups[$lecturer["lecturer_id"]])) {

                            $this->lecturerSubgroups[$lecturer["lecturer_id"]] = [];
                        }

                        foreach ($subgroupIds as $subgroupId) {

                            if (!in_array($subgroupId, $this->lecturerSubgroups[$lecturer["lecturer_id"]])) {

                                $this->lecturerSubgroups[$lecturer["lecturer_id"]][] = $subgroupId;
                            }
                        }
                    }
                }
            }
        }

        $this->setLecturerStudents();

        return $this->lecturerStudents;
    }

    public function setLecturerStudents()
    {
        if (count($this->lecturers) > 0 && count($this->subgroupStudents) > 0) {

            foreach ($this->lecturers as $lecturerId) {

                foreach ($this->subgroupStudents as $subgroupId => $studentIds) {

                    if (in_array($subgroupId, $this->lecturerSubgroups[$lecturerId])) {

                        if (count($studentIds) > 0) {

                            foreach ($studentIds as $studentId) {

                                if (!isset($this->lecturerStudents[$lecturerId])) {

                                    $this->lecturerStudents[$lecturerId] = [];
                                }

                                if (!in_array($studentId, $this->lecturerStudents[$lecturerId])) {

                                    $this->lecturerStudents[$lecturerId][] = $studentId;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
