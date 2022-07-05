<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\SlqfVersion;

class SlqfVersionRepository extends BaseRepository
{
    public string $statusField = "version_status";

    public $defaultStatuses = [
        ["id" => "1", "name" => "Default", "label" => "success"],
        ["id" => "0", "name" => "Non-Default", "label" => "info"]
    ];

    public $upload_dir = "public/slqf_uploads/";

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
            "route" => "/academic/slqf_version/verification",
            "permissionRoutes" => [],
        ],
        [
            "step" => "approval",
            "approvedStatus" => 1,
            "declinedStatus" => 2,
            "route" => "/academic/slqf_version/approval",
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
        $url = URL::to("/academic/slqf_version/view/" . $model->id);

        return view("academic::slqf_version.approvals." . $step, compact('record', 'url'));
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

        /*//check if this SLQF has been linked to any course, if linked then it won't be allowed to disable
        if($this->isLinked($model->id))
        {
            $errors = [];
            $errors[]="SLQF structure disabling was failed. Requested SLQF structure is already linked to some courses.";
            $errors[]="Until they unlinked status of this SLQF structure can not be disabled.";

            $this->setErrors($errors);
            $allowed = false;
        }*/

        return parent::isStatusUpdateAllowed($model, $statusField, $status, $allowed);
    }

    public static function generateVersion()
    {
        //get max course_category code
        $version = SlqfVersion::withTrashed()->max("version");

        if ($version != null) {
            $version = intval($version);
            $version++;

            if ($version < 10) {
                $version = "0" . $version;
            }
        } else {
            $version = "01";
        }

        return $version;
    }

    public function addSlqfVersion($slqfId)
    {
        $model = new SlqfVersion();

        $model = $this->getValidatedData($model, [
            "version_name" => "required",
            "version_date" => "required|date",
            "slqf_file_name" => "mimes:pdf",
        ], [], ["version_name" => "Version Name", "version_date" => "Date of Amendment", "slqf_file_name" => "SLQF Document"]);

        if ($this->isValidData) {
            $fileName = uniqid() . "_" . $_FILES["slqf_file_name"]["name"];

            //set as 1 until approval process implements
            $model->version_status = 1;

            $model->slqf_id = $slqfId;
            $model->version = $this->generateVersion();
            $model->slqf_file_name = $fileName;
            $model->default_status = 1; //set first record as default
            $response = $this->saveModel($model);

            if ($response["notify"]["status"] == "success") {
                $this->uploadSlqfFile($fileName);

                return $model;
            }
        }

        return false;
    }

    function uploadSlqfFile($fileName, $currFileName = "")
    {
        if (Storage::disk('local')->put($this->upload_dir . $fileName, file_get_contents($_FILES["slqf_file_name"]["tmp_name"]))) {
            $this->deleteSlqfFile($currFileName);
        }
    }

    function downloadSlqfFile($fileName, $fileTitle = "")
    {
        if ($fileTitle == "") {
            $fileTitle = $fileName;
        }

        return Storage::download($this->upload_dir . $fileName, $fileTitle);
    }

    function deleteSlqfFile($fileName)
    {
        if ($fileName != "") {
            Storage::delete($this->upload_dir . $fileName);
        }
    }

    function resetOtherVersionDefault($slqfId, $currentId)
    {
        $data = [];
        $data["default_status"] = 0;
        SlqfVersion::query()->where("slqf_id", "=", $slqfId)->whereNotIn("slqf_version_id", [$currentId])->update($data);
    }
}
