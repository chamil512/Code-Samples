<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicCalendar;
use Modules\Academic\Entities\SubgroupDeactivateTemporary;
use Modules\Settings\Entities\CalendarEvent;
use Modules\Settings\Repositories\CalendarEventRepository;

class AcademicCalendarRepository extends BaseRepository
{
    public string $modelType = "academic_calendar";
    public bool $isValidDates = false;
    public string $statusField = "ac_status";
    public string $completeStatusField = "complete_status";

    public array $statuses = [
        ["id" => "1", "name" => "Enabled", "label" => "success"],
        ["id" => "0", "name" => "Disabled", "label" => "danger"]
    ];

    public array $completeStatuses = [
        ["id" => "0", "name" => "Pending Start", "label" => "warning"],
        ["id" => "1", "name" => "Started", "label" => "info"],
        ["id" => "2", "name" => "Completed", "label" => "success"],
    ];

    public array $approvalStatuses = [
        ["id" => "", "name" => "Not Sent for Approval", "label" => "info"],
        ["id" => "0", "name" => "Verification Pending", "label" => "warning"],
        ["id" => "3", "name" => "Verified & Pending Approval", "label" => "success"],
        ["id" => "4", "name" => "Verification Declined", "label" => "danger"],
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
            "route" => "/academic/academic_calendar/verification",
            "permissionRoutes" => [],
        ],
        [
            "step" => "approval",
            "approvedStatus" => 1,
            "declinedStatus" => 2,
            "route" => "/academic/academic_calendar/approval",
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

            case "approval" :
                $text = $model->name . " approval.";
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
        $url = URL::to("/academic/academic_calendar/view/" . $model->id);

        return view("academic::academic_calendar.approvals." . $step, compact('record', 'url'));
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

    /**
     * @throws Exception
     */
    protected function onUpdateStatusSuccess($model, $statusField, $status)
    {
        if ($statusField === $this->completeStatusField) {

            $this->_updateSDT($model);
        }

        parent::onUpdateStatusSuccess($model, $statusField, $status);
    }

    /**
     * @throws Exception
     */
    private function _updateSDT($model)
    {
        $sDTModel = SubgroupDeactivateTemporary::query()->where("academic_calendar_id", $model->id)->first();

        if ($sDTModel) {

            //check if this record's complete status has been rolled back
            if ($model->{$this->completeStatusField} !== 2) {

                //then check for the temporary record's status is still pending
                //if yes, then remove it
                if ($sDTModel->status === 0) {

                    $sDTModel->delete();
                }
            }
        } else {

            if ($model->{$this->completeStatusField} === 2) {

                $sDTModel = new SubgroupDeactivateTemporary();
                $sDTModel->academic_calendar_id = $model->id;
                $sDTModel->status = 0;

                $sDTModel->save();
            }
        }
    }

    public function updateCalendar($model, $dateFields)
    {
        $modelId = $model->id;

        //get current data
        $currentData = $this->getCalendarData($modelId);

        $calEvRepo = new CalendarEventRepository();
        $modelTypeHash = $calEvRepo->generateModelTypeHash($this->modelType);

        $updatingFields = [];
        foreach ($dateFields as $dF) {
            if (isset($dF["value"]) && $dF["value"] != "") {
                $eventType = $dF["field"];
                $eventTypeHash = $calEvRepo->generateEventTypeHash($eventType);

                if (isset($currentData[$eventTypeHash])) {
                    $event = $currentData[$eventTypeHash];
                    $eventId = $event["event_id"];

                    $modelCE = CalendarEvent::query()->find($eventId);

                    $updatingFields[] = $eventTypeHash;
                } else {
                    $modelCE = new CalendarEvent();
                }

                $modelCE->model_id = $model->id;
                $modelCE->model_type = $modelTypeHash;
                $modelCE->event_type = $eventTypeHash;
                $modelCE->event_name = $dF["label"] . " of " . $model->name;
                $modelCE->event_date = $dF["value"];
                $modelCE->full_day_event = "1";
                //$modelCE->event_status = "0";
                $modelCE->event_status = "1"; //Until approval process is implemented status will be saved as 1

                $this->saveModel($modelCE);
            }
        }

        $currentFields = $this->getCurrentFields($currentData);
        $notUpdatingFields = array_diff($currentFields, $updatingFields);

        //delete not updating records
        CalendarEvent::query()->where("model_id", $modelId)->where("model_type", $modelTypeHash)->whereIn("event_type", $notUpdatingFields)->delete();
    }

    function updateCalendarItemStatus($model, $status)
    {
        $modelId = $model->model_id;

        $calEvRepo = new CalendarEventRepository();
        $modelTypeHash = $calEvRepo->generateModelTypeHash($this->modelType);

        $data = [];
        $data["event_status"] = $status;

        CalendarEvent::update(["model_id" => $modelId, "model_type" => $modelTypeHash], $data);
    }

    /**
     * Get current data related to academic calendar model which have been saved in calendar events table
     * @param $modelId
     * @return array
     */
    function getCalendarData($modelId): array
    {
        $calEvRepo = new CalendarEventRepository();
        $modelType = $calEvRepo->generateModelTypeHash($this->modelType);

        $results = CalendarEvent::query()->where("model_id", $modelId)->where("model_type", $modelType)->get()->toArray();

        $data = [];
        if ($results && count($results) > 0) {
            foreach ($results as $result) {
                $data[$result["event_type"]] = $result;
            }
        }

        return $data;
    }

    /**
     * Get list of event types of current data related to academic calendar model which have been saved in calendar events table
     * @param $results
     * @return array
     */
    function getCurrentFields($results): array
    {
        $data = [];
        if ($results && count($results) > 0) {
            foreach ($results as $result) {
                $data[] = $result["event_type"];
            }
        }

        return $data;
    }

    function getValidatedDateFields(): array
    {
        $dateFields = $this->getCalendarDateFields();

        $errors = 0;
        $notify = [];
        $del = "-";
        foreach ($dateFields as $key => $dateField) {
            $field = $dateField["field"];
            $label = $dateField["label"];
            $required = $dateField["required"];
            $value = request()->post($field);

            if ($value != "") {
                $valueExp = explode($del, $value);

                if (count($valueExp) > 0 && checkdate($valueExp[1], $valueExp[2], $valueExp[0])) {
                    $dateField["value"] = $value;
                    $dateFields[$key] = $dateField;
                } else {
                    $errors++;
                    if ($required) {
                        $notify[] = "Valid " . $label . " required.";
                    } else {
                        $notify[] = $label . " should be a valid date.";
                    }
                }
            } else {
                if ($required) {
                    $errors++;
                    $notify[] = $label . " required.";
                }
            }
        }

        if ($errors > 0) {
            $this->isValidDates = false;
            $response["status"] = "failed";
            $response["notify"] = $notify;

            return $response;
        } else {
            $this->isValidDates = true;
        }

        return $dateFields;
    }

    public function getCalendarDateFields(): array
    {
        $data = [];
        $data[] = ["field" => "academic_start_date", "label" => "Academic Start Date", "required" => true];
        $data[] = ["field" => "academic_end_date", "label" => "Academic End Date", "required" => true];
        $data[] = ["field" => "mid_vac_start_date", "label" => "Mid Vacation Start Date", "required" => false];
        $data[] = ["field" => "mid_vac_end_date", "label" => "Mid Vacation End Date", "required" => false];
        $data[] = ["field" => "exam_start_date", "label" => "Examination Start Date", "required" => true];
        $data[] = ["field" => "exam_end_date", "label" => "Examination End Date", "required" => true];
        $data[] = ["field" => "ca_exam_start_date", "label" => "CA Examination Start Date", "required" => false];
        $data[] = ["field" => "ca_exam_end_date", "label" => "CA Examination End Date", "required" => false];
        $data[] = ["field" => "vac_start_date", "label" => "Vacation Start Date", "required" => false];
        $data[] = ["field" => "vac_end_date", "label" => "Vacation End Date", "required" => false];
        $data[] = ["field" => "emg_vac_start_date", "label" => "Emergency Vacation Start Date", "required" => false];
        $data[] = ["field" => "emg_vac_end_date", "label" => "Emergency Vacation End Date", "required" => false];

        return $data;
    }

    public function getEmptyData(): array
    {
        $dateFields = $this->getCalendarDateFields();

        $data = [];
        foreach ($dateFields as $dateField) {
            $field = $dateField["field"];
            $data[$field] = "";
        }

        return $data;
    }

    public function getDataByFields($evData): array
    {
        $dateFields = $this->getCalendarDateFields();

        $data = [];
        $calEvRepo = new CalendarEventRepository();
        foreach ($dateFields as $dateField) {
            $field = $dateField["field"];
            $eventTypeHash = $calEvRepo->generateEventTypeHash($field);

            $data[$field] = "";
            if (isset($evData[$eventTypeHash])) {
                $data[$field] = $evData[$eventTypeHash]["event_date"];
            }
        }

        return $data;
    }

    public function isOtherCalendarExist($model): bool
    {
        $result = AcademicCalendar::query()
            ->where("academic_calendar_id", "!=", $model->academic_calendar_id)
            ->where("course_id", $model->course_id)
            ->where("academic_year_id", $model->academic_year_id)
            ->where("semester_id", $model->semester_id)
            ->where("batch_id", $model->batch_id)
            ->first();

        if ($result) {
            return true;
        }

        return false;
    }

    public function getAcademicCalendar($courseId, $academicYearId, $semesterId, $batchId)
    {
        $record = AcademicCalendar::query()
            ->select("academic_calendar_id")
            ->where("ac_status", "1")
            ->where("course_id", $courseId)
            ->where("academic_year_id", $academicYearId)
            ->where("semester_id", $semesterId)
            ->where("batch_id", $batchId)
            ->first();

        $data = false;
        if ($record) {
            $calendarEvents = $this->getCalendarData($record->id);
            $calendarEvents = $this->getDataByFields($calendarEvents);

            $data = $calendarEvents;
        }

        return $data;
    }

    public function getAcademicCalendarInfo($id)
    {
        $record = AcademicCalendar::query()->find($id);

        $data = false;
        if ($record) {
            $calendarEvents = $this->getCalendarData($record->id);
            $calendarEvents = $this->getDataByFields($calendarEvents);

            $data = $calendarEvents;
        }

        return $data;
    }

    public function getFilteredData($relations): array
    {
        $request = request();

        $facultyId = $request->post("faculty_id");
        $deptId = $request->post("dept_id");
        $courseIds = $request->post("course_id");
        $batchIds = $request->post("batch_id");
        $dateFrom = $request->post("date_from");
        $dateTill = $request->post("date_till");

        $query = AcademicCalendar::query()
            ->where("ac_status", 1);

        if (is_array($relations) && count($relations) > 0) {

            $query->with($relations);
        }

        if ($facultyId) {

            $query->whereIn("faculty_id", $facultyId);
        }

        if ($deptId) {

            $query->whereIn("dept_id", $deptId);
        }

        if ($courseIds) {

            $query->whereIn("course_id", $courseIds);
        }

        if ($batchIds) {

            $query->whereIn("batch_id", $batchIds);
        }

        $calEvRepo = new CalendarEventRepository();
        $aSDET = $calEvRepo->generateEventTypeHash("academic_start_date");
        $aEDET = $calEvRepo->generateEventTypeHash("academic_end_date");

        if ($dateFrom !== null && $dateTill !== null) {

            $query->whereHas("calendarEvents", function ($query) use($dateFrom, $dateTill, $aSDET, $aEDET) {

                $query->where(function ($query) use($dateFrom, $dateTill, $aSDET) {

                    $query->where("event_type", $aSDET);
                    $query->whereDate("event_date", ">=", $dateFrom);
                    $query->whereDate("event_date", "<=", $dateTill);
                })->orWhere(function ($query) use($dateFrom, $dateTill, $aEDET) {

                    $query->where("event_type", $aEDET);
                    $query->whereDate("event_date", ">=", $dateFrom);
                    $query->whereDate("event_date", "<=", $dateTill);
                });
            });
        }

        return $query->get()->toArray();
    }

    /**
     * @param $model
     * @param bool $datesOnly
     * @return array
     */
    public function getDates($model, bool $datesOnly = false): array
    {
        $model->load(["dates"]);
        $records = $model->dates->toArray();

        $orderColumn = array_column($records, 'date');
        array_multisort($orderColumn, SORT_ASC, $records);

        $dates = [];
        if (is_array($records) && count($records) > 0) {
            foreach ($records as $record) {

                if ($datesOnly) {

                    $dates[] = $record["date"];
                } else {

                    $date = [];
                    $date["id"] = $record["id"];
                    $date["date"] = $record["date"];

                    $dates[] = $date;
                }
            }
        }

        return $dates;
    }

    /*public function updateACBulk()
    {
        $records = AcademicCalendar::withTrashed()
            ->with(["course", "academicYear", "semester", "batch"])
            ->select(["academic_calendar_id", "course_id", "academic_year_id", "semester_id", "batch_id"])
            ->get()->toArray();

        if (count($records) > 0) {

            foreach ($records as $record) {

                $deptId = $this->getDepartmentIdFromCourseId($record["course_id"]);
                $facultyId = $this->getFacultyIdFromDepartmentId($deptId);

                $data = [];
                $data["dept_id"] = $deptId;
                $data["faculty_id"] = $facultyId;
                $data["name"] = $record["base_name"];

                AcademicCalendar::withTrashed()
                    ->where("academic_calendar_id", $record["id"])
                    ->update($data);
            }
        }
    }*/
}
