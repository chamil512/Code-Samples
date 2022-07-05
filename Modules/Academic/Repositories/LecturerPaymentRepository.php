<?php

namespace Modules\Academic\Repositories;

use App\Helpers\Helper;
use App\Repositories\BaseRepository;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicTimetableInformation;
use Modules\Academic\Entities\Lecturer;
use Modules\Academic\Entities\LecturerPayment;
use Modules\Academic\Entities\LecturerPaymentMethod;

/**
 * Class LecturerPaymentRepository
 * @package Modules\Academic\Repositories
 */
class LecturerPaymentRepository extends BaseRepository
{
    public string $statusField = "status";
    private int $paidHours = 0;
    private int $paidMinutes = 0;

    public array $statuses = [
        ["id" => "0", "name" => "Pending Approval", "label" => "warning"],
        ["id" => "1", "name" => "Approved", "label" => "success"],
        ["id" => "2", "name" => "Partially Approved", "label" => "primary"],
        ["id" => "3", "name" => "Rejected", "label" => "danger"]
    ];

    public array $paidStatusOptions = [
        ["id" => "0", "name" => "Pending", "label" => "warning"],
        ["id" => "1", "name" => "Paid", "label" => "success"],
        ["id" => "2", "name" => "Rejected", "label" => "danger"]
    ];

    public array $approvalStatuses = [
        ["id" => "", "name" => "Not Sent for Approval", "label" => "info"],
        ["id" => "0", "name" => "Verification Pending", "label" => "warning"],
        ["id" => "1", "name" => "Approved", "label" => "success"],
        ["id" => "2", "name" => "Declined", "label" => "danger"],
        /*["id" =>"3", "name" =>"Verified & Pending Pre Approval", "label" => "success"],
        ["id" =>"4", "name" =>"Verification Declined", "label" => "danger"],
        ["id" =>"5", "name" =>"Verified & Pending Approval", "label" => "success"],
        ["id" =>"6", "name" =>"Pre Approval Declined", "label" => "danger"],
        ["id" =>"1", "name" =>"Approved", "label" => "success"],
        ["id" =>"2", "name" =>"Declined", "label" => "danger"],*/
    ];

    /*
     * Approval properties and methods starts
     */
    public string $approvalField = "approval_status";
    public $approvalDefaultStatus = "0";
    protected array $approvalSteps = [
        [
            "step" => "verification",
            "approvedStatus" => 1,
            "declinedStatus" => 2,
            "route" => "/academic/lecturer_payment/verification",
            "permissionRoutes" => [],
            "approvedSubmitStatuses" => [1, 2], //values which are submitting from form will be validated against these values
            "approvalStatuses" => [
                ["id" => "0", "name" => "Pending Approval", "label" => "warning"],
                ["id" => "1", "name" => "Approved", "label" => "success"],
                ["id" => "2", "name" => "Partially Approved", "label" => "primary"],
                ["id" => "3", "name" => "Rejected", "label" => "danger"]
            ],
        ],
        /*[
            "step" => "verification",
            "approvedStatus" => 3,
            "declinedStatus" => 4,
            "route" => "/academic/lecturer_payment/verification",
            "permissionRoutes" => [],
            "approvedSubmitStatuses" => [1, 2], //values which are submitting from form will be validated against these values
            "approvalStatuses" => [
                ["id" => "0", "name" => "Pending Approval", "label" => "warning"],
                ["id" => "1", "name" => "Approved", "label" => "success"],
                ["id" => "2", "name" => "Partially Approved", "label" => "primary"],
                ["id" => "3", "name" => "Rejected", "label" => "danger"]
            ],
        ],
        [
            "step" => "pre_approval",
            "approvedStatus" => 5,
            "declinedStatus" => 6,
            "route" => "/academic/lecturer_payment/pre_approval",
            "permissionRoutes" => [],
        ],
        [
            "step" => "approval",
            "approvedStatus" => 1,
            "declinedStatus" => 2,
            "route" => "/academic/lecturer_payment/approval",
            "permissionRoutes" => [],
        ]*/
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
                $text = $model->name . " | approval.";
                break;

            /*case "verification" :
                $text = $model->name . " | verification.";
                break;

            case "pre_approval" :
                $text = $model->name . " | pre approval.";
                break;

            case "approval" :
                $text = $model->name . " | approval.";
                break;*/

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
        $url = URL::to("/academic/lecturer_payment/view/" . $model->id);

        $record["slotMinutes"] = Helper::convertMinutesToHumanTime($record["slot_minutes"]);
        $record["actualMinutes"] = Helper::convertMinutesToHumanTime($record["actual_minutes"]);

        return view("academic::lecturer_payment.approvals." . $step, compact('record', 'url'));
    }

