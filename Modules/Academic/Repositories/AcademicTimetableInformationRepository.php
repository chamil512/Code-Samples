<?php

namespace Modules\Academic\Repositories;

use App\Helpers\Helper;
use App\Repositories\BaseRepository;
use App\Services\Notify;
use App\SystemApproval;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicTimetable;
use Modules\Academic\Entities\AcademicTimetableInformation;
use Modules\Academic\Entities\AcademicTimetableLecturer;
use Modules\Academic\Entities\AcademicTimetableSpace;

class AcademicTimetableInformationRepository extends BaseRepository
{
    public string $statusField = "slot_status";
    public array $remindBeforeDays = [2, 0];

    public array $statuses = [
        ["id" => "0", "name" => "Disabled", "label" => "danger"],
        ["id" => "1", "name" => "Enabled", "label" => "success"],
    ];

    public array $slotTypes = [
        ["id" => "1", "name" => "Default", "label" => "info"],
        ["id" => "2", "name" => "Canceled", "label" => "info"],
        ["id" => "3", "name" => "Rescheduled", "label" => "info"],
        ["id" => "4", "name" => "Revision", "label" => "info"],
        ["id" => "5", "name" => "Relief", "label" => "info"],
    ];

    public array $approvalStatuses = [
        ["id" => "0", "name" => "Verification Pending of Batch/Semester Coordinator", "label" => "warning"],
        ["id" => "3", "name" => "Verified & Pending Pre-approval of Academic Head of the Department", "label" => "success"],
        ["id" => "4", "name" => "Verification of Batch/Semester Coordinator Declined", "label" => "danger"],

        //["id" => "5", "name" => "Pre-approved & Pending Pre-approval of Senior Assistant Registrar", "label" => "success"],
        ["id" => "5", "name" => "Pre-approved & Pending Final Approval of Senior Assistant Registrar", "label" => "success"],
        ["id" => "6", "name" => "Pre-approval of Academic Head of the Department Declined", "label" => "danger"],

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
            "route" => "/verification",
            "permissionRoutes" => [],
        ],
        [
            "step" => "pre_approval_hod",
            "approvedStatus" => 5,
            "declinedStatus" => 6,
            "route" => "/pre_approval_hod",
            "permissionRoutes" => [],
        ],
        /*[
            "step" => "pre_approval_sar",
            "approvedStatus" => 7,
            "declinedStatus" => 8,
            "route" => "/pre_approval_sar",
            "permissionRoutes" => [],
        ],*/
        /*[
            "step" => "pre_approval_registrar",
            "approvedStatus" => 9,
            "declinedStatus" => 10,
            "route" => "/pre_approval_registrar",
            "permissionRoutes" => [],
        ],
        [
            "step" => "pre_approval_vc",
            "approvedStatus" => 11,
            "declinedStatus" => 12,
            "route" => "/pre_approval_vc",
            "permissionRoutes" => [],
        ],*/
        [
            "step" => "approval",
            "approvedStatus" => 1,
            "declinedStatus" => 2,
            "route" => "/approval",
            "permissionRoutes" => [],
        ]
    ];

    private function _getSlotType($model): string
    {
        $type = "";
        if ($model->slot_type === 2) {
            $type = "cancel";
        } elseif ($model->slot_type === 3) {
            $type = "reschedule";
        } elseif ($model->slot_type === 4) {
            $type = "revision";
        } elseif ($model->slot_type === 5) {
            $type = "relief";
        }

        return $type;
    }

    public function getApprovalStepTitle($model, $step): string
    {
        $type = $this->_getSlotType($model);

        $timetable = $model->timetable;
        $timetableName = $timetable->name;

        switch ($step) {
            case "verification" :
                $text = $timetableName . "'s " . $model->name . " " . $type . " verification of batch/semester coordinator.";
                break;

            case "pre_approval_hod" :
                $text = $timetableName . "'s " . $model->name . " " . $type . " pre-approval of academic head of the department.";
                break;

            case "pre_approval_sar" :
                $text = $timetableName . "'s " . $model->name . " " . $type . " pre-approval of senior assistant registrar.";
                break;

            /*case "pre_approval_registrar" :
                $text = $timetableName . "'s " . $model->name . " " . $type . " pre-approval of registrar.";
                break;

            case "pre_approval_vc" :
                $text = $timetableName . "'s " . $model->name . " " . $type . " pre-approval of vice chancellor.";
                break;*/

            case "approval" :

                if ($model->slot_type === 2 || $model->slot_type === 3) {

                    $text = $timetableName . "'s " . $model->name . " " . $type . " final approval of senior assistant registrar.";
                } else {

                    $text = $timetableName . "'s " . $model->name . " " . $type . " final approval of Head/Department of Finance.";
                }
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
        $timetableUrl = URL::to("/academic/academic_timetable/view/" . $model->academic_timetable_id);

        $type = $this->_getSlotType($model);

        $record = $model->toArray();

        $rescheduleInfo = null;
        if (isset($record["rescheduled_slot_info"]["date"])
            && isset($record["rescheduled_slot_info"]["start_time"])
            && isset($record["rescheduled_slot_info"]["end_time"])) {

            $rescheduleInfo = $record["rescheduled_slot_info"];
            $rescheduleInfo["hours"] = Helper::getHourDiff($rescheduleInfo["start_time"], $rescheduleInfo["end_time"]);
        }

        $record = $model->toArray();
        return view("academic::academic_timetable_information.approvals." . $type . "." . $step,
            compact('record', 'timetableUrl', 'rescheduleInfo'));
    }

    /**
     * @param $model
     * @param $step
     * @return array
     */
    protected function getApprovalStepUsers($model, $step): array
    {
        $data = [];
        if ($step === "verification") {

            $timetable = $model->timetable;
            $data = BatchCoordinatorRepository::getBatchCoordinatorIds($timetable->batch_id);

        } elseif ($step === "pre_approval_hod") {

            $timetable = $model->timetable;

            //get department id
            $course = $timetable->course;
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
     * @param $model
     */
    public function validateRequest($model)
    {
        $slotType = request()->route()->getAction()['type'];

        if ($model->slot_type !== $slotType) {

            abort(404);
        }
    }

    /**
     * @param $model
     * @return bool
     */
    public function isValidModel($model): bool
    {
        if (isset(request()->route()->getAction()['type'])) {

            $slotType = request()->route()->getAction()['type'];

            if ($model->slot_type !== $slotType) {

                $this->setApprovalUrls($slotType);

                return false;
            }
        }

        return true;
    }

    /**
     * @param $slotType
     */
    public function setApprovalUrls($slotType)
    {
        $slotType = intval($slotType);

        $baseUrl = "";
        if ($slotType === 2) {
            $baseUrl = "/academic/academic_timetable_slot_cancel";
        } elseif ($slotType === 3) {
            $baseUrl = "/academic/academic_timetable_slot_reschedule";
        } elseif ($slotType === 4) {
            $baseUrl = "/academic/academic_timetable_slot_revision";
        } elseif ($slotType === 5) {
            $baseUrl = "/academic/academic_timetable_slot_relief";
        }

        $approvalSteps = $this->approvalSteps;

        if (count($approvalSteps) > 0) {

            $data = [];
            foreach ($approvalSteps as $approvalStep) {

                $approvalStep["route"] = $baseUrl . $approvalStep["route"];

                $data[] = $approvalStep;
            }

            $this->approvalSteps = $data;
        }
    }

    /**
     * @param $slotType
     */
    public function setApprovalStatuses($slotType)
    {
        $slotType = intval($slotType);

        if ($slotType === 2 || $slotType === 3) {

            $approvalStatuses = [
                ["id" => "0", "name" => "Preapproval Pending of Academic Head of the Department", "label" => "warning"],

                ["id" => "3", "name" => "Pre-approved & Pending Final Approval of Senior Assistant Registrar", "label" => "success"],
                ["id" => "4", "name" => "Pre-approval of Academic Head of the Department Declined", "label" => "danger"],

                ["id" => "1", "name" => "Approved", "label" => "success"],
                ["id" => "2", "name" => "Declined", "label" => "danger"],
            ];

            $this->approvalStatuses = $approvalStatuses;
        }
    }

    /**
     * @param $slotType
     */
    public function setApprovalSteps($slotType)
    {
        $slotType = intval($slotType);

        if ($slotType === 2 || $slotType === 3) {

            $approvalSteps = [
                [
                    "step" => "pre_approval_hod",
                    "approvedStatus" => 3,
                    "declinedStatus" => 4,
                    "route" => "/pre_approval_hod",
                    "permissionRoutes" => [],
                ],
                [
                    "step" => "approval",
                    "approvedStatus" => 1,
                    "declinedStatus" => 2,
                    "route" => "/approval",
                    "permissionRoutes" => [],
                ]
            ];

            $this->approvalSteps = $approvalSteps;
        }
    }

    /**
     * @param $model
     * @param $step
     * @param $previousStatus
     * @return void
     */
    protected function onApproved($model, $step, $previousStatus)
    {
        if ($step === "approval") {
            if ($model->slot_type === 2) {

                //cancelled
                $model->{$this->statusField} = 0;
            } else {
                $model->{$this->statusField} = 1;
            }

            if ($model->save()) {

                if ($model->slot_type === 2) {

                    $this->triggerAutomatedReschedule($model);

                } elseif ($model->slot_type === 3) {

                    //update the cancelled slot with rescheduling slot id
                    $cModel = AcademicTimetableInformation::withTrashed()->find($model->cancelled_slot_id);

                    if ($cModel) {

                        $cModel->rescheduled_slot_id = $model->id;
                        $cModel->save();
                    }
                }
            }
        }
    }

    protected function onDeclined($model, $step, $previousStatus)
    {
        if ($step === "approval" && $model->{$this->statusField} === 1) {

            if ($model->slot_type === 2) {

                //rollback cancellation to pending
                $model->{$this->statusField} = 1;
            } else {

                $model->{$this->statusField} = 0;
            }

            if ($model->save()) {

                if ($model->slot_type === 3) {

                    //reset rescheduling slot id of the cancelled slot
                    $cModel = AcademicTimetableInformation::withTrashed()->find($model->cancelled_slot_id);

                    if ($cModel) {

                        $cModel->rescheduled_slot_id = null;
                        $cModel->save();
                    }
                }
            }
        }
    }

    public function triggerAutomatedReschedule($model)
    {
        $cancelled = AcademicTimetableInformation::query()
            ->where("cancelled_slot_id", $model->id)
            ->first();

        if (!$cancelled) {

            $record = $model->toArray();

            $info = $record["rescheduled_slot_info"] ?? [];

            if (isset($info["date"]) && isset($info["start_time"]) && isset($info["end_time"])) {

                $info["hours"] = Helper::getHourDiff($info["start_time"], $info["end_time"]);

                $date = $info["date"];
                $startTime = $info["start_time"];
                $endTime = $info["end_time"];
                $lecturerIds = [];
                $subgroupIds = [];
                $spaceIds = [];

                $startTimeText = date("h:i A", strtotime($startTime));
                $endTimeText = date("h:i A", strtotime($endTime));

                if (isset($info["lecturers"])) {

                    if (is_array($info["lecturers"]) && count($info["lecturers"]) > 0) {

                        foreach ($info["lecturers"] as $lecturer) {

                            $lecturerIds[] = $lecturer["id"];
                        }
                    }
                } else {

                    if (is_array($record["lecturers"]) && count($record["lecturers"]) > 0) {

                        foreach ($record["lecturers"] as $lecturer) {

                            $lecturerIds[] = $lecturer["id"];
                        }
                    }
                }

                if (is_array($record["subgroups"]) && count($record["subgroups"]) > 0) {

                    foreach ($record["subgroups"] as $subgroup) {

                        $subgroupIds[] = $subgroup["id"];
                    }
                }

                if (is_array($record["spaces"]) && count($record["spaces"]) > 0) {

                    foreach ($record["spaces"] as $space) {

                        $spaceIds[] = $space["id"];
                    }
                }

                $cancelledBy = null;
                $modelName = $this->getClassName($model);
                $modelHash = $this->generateClassNameHash($modelName);

                $cancellation = SystemApproval::query()
                    ->select("created_by")
                    ->where("model_hash", $modelHash)
                    ->where("model_id", $model->id)
                    ->where("approval_step", "pre_approval_hod")
                    ->orderBy("created_at", "desc")
                    ->first();

                if ($cancellation) {

                    $cancelledBy = $cancellation->created_by;
                }

                $aTRepo = new AcademicTimetableRepository();

                $availableLectureIds = $aTRepo->getAvailableLecturerIds($model->id, $date, $startTime, $endTime, $lecturerIds);

                $rescheduleAllowed = true;
                $spaceNotifyNeeded = false;
                $failedReason = "";
                if (count($lecturerIds) === count($availableLectureIds)) {

                    $availableSubgroupIds = $aTRepo->getAvailableSubgroupIds($model->id, $date, $startTime, $endTime, $subgroupIds);

                    if (count($subgroupIds) === count($availableSubgroupIds)) {

                        if (count($spaceIds) > 0) {

                            $availableSpaceIds = $aTRepo->getAvailableSpaceIds($model->id, $date, $startTime, $endTime, $spaceIds);

                            if (count($spaceIds) !== count($availableSpaceIds)) {

                                $spaceNotifyNeeded = true;
                            }
                        }
                    } else {

                        $failedReason = "Subgroups are not available for the planned date and time:
                On " . $date . " From " . $startTimeText . " to " . $endTimeText;
                        $rescheduleAllowed = false;
                    }
                } else {

                    $failedReason = "Lecturer is not available for the planned date and time:
                On " . $date . " From " . $startTimeText . " to " . $endTimeText;
                    $rescheduleAllowed = false;
                }

                $timetableUrl = URL::to("/academic/academic_timetable/view/" . $model->academic_timetable_id);

                $rescheduled = false;
                if ($rescheduleAllowed) {

                    $rescheduled = $this->triggerReschedule($model, $info, $lecturerIds, $spaceIds);

                    if (!$rescheduled) {

                        $failedReason = "Unknown error occurred.";
                    }
                }

                if ($rescheduled) {

                    $subject = "Automated reschedule successfully completed.";
                    if ($spaceNotifyNeeded) {

                        $subject .= "But space allocation required.";
                    }

                    $mailContent = view("academic::academic_timetable_information.approvals.cancel.auto_reschedule_success",
                        compact('record', 'timetableUrl', 'rescheduled', 'spaceNotifyNeeded', 'info'));
                } else {

                    $subject = "Automated reschedule process failed.";
                    $mailContent = view("academic::academic_timetable_information.approvals.cancel.auto_reschedule_failed",
                        compact('record', 'timetableUrl', 'rescheduled', 'failedReason', 'info'));
                }

                if ($cancelledBy) {

                    Notify::send($subject, $mailContent, "", [$cancelledBy]);
                }

                Notify::sendToEmail($subject, $mailContent, ["chamilrupasinghe@gmail.com"]);
            }
        }
    }

    public function triggerReschedule($model, $info, $lecturerIds, $spaceIds): bool
    {
        DB::beginTransaction();
        try {
            $infoModel = $model->replicate();
            $infoModel->slot_status = 1; //set as disabled
            $infoModel->slot_type = 3; //reschedule status
            $infoModel->cancelled_slot_id = $model->id; //cancelled slot id
            $infoModel->tt_date = $info["date"];
            $infoModel->start_time = date("H:i", strtotime($info["start_time"]));
            $infoModel->end_time = date("H:i", strtotime($info["end_time"]));
            $infoModel->hours = Helper::getHourDiff($info["start_time"], $info["end_time"]);
            $infoModel->rescheduled_slot_info = [];

            //save new model
            if ($infoModel->push()) {

                if (count($lecturerIds) > 0) {
                    foreach ($lecturerIds as $lecturerId) {
                        $lecModel = new AcademicTimetableLecturer();

                        $lecModel->academic_timetable_information_id = $infoModel->id;
                        $lecModel->lecturer_id = $lecturerId;

                        $lecModel->save();
                    }
                }

                $ttInfoSubgroups = $model->ttInfoSubgroups()->get();

                if (count($ttInfoSubgroups) > 0) {
                    foreach ($ttInfoSubgroups as $infoSub) {
                        $subModel = $infoSub->replicate();

                        unset($subModel->academic_timetable_lecturer_id);
                        $subModel->academic_timetable_information_id = $infoModel->id;

                        $subModel->push();
                    }
                }

                if (count($spaceIds) > 0) {
                    foreach ($spaceIds as $spaceId) {
                        $spaceModel = new AcademicTimetableSpace();

                        $spaceModel->academic_timetable_information_id = $infoModel->id;
                        $spaceModel->space_id = $spaceId;

                        $spaceModel->save();
                    }
                }
            }

            //set approval records for automated rescheduled slot
            $this->_setApprovalRecords($model, $infoModel);

            //set rescheduled slot id in cancelled slot
            $model->rescheduled_slot_id = $infoModel->id;
            $model->save();

            $rescheduled = true;

            /*$infoModel->load(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
                "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"]);

            $this->setApprovalSteps($infoModel->slot_type);
            $this->setApprovalUrls($infoModel->slot_type);
            $this->setApprovalStatuses($infoModel->slot_type);

            $response = $this->startApprovalProcess($infoModel);

            if ($response["notify"]["status"] === "success") {

                $rescheduled = true;
            } else {
                $rescheduled = false;
            }*/
        } catch (Exception $exception) {

            $rescheduled = false;
        }

        if ($rescheduled) {

            DB::commit();
        } else {

            DB::rollBack();
        }

        return $rescheduled;
    }

    private function _setApprovalRecords($model, $newModel)
    {
        $modelName = $this->getClassName($model);
        $modelHash = $this->generateClassNameHash($modelName);

        $verification = SystemApproval::query()
            ->where("model_hash", $modelHash)
            ->where("model_id", $model->id)
            ->where("approval_step", "pre_approval_hod")
            ->orderBy("created_at", "desc")
            ->first();

        if ($verification) {

            $verification = $verification->replicate();
            $verification->model_id = $newModel->id;
            $verification->push();
        }

        $approval = SystemApproval::query()
            ->where("model_hash", $modelHash)
            ->where("model_id", $model->id)
            ->where("approval_step", "approval")
            ->orderBy("created_at", "desc")
            ->first();

        if ($approval) {

            $approval = $approval->replicate();
            $approval->model_id = $newModel->id;
            $approval->push();
        }
    }

    public function isValidRescheduleSlot($slotId): array
    {
        $slot = AcademicTimetableInformation::query()->find($slotId);

        if ($slot) {

            if ($slot->slot_status === 0 && $slot->slot_type === 2) {

                $response["notify"]["status"] = "success";
            } else {
                $response["notify"]["status"] = "failed";
                $response["notify"]["notify"][] = "Rescheduling requested, cancelled slot is not eligible for rescheduling.";
            }
        } else {
            $response["notify"]["status"] = "failed";
            $response["notify"]["notify"][] = "Rescheduling requested, cancelled slot does not exist.";
        }

        return $response;
    }

    public function validateTimetable($timetable): array
    {
        if ($timetable) {

            if ($timetable->type === 2) {
                $response["notify"]["status"] = "success";
            } else {
                $response["notify"]["status"] = "failed";
                $response["notify"]["notify"][] = "Requested timetable is not an academic timetable.";
                $response["notify"]["notify"][] = "Requested timetable is an master timetable.";
            }
        } else {
            $response["notify"]["status"] = "failed";
            $response["notify"]["notify"][] = "Requested timetable does not exist.";
        }

        return $response;
    }

    public function validateTimeSlot($timetable, $slot): array
    {
        $response = [];
        $response["notify"]["status"] = "success";

        if ($slot["start_time"] != "" && $slot["end_time"] != "") {
            if ($timetable->mode_type === "exam") {

                if (!isset($slot["examType"]["id"])) {

                    $response["notify"]["status"] = "failed";
                    $response["notify"]["notify"][] = "Exam type required since this is an exam timetable";
                }
            } else {

                if (!isset($slot["lecturers"]) && count($slot["lecturers"]) > 0) {

                    $response["notify"]["status"] = "failed";
                    $response["notify"]["notify"][] = "Lecturer selection is required";
                }
            }
        } else {

            $response["notify"]["status"] = "failed";
            $response["notify"]["notify"][] = "Start time and end time required.";
        }

        return $response;
    }

    public static function updateSlotHoursBulk()
    {
        $results = AcademicTimetableInformation::query()
            ->select(["academic_timetable_information_id", "start_time", "end_time"])
            ->where("slot_status", 1)
            ->get()
            ->toArray();

        if (count($results) > 0) {

            foreach ($results as $result) {

                $data = [];
                $data["hours"] = Helper::getHourDiff($result["start_time"], $result["end_time"]);

                AcademicTimetableInformation::query()
                    ->where("academic_timetable_information_id", $result["academic_timetable_information_id"])
                    ->update($data);
            }
        }
    }

    public function sendLectureReminders()
    {
        if (count($this->remindBeforeDays) > 0) {

            $dates = [];
            $daysByDate = [];
            foreach ($this->remindBeforeDays as $days) {

                $date = date("Y-m-d", strtotime("+" . $days . " days"));

                $dates[] = $date;
                $daysByDate[$date] = $days;
            }

            $results = AcademicTimetableLecturer::with([
                "lecturer",
                "timetableInfo",
                "timetableInfo.module",
                "timetableInfo.ttInfoSpaces",
                "timetableInfo.ttInfoSpaces.space",
                "timetableInfo.timetable.course",
                "timetableInfo.timetable.batch",
            ])
                ->whereHas("timetableInfo", function ($query) use ($dates) {

                    $query->where("slot_status", 1)
                        ->whereIn("tt_date", $dates)
                        ->whereHas("module")
                        ->whereHas("timetable", function ($query) {

                            $query->where("status", 1);
                            $query->where("type", 2);
                        });
                })
                ->get()
                ->toArray();

            $lecturerSlots = [];
            if (is_array($results) && count($results) > 0) {

                foreach ($results as $result) {

                    $slot = $result["timetable_info"];
                    $date = $slot["tt_date"];

                    if (!isset($lecturerSlots[$result["lecturer_id"]])) {

                        $lecturerSlots[$result["lecturer_id"]] = [];
                        $lecturerSlots[$result["lecturer_id"]]["lecturer"] = $result["lecturer"];
                        $lecturerSlots[$result["lecturer_id"]]["dates"] = [];
                    }

                    if (!isset($lecturerSlots[$result["lecturer_id"]]["dates"][$date])) {

                        $lecturerSlots[$result["lecturer_id"]]["dates"][$date] = [];
                    }

                    $lecturerSlots[$result["lecturer_id"]]["dates"][$date][] = $slot;
                }
            }

            if (is_array($lecturerSlots) && count($lecturerSlots) > 0) {

                foreach ($lecturerSlots as $lecturerSlot) {

                    $lecturer = $lecturerSlot["lecturer"];
                    $dates = $lecturerSlot["dates"];

                    if (!empty($lecturer)) {

                        foreach ($dates as $date => $slots) {

                            $days = $daysByDate[$date];

                            if ($days === 0) {

                                $title = "Your lectures list for today.";
                                $viewPath = "academic::academic_timetable_information.reminders.daily_lecture_reminder";
                            } else {

                                $title = "Your lecture reminder before " . $days . " days";
                                $viewPath = "academic::academic_timetable_information.reminders.days_before_lecture_reminder";
                            }

                            $title .= " | " . date("Y-m-d", strtotime($date));
                            $content = view($viewPath, compact('date', 'lecturer', 'slots'));

                            if ($lecturer["admin_id"] === null) {

                                Notify::sendToEmail($title, $content, [$lecturer["email"]]);
                            } else {

                                Notify::send($title, $content, "", [$lecturer["admin_id"]]);
                            }
                        }
                    }
                }
            }
        }
    }

    public function sendMonthlyLectureSchedule()
    {
        $startDate = date("Y-m-01", strtotime("next month"));
        $endDate = date("Y-m-t", strtotime("next month"));

        $results = AcademicTimetableLecturer::with([
            "lecturer",
            "timetableInfo",
            "timetableInfo.module",
            "timetableInfo.ttInfoSpaces",
            "timetableInfo.ttInfoSpaces.space",
            "timetableInfo.timetable.course",
            "timetableInfo.timetable.batch",
        ])
            ->whereHas("timetableInfo", function ($query) use ($startDate, $endDate) {

                $query
                    ->where("slot_status", 1)
                    ->whereBetween("tt_date", [$startDate, $endDate])
                    ->whereHas("module")
                    ->whereHas("timetable", function ($query) {

                        $query->where("status", 1);
                        $query->where("type", 2);
                    });
            })
            ->get()
            ->toArray();

        $lecturerSlots = [];
        if (is_array($results) && count($results) > 0) {

            foreach ($results as $result) {

                $slot = $result["timetable_info"];

                if (!isset($lecturerSlots[$result["lecturer_id"]])) {

                    $lecturerSlots[$result["lecturer_id"]] = [];
                    $lecturerSlots[$result["lecturer_id"]]["lecturer"] = $result["lecturer"];
                    $lecturerSlots[$result["lecturer_id"]]["slots"] = [];
                }

                $lecturerSlots[$result["lecturer_id"]]["slots"][] = $slot;
            }
        }

        if (is_array($lecturerSlots) && count($lecturerSlots) > 0) {

            foreach ($lecturerSlots as $lecturerSlot) {

                $lecturer = $lecturerSlot["lecturer"];
                $slots = $lecturerSlot["slots"];

                if (!empty($lecturer)) {

                    $title = "Your lecture schedule for next month | From " . $startDate . " - " . $endDate;
                    $content = view("academic::academic_timetable_information.reminders.monthly_lecture_schedule",
                        compact('lecturer', 'slots'));

                    if ($lecturer["admin_id"] === null) {

                        Notify::sendToEmail($title, $content, [$lecturer["email"]]);
                    } else {

                        Notify::send($title, $content, "", [$lecturer["admin_id"]]);
                    }
                }
            }
        }
    }

    public static function triggerManualSTLS($timetableId)
    {
        /*
         * Tinker commands
         * AcademicTimetableInformationRepository::triggerManualSTLS(999999999);
         */
        $model = AcademicTimetable::query()->find($timetableId);
        self::sendTimetableLectureSchedule($model);
    }

    public static function sendTimetableLectureSchedule($model)
    {
        $timetableId = $model->id;
        $results = AcademicTimetableLecturer::with([
            "lecturer",
            "timetableInfo",
            "timetableInfo.module",
            "timetableInfo.ttInfoSpaces",
            "timetableInfo.ttInfoSpaces.space",
            "timetableInfo.timetable.course",
            "timetableInfo.timetable.batch",
        ])
            ->whereHas("timetableInfo", function ($query) use ($timetableId) {

                $query
                    ->where("academic_timetable_id", $timetableId)
                    ->where("slot_status", 1);
            })
            ->get()
            ->toArray();

        $lecturerSlots = [];
        if (is_array($results) && count($results) > 0) {

            foreach ($results as $result) {

                $slot = $result["timetable_info"];

                if (!isset($lecturerSlots[$result["lecturer_id"]])) {

                    $lecturerSlots[$result["lecturer_id"]] = [];
                    $lecturerSlots[$result["lecturer_id"]]["lecturer"] = $result["lecturer"];
                    $lecturerSlots[$result["lecturer_id"]]["slots"] = [];
                }

                $lecturerSlots[$result["lecturer_id"]]["slots"][] = $slot;
            }
        }

        if (is_array($lecturerSlots) && count($lecturerSlots) > 0) {

            $timetable = $model->toArray();

            foreach ($lecturerSlots as $lecturerSlot) {

                $lecturer = $lecturerSlot["lecturer"];
                $slots = $lecturerSlot["slots"];

                if (!empty($lecturer)) {

                    $title = "Your lecture schedule for timetable | " . $model->name;
                    $content = view("academic::academic_timetable_information.reminders.timetable_lecture_schedule",
                        compact('lecturer', 'slots', 'timetable'));

                    if ($lecturer["admin_id"] === null) {

                        Notify::sendToEmail($title, $content, [$lecturer["email"]]);
                    } else {

                        Notify::queue($title, $content, "", [$lecturer["admin_id"]]);
                    }
                }
            }
        }
    }
}
