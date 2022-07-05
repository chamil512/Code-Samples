<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\CourseSyllabus;
use Modules\Academic\Entities\GradingImplication;
use Modules\Academic\Entities\SyllabusGradingImplication;

class CourseSyllabusRepository extends BaseRepository
{
    public string $statusField = "syllabus_status";

    public array $statuses = [
        ["id" => "1", "name" => "Enabled", "label" => "success"],
        ["id" => "0", "name" => "Disabled", "label" => "danger"]
    ];

    public array $defaultStatuses = [
        ["id" => "1", "name" => "Default", "label" => "success"],
        ["id" => "0", "name" => "Non-Default", "label" => "info"]
    ];

    public array $approvalStatuses = [
        ["id" => "", "name" => "Not Sent for Approval", "label" => "info"],
        ["id" => "0", "name" => "Verification Pending", "label" => "warning"],

        //["id" => "3", "name" => "Verified & Pending Pre-approval of Senior Assistant Registrar", "label" => "success"],
        ["id" => "3", "name" => "Verified & Pending Final approval of Senior Assistant Registrar", "label" => "success"],
        ["id" => "4", "name" => "Verification Declined", "label" => "danger"],

        //["id" => "5", "name" => "Pre-approved by Senior Assistant Registrar & Pending Pre-approval of Registrar", "label" => "success"],
        /*["id" => "5", "name" => "Pre-approved by Senior Assistant Registrar & Pending Final Approval of Head/Department of Finance", "label" => "success"],
        ["id" => "6", "name" => "Pre-approval of Senior Assistant Registrar Declined", "label" => "danger"],*/

        /*["id" => "7", "name" => "Pre-approved by Registrar & Pending Pre-approval of Vice Chancellor", "label" => "success"],
        ["id" => "8", "name" => "Pre-approval of Registrar Declined", "label" => "danger"],

        ["id" => "9", "name" => "Pre-approved by Vice Chancellor & Pending Final Approval of Head/Department of Finance", "label" => "success"],
        ["id" => "10", "name" => "Pre-approval of Vice Chancellor Declined", "label" => "danger"],*/

        ["id" => "1", "name" => "Approved", "label" => "success"],
        ["id" => "2", "name" => "Declined", "label" => "danger"],
    ];

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
            "route" => "/academic/course_syllabus/verification",
            "permissionRoutes" => [],
        ],
        /*[
            "step" => "pre_approval_sar",
            "approvedStatus" => 5,
            "declinedStatus" => 6,
            "route" => "/academic/course_syllabus/pre_approval_sar",
            "permissionRoutes" => [],
        ],
        [
            "step" => "pre_approval_registrar",
            "approvedStatus" => 7,
            "declinedStatus" => 8,
            "route" => "/academic/course_syllabus/pre_approval_registrar",
            "permissionRoutes" => [],
        ],
        [
            "step" => "pre_approval_vc",
            "approvedStatus" => 9,
            "declinedStatus" => 10,
            "route" => "/academic/course_syllabus/pre_approval_vc",
            "permissionRoutes" => [],
        ],*/
        [
            "step" => "approval",
            "approvedStatus" => 1,
            "declinedStatus" => 2,
            "route" => "/academic/course_syllabus/approval",
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
                $text = $model->name . " verification.";
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
        $url = URL::to("/academic/course_syllabus/view/" . $model->id);

        return view("academic::course_syllabus.approvals." . $step, compact('record', 'url'));
    }

    protected function onApproved($model, $step, $previousStatus)
    {
        if ($step === "approval") {

            $model->{$this->statusField} = 1;
            $model->save();
        }
    }

    protected function onDeclined($model, $step, $previousStatus)
    {
        if ($step === "approval" && $model->{$this->statusField} === 1) {

            $model->{$this->statusField} = 0;
            $model->save();
        }
    }

    /*
     * Approval properties and methods ends
     */

    /**
     * @param $model
     * @param $statusField
     * @param $status
     * @param bool $allowed
     * @return bool
     */
    protected function isStatusUpdateAllowed($model, $statusField, $status, bool $allowed = true): bool
    {
        $approvalField = $this->approvalField;

        if ($model->{$approvalField} != "1") {

            $errors = [];
            $errors[] = "This record should have been approved to be eligible to update the status.";

            $this->setErrors($errors);
            $allowed = false;
        }

        return parent::isStatusUpdateAllowed($model, $statusField, $status, $allowed);
    }

    function resetOtherVersionDefault($courseId, $currentId)
    {
        $data = [];
        $data["default_status"] = 0;
        CourseSyllabus::query()->where("course_id", $courseId)->whereNotIn("syllabus_id", [$currentId])->update($data);
    }

    public function getCurriculum($courseModules): array
    {
        $data = [];
        if (is_array($courseModules) && count($courseModules) > 0) {
            $preparedModules = [];
            foreach ($courseModules as $cM) {
                $module = $cM["module"];
                $module["total_hours"] = $cM["total_hours"];
                $module["total_credits"] = $cM["total_credits"];
                $module["delivery_modes"] = $cM["delivery_modes"];
                $module["module_order"] = $cM["module_order"];

                $preparedModules[] = $module;
            }

            $courseModules = $preparedModules;

            $orderColumn1 = array_column($courseModules, 'year_no');
            $orderColumn2 = array_column($courseModules, 'semester_no');
            $orderColumn3 = array_column($courseModules, 'module_order');
            $orderColumn4 = array_column($courseModules, 'module_code');

            array_multisort($orderColumn1, SORT_ASC, $courseModules,
                $orderColumn2, SORT_ASC, $courseModules,
                $orderColumn3, SORT_ASC, $courseModules,
                $orderColumn4, SORT_ASC, $courseModules);

            foreach ($courseModules as $cM) {
                if (!isset($data[$cM["year_name"]])) {
                    $data[$cM["year_name"]] = [];
                }
                if (!isset($data[$cM["year_name"]][$cM["semester_name"]])) {
                    $data[$cM["year_name"]][$cM["semester_name"]] = [];
                }
                if (!isset($data[$cM["year_name"]][$cM["semester_name"]])) {
                    $data[$cM["year_name"]][$cM["semester_name"]] = [];
                }

                $data[$cM["year_name"]][$cM["semester_name"]][] = $cM;
            }
        }

        $array = $data;

        $data = [];
        foreach ($array as $yearName => $semesters) {
            $record = [];
            $record["year_name"] = $yearName;
            $record["semesters"] = [];
            $record["deliveryModes"] = [];

            $deliveryModes = [];
            $moduleModes = [];

            if (count($semesters) > 0) {
                foreach ($semesters as $modules) {
                    if (count($modules) > 0) {
                        foreach ($modules as $module) {
                            if (is_array($module["delivery_modes"]) && count($module["delivery_modes"]) > 0) {
                                foreach ($module["delivery_modes"] as $dm) {
                                    $deliveryModes[$dm["delivery_mode_id"]]["mode_id"] = $dm["delivery_mode_id"];
                                    $deliveryModes[$dm["delivery_mode_id"]]["mode_name"] = $dm["delivery_mode"]["mode_name"];

                                    $moduleModes[$module["module_id"]][$dm["delivery_mode_id"]]["hours"] = $dm["hours"];
                                    $moduleModes[$module["module_id"]][$dm["delivery_mode_id"]]["credits"] = $dm["credits"];
                                }
                            }
                        }
                    }
                }

                if (count($deliveryModes) > 0) {
                    $orderColumn1 = array_column($deliveryModes, 'mode_name');
                    array_multisort($orderColumn1, SORT_ASC, $deliveryModes);

                    $record["deliveryModes"] = $deliveryModes;

                    foreach ($semesters as $semesterName => $modules) {
                        if (count($modules) > 0) {
                            foreach ($modules as $module) {
                                $moduleMode = [];

                                if (isset($moduleModes[$module["module_id"]])) {

                                    $moduleMode = $moduleModes[$module["module_id"]];
                                }

                                foreach ($deliveryModes as $mode) {
                                    $modeId = $mode["mode_id"];

                                    if (isset($moduleMode[$modeId])) {
                                        $mode["hours"] = $moduleMode[$modeId]["hours"];
                                        $mode["credits"] = $moduleMode[$modeId]["credits"];
                                    } else {
                                        $mode["hours"] = "";
                                        $mode["credits"] = "";
                                    }

                                    $module["moduleModes"][] = $mode;
                                }

                                unset($module["delivery_modes"]);

                                $record["semesters"][$semesterName][] = $module;
                            }
                        }
                    }
                } else {
                    $record["semesters"] = $semesters;
                }
            }

            $data[] = $record;
        }

        return $data;
    }

    /**
     * @param $model
     */
    public function updateGradingPoints($model)
    {
        $gIIds = request()->post("grading_implication_id");
        $syllabusGIIds = request()->post("syllabus_gi_id");
        $implicationNames = request()->post("implication_names");
        $gIMinMarks = request()->post("gi_min_marks");
        $gIMaxMarks = request()->post("gi_max_marks");
        $gIPoints = request()->post("gi_points");
        $gIEnables = request()->post("gi_enable");

        $currIds = $this->getCurrentGIIds($model);
        $updatingIds = [];

        if (is_array($gIIds) && count($gIIds) > 0) {

            $dataBulk = [];
            foreach ($gIIds as $key => $gIId) {

                $id = $syllabusGIIds[$key];
                $gIEnable = $gIEnables[$key];

                $grading = [];
                $grading["implication_name"] = $implicationNames[$key];
                $grading["min_marks"] = $gIMinMarks[$key];
                $grading["max_marks"] = $gIMaxMarks[$key];
                $grading["points"] = $gIPoints[$key];

                if ($gIEnable == "1") {

                    if ($id == "") {

                        $grading["syllabus_id"] = $model->id;
                        $grading["grading_implication_id"] = $gIId;

                        $dataBulk[] = $grading;
                    } else {

                        $updatingIds[] = $id;
                        SyllabusGradingImplication::query()->where(["id" => $id])->update($grading);
                    }
                }
            }

            if (count($dataBulk) > 0) {
                SyllabusGradingImplication::query()->insert($dataBulk);
            }
        }

        $deletingIds = array_diff($currIds, $updatingIds);

        if (count($deletingIds) > 0) {

            SyllabusGradingImplication::query()->whereIn("id", $deletingIds)->delete();
        }
    }

    /**
     * @param $model
     * @return int[]|string[]
     */
    private function getCurrentGIIds($model) {

        $syllabusGradings = SyllabusGradingImplication::query()
            ->select("id")
            ->where("syllabus_id", $model->id)
            ->get()
            ->keyBy("id")
            ->toArray();

        return array_keys($syllabusGradings);
    }

    /**
     * @param $model
     * @param bool $onlyUpdated
     * @return array
     */
    public function getGradingPoints($model, $onlyUpdated = false): array
    {
        $gradings = GradingImplication::query()
            ->select(DB::raw("id AS grading_implication_id"), "implication_name", "grade", "min_marks", "max_marks", "points", "imp_status")
            ->get()->toArray();

        if ($model && isset($model->id)) {

            $syllabusGradings = SyllabusGradingImplication::query()
                ->select("id", "grading_implication_id", "implication_name", "min_marks", "max_marks", "points")
                ->where("syllabus_id", $model->id)
                ->get()
                ->keyBy("grading_implication_id")
                ->toArray();
        }

        $data = [];
        if (is_array($gradings) && count($gradings) > 0) {
            foreach ($gradings as $grading) {

                $gIId = $grading["grading_implication_id"];

                if (isset($syllabusGradings[$gIId])) {

                    $sg = $syllabusGradings[$gIId];

                    if ($sg["implication_name"] === "") {

                        $sg["implication_name"] = $grading["implication_name"];
                    }

                    $grading = array_merge($grading, $sg);

                    $data[] = $grading;
                } else {

                    if (!$onlyUpdated) {

                        if ($grading["imp_status"] == "1") {

                            $grading["id"] = "";
                            $data[] = $grading;
                        }
                    }
                }
            }

            $orderColumn = array_column($data, 'points');
            array_multisort($orderColumn, SORT_DESC, $data);

            $orderColumn = array_column($data, 'max_marks');
            array_multisort($orderColumn, SORT_DESC, $data);
        }

        return $data;
    }

    public function duplicate($model)
    {
        $replica = $model->replicate();
        if ($model->type === 2) {
            $replica->based_syllabus_id = $model->based_syllabus_id;
        } else {

            $replica->based_syllabus_id = $model->id;
        }
        $replica->push();

        $modules = $model->syllabusModules()->get();

        if (count($modules) > 0) {

            foreach ($modules as $module) {

                $modModel = $module->replicate();
                $modModel->syllabus_id = $replica->id;

                $modModel->push();

                $examTypes = $module->examTypes()->get();

                if (count($examTypes) > 0) {

                    foreach ($examTypes as $examType) {

                        $eTModel = $examType->replicate();
                        $eTModel->syllabus_module_id = $modModel->id;

                        $eTModel->push();
                    }
                }

                $deliveryModes = $module->deliveryModes()->get();

                if (count($deliveryModes) > 0) {

                    foreach ($deliveryModes as $deliveryMode) {

                        $dMModel = $deliveryMode->replicate();
                        $dMModel->syllabus_module_id = $modModel->id;

                        $dMModel->push();
                    }
                }
            }
        }

        $sECriteria = $model->syllabusEntryCriteria()->get();
        if (count($sECriteria) > 0) {

            foreach ($sECriteria as $criteria) {

                $sECModel = $criteria->replicate();
                $sECModel->syllabus_id = $replica->id;

                $sECModel->push();

                $ecDocuments = $criteria->ecDocuments()->get();
                if (count($ecDocuments) > 0) {

                    foreach ($ecDocuments as $ecDocument) {

                        $sECDModel = $ecDocument->replicate();
                        $sECDModel->syllabus_entry_criteria_id = $sECModel->id;

                        $sECDModel->push();
                    }
                }
            }
        }

        return $replica;
    }
}