    protected function onApproved($model, $step, $previousStatus)
    {
        if ($step === "verification") {

            $model->remarks = request()->post("remarks");
            $model->{$this->statusField} = intval(request()->post($this->statusField));

            if ($model->payment_type === 1) {
                $model->lecturer_payment_method_id = request()->post("lecturer_payment_method_id");

                $lPMethod = LecturerPaymentMethod::query()->find($model->lecturer_payment_method_id);

                if ($lPMethod) {

                    $lPMethod = $lPMethod->toArray();
                    $model->hourly_rate = $lPMethod["hourly_rate"];
                }
            }

            if ($model->{$this->statusField} === 1) {

                $model->approved_total = $this->getFullApprovalAmount($model);
            } elseif ($model->{$this->statusField} === 2) {

                $model->approved_total = $this->getPartialApprovalAmount($model);
            }

            if ($model->payment_type === 1 || $model->payment_type === 2) {

                $model->paid_hours = $this->paidHours;
                $model->paid_minutes = $this->paidMinutes;
                $model->calculated_total = $this->calSlotPayment($model->actual_minutes, $model->hourly_rate);
            }

            $model->save();
        }
    }

    protected function onDeclined($model, $step, $previousStatus)
    {
        if ($step === "verification") {

            $model->{$this->statusField} = 3;
            $model->save();
        }
    }

    /*
     * Approval properties and methods ends
     */

    public function getRecordPrepared($record)
    {
        if (isset($record["slot_minutes"])) {

            $record["slotMinutes"] = Helper::convertMinutesToHumanTime($record["slot_minutes"]);
        }

        if (isset($record["actual_minutes"])) {

            $record["actualMinutes"] = Helper::convertMinutesToHumanTime($record["actual_minutes"]);
        }

        return parent::getRecordPrepared($record);
    }

    public function getPartialApprovalAmount($model): string
    {
        $total = 0;

        if ($model->payment_type === 1 || $model->payment_type === 2) {

            if ($model->slot_minutes > $model->actual_minutes) {
                $this->paidMinutes = $model->actual_minutes;
            } else {
                $this->paidMinutes = $model->slot_minutes;
            }

            if ($model->slot_hours > $model->actual_hours) {
                $this->paidHours = $model->actual_hours;
            } else {
                $this->paidHours = $model->slot_hours;
            }

            $total = $this->calSlotPayment($this->paidMinutes, $model->hourly_rate);
        } else if ($model->payment_type === 3) {

            $paymentPlan = $model->paymentPlan;
            $fixedAmount = $paymentPlan["fixed_amount"];
            $calculatedTotal = $model->calculated_total;

            if ($fixedAmount < $calculatedTotal) {
                $approvedTotal = $fixedAmount;
            } else {
                $approvedTotal = $calculatedTotal;
            }

            $total = $approvedTotal;
        }

        return number_format($total, 2, ".", "");
    }

    public function getFullApprovalAmount($model): string
    {
        $total = 0;

        if ($model->payment_type === 1 || $model->payment_type === 2) {

            if ($model->slot_minutes > $model->actual_minutes) {
                $this->paidMinutes = $model->slot_minutes;
            } else {
                $this->paidMinutes = $model->actual_minutes;
            }

            if ($model->slot_hours > $model->actual_hours) {
                $this->paidHours = $model->slot_hours;
            } else {
                $this->paidHours = $model->actual_hours;
            }

            $total = $this->calSlotPayment($this->paidMinutes, $model->hourly_rate);
        } else if ($model->payment_type === 3) {

            $paymentPlan = $model->paymentPlan;
            $fixedAmount = $paymentPlan["fixed_amount"];
            $calculatedTotal = $model->calculated_total;

            if ($fixedAmount > $calculatedTotal) {
                $approvedTotal = $fixedAmount;
            } else {
                $approvedTotal = $calculatedTotal;
            }

            $total = $approvedTotal;
        }

        return number_format($total, 2, ".", "");
    }

