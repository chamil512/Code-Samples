<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Admin\Entities\Admin;

class SyllabusLessonPlanRepository extends BaseRepository
{
    public string $statusField= "plan_status";

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
            "route" => "/academic/syllabus_lesson_plan/verification",
            "permissionRoutes" => [],
        ],
        [
            "step" => "approval",
            "approvedStatus" => 1,
            "declinedStatus" => 2,
            "route" => "/academic/syllabus_lesson_plan/approval",
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
        $url = URL::to("/academic/syllabus_lesson_plan/view/" . $model->id);

        return view("academic::syllabus_lesson_plan.approvals." . $step, compact('record', 'url'));
    }

    /**
     * @param $model
     * @param $step
     * @return array
     */
    protected function getApprovalStepUsers($model, $step): array
    {
        $model->load(["syllabus", "syllabus.course"]);
        $deptId = $model->syllabus->course->dept_id;

        $data = [];
        if ($step === "verification") {

            $data = BatchCoordinatorRepository::getBatchCoordinatorIds($model->batch_id);

        } elseif ($step === "approval") {

            //get head of the department's id
            $data = DepartmentHeadRepository::getHODAdmins($deptId);
        }

        return $data;
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

    public function replicate($oldModel, $model)
    {
        $topics = $oldModel->topics()->get();

        if (count($topics) > 0) {

            foreach ($topics as $topic) {

                $topicModel = $topic->replicate();
                $topicModel->syllabus_lesson_plan_id = $model->id;
                $topicModel->lecturer_id = null;

                $topicModel->push();
            }
        }
    }
}
