<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\Course;

class CourseRepository extends BaseRepository
{
    public string $statusField= "course_status";

    public array $statuses = [
        ["id" =>"1", "name" =>"Enabled", "label"=>"success"],
        ["id" =>"0", "name" =>"Disabled", "label"=>"danger"]
    ];

    public array $approvalStatuses = [
        ["id" => "", "name" =>"Not Sent for Approval", "label" => "info"],
        ["id" =>"0", "name" =>"Verification Pending", "label" => "warning"],
        ["id" =>"3", "name" =>"Verified & Pending Approval", "label" => "success"],
        ["id" =>"4", "name" =>"Verification Declined", "label" => "danger"],
        ["id" =>"1", "name" =>"Approved", "label" => "success"],
        ["id" =>"2", "name" =>"Declined", "label" => "danger"],
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
            "route" => "/academic/course/verification",
            "permissionRoutes" => [],
        ],
        [
            "step" => "approval",
            "approvedStatus" => 1,
            "declinedStatus" => 2,
            "route" => "/academic/course/approval",
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
        $url = URL::to("/academic/course/view/" . $model->id);

        return view("academic::course.approvals." . $step, compact('record', 'url'));
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

    public static function generateCourseCode($deptId)
    {
        //get max course_category code
        $course_code = Course::withTrashed()->where("dept_id", $deptId)->max("course_code");

        if($course_code!=null)
        {
            $course_code = intval($course_code);
            $course_code++;

            if($course_code<10)
            {
                $course_code = "0".$course_code;
            }
        }
        else
        {
            $course_code = "01";
        }

        return $course_code;
    }

    protected function beforeDelete($model, $allowed): bool
    {
        $relations = [
            ["relation" => "studentRegCourses", "relationName" => "registered student"]
        ];

        $isAllowed = $this->checkRelationsBeforeDelete($model, "course", $relations);

        if(!$isAllowed)
        {
            $allowed =false;
        }

        return parent::beforeDelete($model, $allowed);
    }
}