    public function displayPaymentInfoAs()
    {
        return view("academic::lecturer_payment.datatable.payment_info_ui");
    }

    public function displayTimeHoursAs()
    {
        $rosterUrl = URL::to("/academic/lecturer_roster/");
        return view("academic::lecturer_payment.datatable.time_hours_info_ui", compact('rosterUrl'));
    }

    /**
     * @param $lecturerId
     * @param $timetableInfoId
     * @param $timeInfo
     * @param $remarks
     */
    public static function triggerPayment($lecturerId, $timetableInfoId, $timeInfo, $remarks)
    {
        $lPRepo = new LecturerPaymentRepository();
        $lPRepo->sendForApproval($lecturerId, $timetableInfoId, $timeInfo, $remarks);
    }

    /**
     * @param $lecturerId
     * @param $timetableInfoId
     * @param $timeInfo
     * @param $remarks
     */
    public function sendForApproval($lecturerId, $timetableInfoId, $timeInfo, $remarks)
    {
        //get timeslot info
        $timeSlot = AcademicTimetableInformation::with(["timetable", "module", "deliveryMode", "deliveryModeSpecial", "ttInfoLecturers"])->find($timetableInfoId);

        try {

            if ($timeSlot) {

                $lecturers = [];
                if (isset($timeSlot->ttInfoLecturers)) {
                    $lecturers = $timeSlot->ttInfoLecturers->keyBy("lecturer_id")->toArray();
                    $lecturers = array_keys($lecturers);
                }

                if (in_array($lecturerId, $lecturers)) {

                    if ($timeSlot->payable_status === 1) {

                        //get lecturer
                        $lecturer = Lecturer::query()->find($lecturerId);

                        if ($lecturer) {

                            $timetable = $timeSlot->timetable;

                            if ($timetable && $timetable->type === 2) {

                                $courseId = $timetable->course_id;
                                $date = $timeSlot->tt_date;

                                $paymentPlan = LecturerPaymentPlanRepository::getLecturerHourlyPaymentPlan($lecturerId, $courseId, $date);

                                if ($paymentPlan) {
                                    $actualHours = Helper::getHourDiff($timeInfo["start_time"], $timeInfo["end_time"]);
                                    $actualMinutes = Helper::getMinutesDiff($timeInfo["start_time"], $timeInfo["end_time"]);
                                    $slotMinutes = Helper::getMinutesDiff($timeSlot->start_time, $timeSlot->end_time);

                                    //check if this record already exists
                                    $model = LecturerPayment::query()
                                        ->where("lecturer_id", $lecturerId)
                                        ->where("academic_timetable_information_id", $timetableInfoId)
                                        ->first();

                                    $allowToProceed = false;
                                    if ($model) {

                                        if ($model->status === 0 || $model->status === 3) {

                                            //approval process has not started/completed yet
                                            $allowToProceed = true;
                                        }
                                    } else {

                                        $allowToProceed = true;
                                        $model = new LecturerPayment();
                                    }

                                    if ($allowToProceed) {

                                        $model->lecturer_payment_plan_id = $paymentPlan["id"];
                                        $model->academic_timetable_information_id = $timetableInfoId;
                                        $model->payment_type = $paymentPlan["payment_type"];
                                        $model->lecturer_id = $lecturerId;
                                        $model->course_id = $timetable->course_id;
                                        $model->batch_id = $timetable->batch_id;
                                        $model->academic_year_id = $timetable->academic_year_id;
                                        $model->semester_id = $timetable->semester_id;
                                        $model->module_id = $timeSlot->module_id;
                                        $model->remarks = $remarks;
                                        $model->payment_date = $timeSlot->tt_date;
                                        $model->slot_start_time = $timeSlot->start_time;
                                        $model->slot_end_time = $timeSlot->end_time;
                                        $model->slot_hours = $timeSlot->hours;
                                        $model->slot_minutes = $slotMinutes;
                                        $model->start_time = $timeInfo["start_time"];
                                        $model->end_time = $timeInfo["end_time"];
                                        $model->actual_hours = $actualHours;
                                        $model->actual_minutes = $actualMinutes;

                                        if ($paymentPlan["payment_type"] == "2") {

                                            $model->hourly_rate = $paymentPlan["special_rate"];
                                        } else {

                                            //get matching payment method for this lecturer
                                            $paymentMethod = LecturerPaymentMethodRepository::getLecturerPaymentMethod($timetable->course_id, $lecturer->qualification_id);

                                            if ($paymentMethod) {
                                                $model->hourly_rate = $paymentMethod["hourly_rate"];
                                                $model->lecturer_payment_method_id = $paymentMethod["id"];
                                            }
                                        }

                                        if ($model->save()) {
                                            $model->load(["lecturer", "course", "batch", "module", "paymentMethod"]);
                                            $this->startApprovalProcess($model);
                                        }
                                    }
                                }
                            }
                        }
                    }

                    try {

                        LecturerWorkScheduleRepository::addWorkSchedule($lecturerId, $timeSlot, $timeInfo, $remarks);
                    } catch (Exception $exception) {

                    }
                }
            }
        } catch (Exception $exception) {

        }
    }

