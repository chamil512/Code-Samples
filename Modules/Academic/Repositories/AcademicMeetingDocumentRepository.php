<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Admin\Services\Permission;

class AcademicMeetingDocumentRepository extends BaseRepository
{
    public $upload_dir = "public/academic_meeting_documents/";

    public $approvalOptions = [
        ["id" => "0", "name" => "Pending", "label" => "warning"],
        ["id" => "1", "name" => "Approved", "label" => "success"],
        ["id" => "2", "name" => "Rejected", "label" => "danger"]
    ];

    public $submitTypeOptions = [
        ["id" => "1", "name" => "Committee", "label" => "primary"],
        ["id" => "2", "name" => "Faculty", "label" => "info"],
    ];

    public $purposeTypeOptions = [
        ["id" => "1", "name" => "For Approval", "label" => "primary"],
        ["id" => "2", "name" => "For Information", "label" => "info"],
    ];

    function uploadDocument($currFileName="")
    {
        $fileName = uniqid()."_".$_FILES["file_name"]["name"];

        if(Storage::disk('local')->put($this->upload_dir.$fileName, file_get_contents($_FILES["file_name"]["tmp_name"])))
        {
            $this->deleteDocument($currFileName);

            return $fileName;
        }

        return false;
    }

    function downloadDocument($fileName, $fileTitle="")
    {
        if($fileTitle == "")
        {
            $fileTitle = $fileName;
        }

        return Storage::download($this->upload_dir.$fileName, $fileTitle);
    }

    function deleteDocument($fileName)
    {
        if($fileName != "")
        {
            Storage::delete($this->upload_dir.$fileName);
        }
    }
}
