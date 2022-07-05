<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class LecturerRepository extends BaseRepository
{
    public string $statusField= "status";

    public array $statuses = [
        ["id" =>"1", "name" =>"Enabled", "label"=>"success"],
        ["id" =>"0", "name" =>"Disabled", "label"=>"danger"]
    ];

    public array $staffTypes = [
        ["id" =>"1", "name" =>"Internal", "label"=>"info"],
        ["id" =>"2", "name" =>"Visiting", "label"=>"info"]
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
            "route" => "/academic/lecturer/verification",
            "permissionRoutes" => [],
        ],
        /*[
            "step" => "pre_approval_sar",
            "approvedStatus" => 5,
            "declinedStatus" => 6,
            "route" => "/academic/lecturer/pre_approval_sar",
            "permissionRoutes" => [],
        ],*/
        /*[
            "step" => "pre_approval_registrar",
            "approvedStatus" => 7,
            "declinedStatus" => 8,
            "route" => "/academic/lecturer/pre_approval_registrar",
            "permissionRoutes" => [],
        ],
        [
            "step" => "pre_approval_vc",
            "approvedStatus" => 9,
            "declinedStatus" => 10,
            "route" => "/academic/lecturer/pre_approval_vc",
            "permissionRoutes" => [],
        ],*/
        [
            "step" => "approval",
            "approvedStatus" => 1,
            "declinedStatus" => 2,
            "route" => "/academic/lecturer/approval",
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
        switch ($step)
        {
            case "verification" :
                $text = $model->name." verification.";
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
                $text = $model->name." approval.";
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
        $url = URL::to("/academic/lecturer/view/" . $model->id);

        return view("academic::lecturer.approvals." . $step, compact('record', 'url'));
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

    public function displayContactInfoAs()
    {
        return view("academic::lecturer.datatable.contact_info_ui");
    }

    public function getRecordPrepared($record)
    {
        $record = parent::getRecordPrepared($record);

        if (isset($record["staff_type"]) && $record["staff_type"] === 1) {

            $record["enableEdit"] = false;
            $record["enableDelete"] = false;
            $record["enableTrash"] = false;
            $record["enableRestore"] = false;
        }

        return $record;
    }
}