    /**
     * @param $paymentPlan
     * @param $startDate
     * @param $endDate
     * @param $month
     */
    public function sendFixedPaymentForApproval($paymentPlan, $startDate, $endDate, $month)
    {
        $lecturerId = $paymentPlan["lecturer_id"];
        $fixedAmount = $paymentPlan["fixed_amount"];

        $calculatedTotal = LecturerRosterShiftRepository::getCalculatedTotal($lecturerId, $fixedAmount, $startDate, $endDate);

        $model = new LecturerPayment();
        $model->lecturer_payment_plan_id = $paymentPlan["id"];
        $model->payment_type = $paymentPlan["payment_type"];
        $model->lecturer_id = $paymentPlan["lecturer_id"];
        $model->payment_month = $month;
        $model->calculated_total = $calculatedTotal;
        $model->slot_minutes = null;
        $model->actual_minutes = null;

        if ($model->save()) {
            $this->startApprovalProcess($model);
        }
    }

    public function triggerApprovalBulk()
    {
        $results = LecturerPayment::with(["lecturer", "course", "batch", "module", "paymentMethod"])->get();

        if ($results) {

            foreach ($results as $result) {

                $this->startApprovalProcess($result);
            }
        }
    }

    public function calSlotPayment($minutes, $hourlyRate)
    {
        return ($minutes / 60) * $hourlyRate;
    }

    public function bulkUpdate()
    {
        $records = LecturerPayment::withTrashed()->get()
            ->where("approval_status", 1)
            ->where("approved_total", "0.00");

        if ($records) {

            foreach ($records as $record) {

                $record->slot_minutes = Helper::getMinutesDiff($record->slot_start_time, $record->slot_end_time);
                $record->actual_minutes = Helper::getMinutesDiff($record->start_time, $record->end_time);

                if ($record->payment_type === 1 || $record->payment_type === 2) {

                    if ($record->{$this->statusField} === 1) {

                        $record->approved_total = $this->getFullApprovalAmount($record);
                    } elseif ($record->{$this->statusField} === 2) {

                        $record->approved_total = $this->getPartialApprovalAmount($record);
                    }

                    if ($record->{$this->statusField} === 1 || $record->{$this->statusField} === 2) {

                        $record->paid_hours = $this->paidHours;
                        $record->paid_minutes = $this->paidMinutes;
                        $record->calculated_total = $this->calSlotPayment($record->actual_minutes, $record->hourly_rate);
                    }
                }

                $record->save();
            }
        }
    }

