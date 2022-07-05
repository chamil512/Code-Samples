<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\LecturerPaymentPlan;

class LecturerPaymentPlanRepository extends BaseRepository
{
    public string $statusField = "plan_status";

    public array $statuses = [
        ["id" => "1", "name" => "Active", "label" => "success"],
        ["id" => "0", "name" => "Inactive", "label" => "danger"]
    ];

    public array $paymentTypes = [
        ["id" => "1", "name" => "Hourly Rate", "label" => "info"],
        ["id" => "2", "name" => "Special Rate", "label" => "info"],
        ["id" => "3", "name" => "Monthly Fixed Amount", "label" => "info"],
        ["id" => "4", "name" => "Examination Payment", "label" => "info"],
    ];

    public array $approvalStatuses = [
        ["id" => "", "name" => "Not Sent for Approval", "label" => "info"],
        ["id" => "0", "name" => "Verification Pending", "label" => "warning"],
        //["id" => "3", "name" => "Verified & Pending Pre-approval of Senior Assistant Registrar", "label" => "success"],
        ["id" => "3", "name" => "Verified & Pending Final Approval of Senior Assistant Registrar", "label" => "success"],
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
            "route" => "/academic/lecturer_payment_plan/verification",
            "permissionRoutes" => [],
        ],
        /*[
            "step" => "pre_approval_sar",
            "approvedStatus" => 5,
            "declinedStatus" => 6,
            "route" => "/academic/lecturer_payment_plan/pre_approval_sar",
            "permissionRoutes" => [],
        ],*/
        /*[
            "step" => "pre_approval_registrar",
            "approvedStatus" => 7,
            "declinedStatus" => 8,
            "route" => "/academic/lecturer_payment_plan/pre_approval_registrar",
            "permissionRoutes" => [],
        ],
        [
            "step" => "pre_approval_vc",
            "approvedStatus" => 9,
            "declinedStatus" => 10,
            "route" => "/academic/lecturer_payment_plan/pre_approval_vc",
            "permissionRoutes" => [],
        ],*/
        [
            "step" => "approval",
            "approvedStatus" => 1,
            "declinedStatus" => 2,
            "route" => "/academic/lecturer_payment_plan/approval",
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
        $lecturer = $model->lecturer;
        $paymentType = $this->getPaymentType($model);

        switch ($step) {
            case "verification" :
                $text = $lecturer->name . "'s " . $paymentType . " | verification.";
                break;

            case "pre_approval_sar" :
                $text = $model->name . " pre-approval of senior assistant registrar.";
                break;

            case "pre_approval_registrar" :
                $text = $model->name . " pre-approval of registrar.";
                break;

            case "pre_approval_vc" :
                $text = $model->name . " pre-approval of vice chancellor.";
                break;

            case "approval" :
                $text = $lecturer->name . "'s " . $paymentType . " | approval.";
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
        $url = URL::to("/academic/lecturer_payment_plan/view/" . $model->id);

        return view("academic::lecturer_payment_plan.approvals." . $step, compact('record', 'url'));
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

    public function getPaymentType($model)
    {
        $paymentTypes = $this->paymentTypes;

        $data = "";
        if (is_array($paymentTypes) && count($paymentTypes) > 0) {

            foreach ($paymentTypes as $paymentType) {

                if ($paymentType["id"] == $model->payment_type) {

                    $data = $paymentType["name"];
                }
            }
        }

        return $data;
    }

    /**
     * @param $lecturerId
     * @param $courseId
     * @param $date
     * @return array
     */
    public static function getLecturerHourlyPaymentPlan($lecturerId, $courseId, $date)
    {
        $results =  LecturerPaymentPlan::query()
            ->where("lecturer_id", $lecturerId)
            ->whereIn("payment_type", [1, 2, 3])
            ->where("applicable_from", "<=", $date)
            ->where("applicable_till", ">=", $date)
            ->where("plan_status", 1)
            ->orderBy("id", "DESC")
            ->get()
            ->toArray();

        $data = false;
        if (is_array($results) && count($results) > 0) {

            $fixedPlans = [];
            $hourlyPlans = [];
            $specialRatePlans = [];

            foreach ($results as $result) {

                if ($result["payment_type"] == "1") {

                    $hourlyPlans[] = $result;
                } else if ($result["payment_type"] == "2") {

                    $specialRatePlans[] = $result;
                } else {

                    $fixedPlans[] = $result;
                }
            }

            $restrictedDays = [];
            if (count($fixedPlans) > 0) {

                //get first plan as the active plan
                $fixedPlan = $fixedPlans[0];
                $restrictedDays = $fixedPlan["applicable_days"];
            }

            $weekDay = date("D", strtotime($date));

            if (count($restrictedDays) === 0 || !in_array($weekDay, $restrictedDays)) {

                $hasSpecialRate = false;
                if (count($specialRatePlans) > 0) {

                    foreach ($specialRatePlans as $plan) {

                        if ($plan["course_id"] == $courseId) {
                            //get first special plan as the active plan

                            $hasSpecialRate = true;
                            $data = $plan;
                            break;
                        }
                    }
                }

                if (!$hasSpecialRate && count($hourlyPlans) > 0) {

                    //get first plan as the active plan
                    $data = $hourlyPlans[0];
                }
            }
        }

        return $data;
    }
}