    /**
     * @param $relations
     * @return array
     */
    public function getFilteredData($relations): array
    {
        $request = request();

        $facultyIds = $request->post("faculty_id");
        $deptIds = $request->post("dept_id");
        $courseIds = $request->post("course_id");
        $lecturerIds = $request->post("lecturer_id");
        $batchIds = $request->post("batch_id");
        $moduleIds = $request->post("module_id");
        $paymentMethodIds = $request->post("lecturer_payment_method_id");
        $paymentTypes = $request->post("payment_type");
        $dateFrom = $request->post("date_from");
        $dateTill = $request->post("date_till");
        $approvalStatuses = $request->post("approval_status");
        $paidStatuses = $request->post("paid_status");

        $query = LecturerPayment::query();

        if (is_array($relations) && count($relations) > 0) {

            $query->with($relations);
        }

        if ($facultyIds) {

            $query->where(function ($query) use($facultyIds, $paymentTypes) {

                if ($paymentTypes === null) {

                    $paymentTypes = [];
                }

                $pTCount = count($paymentTypes);

                if ($pTCount === 0 || ($pTCount > 1 && in_array(3, $paymentTypes))) {

                    //Matches faculty ID of both lecturer and timeslot
                    $query->whereHas("course", function ($query) use ($facultyIds) {

                        $query->whereHas("department", function ($query) use ($facultyIds) {

                            $query->whereIn("faculty_id", $facultyIds);
                        });
                    });

                    $query->orWhereHas("lecturer", function ($query) use ($facultyIds) {

                        $query->whereIn("faculty_id", $facultyIds);
                    });
                } elseif ($pTCount === 1 || in_array(3, $paymentTypes)) {

                    //Matches faculty ID of lecturer only
                    $query->whereHas("lecturer", function ($query) use ($facultyIds) {

                        $query->whereIn("faculty_id", $facultyIds);
                    });
                } else {

                    //Matches faculty ID of timeslot only
                    $query->whereHas("course", function ($query) use ($facultyIds) {

                        $query->whereHas("department", function ($query) use ($facultyIds) {

                            $query->whereIn("faculty_id", $facultyIds);
                        });
                    });
                }
            });
        }

        if ($deptIds) {

            $query->where(function ($query) use($deptIds, $paymentTypes) {

                if ($paymentTypes === null) {

                    $paymentTypes = [];
                }

                $pTCount = count($paymentTypes);

                if ($pTCount === 0 || ($pTCount > 1 && in_array(3, $paymentTypes))) {

                    //Matches faculty ID of both lecturer and timeslot
                    $query->whereHas("course", function ($query) use ($deptIds) {

                        $query->whereIn("dept_id", $deptIds);
                    });

                    $query->orWhereHas("lecturer", function ($query) use ($deptIds) {

                        $query->whereIn("dept_id", $deptIds);
                    });
                } elseif ($pTCount === 1 || in_array(3, $paymentTypes)) {

                    //Matches faculty ID of lecturer only
                    $query->whereHas("lecturer", function ($query) use ($deptIds) {

                        $query->whereIn("dept_id", $deptIds);
                    });
                } else {

                    //Matches faculty ID of timeslot only
                    $query->whereHas("course", function ($query) use ($deptIds) {

                        $query->whereIn("dept_id", $deptIds);
                    });
                }
            });
        }

        if ($courseIds) {

            $query->whereIn("course_id", $courseIds);
        }

        if ($batchIds) {

            $query->whereIn("batch_id", $batchIds);
        }

        if ($moduleIds) {

            $query->whereIn("module_id", $moduleIds);
        }

        if ($paymentTypes) {

            $query->whereIn("payment_type", $paymentTypes);
        }

        if ($lecturerIds) {

            $query->whereIn("lecturer_id", $lecturerIds);
        }

        if ($paymentMethodIds) {

            $query->whereIn("lecturer_payment_method_id", $paymentMethodIds);
        }

        if ($approvalStatuses) {

            $query->whereIn("approval_status", $approvalStatuses);
        }

        if ($paidStatuses) {

            $query->whereIn("paid_status", $paidStatuses);
        }

        if ($dateFrom && $dateTill) {

            $query->where(function ($query) use($dateFrom, $dateTill) {

                $del = "-";
                $dateFromExp = explode($del, $dateFrom);
                array_pop($dateFromExp);
                $dateFromMonth = implode($del, $dateFromExp);

                $dateTillExp = explode($del, $dateTill);
                array_pop($dateTillExp);
                $dateTillMonth = implode($del, $dateTillExp);

                $query->whereDate("payment_date", ">=", $dateFrom);
                $query->whereDate("payment_date", "<=", $dateTill);
                $query->orWhereBetween("payment_month", [$dateFromMonth, $dateTillMonth]);
            });
        }

        return $query->get()->toArray();
    }
}
