<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\Storage;
use Modules\Academic\Entities\FacultyDean;
use Modules\Admin\Entities\Admin;

class FacultyDeanRepository extends BaseRepository
{
    public string $statusField= "status";

    public array $statuses = [
        ["id" =>"1", "name" =>"Current Dean", "label"=>"success"],
        ["id" =>"0", "name" =>"Former Dean", "label"=>"info"]
    ];

    public $deanTypes = [
        ["id" =>"1", "name" =>"Lecturer", "label"=>"info"],
        ["id" =>"2", "name" =>"Employee", "label"=>"info"],
        ["id" =>"3", "name" =>"External Individual", "label"=>"info"],
    ];

    public $upload_dir = "public/faculty_dean_images/";
    private $formFileName = "file_name";
    private $fileTypes=[
        ["type" => "image/jpeg", "ext" => "jpg"],
        ["type" => "image/jpeg", "ext" => "jpg"],
        ["type" => "image/png", "ext" => "png"],
        ["type" => "image/gif", "ext" => "gif"],
    ];

    /**
     * @param string $currFileName
     */
    public function uploadImage($currFileName="")
    {
        $fileName = "";
        if (isset($_FILES[$this->formFileName]["name"]) && $this->isValidImage()) {

            $fileName = preg_replace("/[^a-zA-Z0-9.\-_]+/", "", $_FILES[$this->formFileName]["name"]);
            $fileName = md5(microtime() . $fileName) . "-" . $fileName;

            if(Storage::disk('local')->put($this->upload_dir . $fileName, file_get_contents($_FILES[$this->formFileName]["tmp_name"]))) {

                $this->deleteImage($currFileName);
            }
        }

        return $fileName;
    }

    /**
     * @param $fileName
     */
    private function deleteImage($fileName)
    {
        if($fileName != "") {

            Storage::delete($this->upload_dir . $fileName);
        }
    }

    /**
     * @return bool
     */
    private function isValidImage()
    {
        $matchFound=false;
        $thisFileType=strtolower($_FILES[$this->formFileName]["type"]);
        $expDel = "."; //end delimiter

        $fileName = $_FILES[$this->formFileName]["name"];
        $fileName = explode($expDel, $fileName);
        $thisFileExt = strtolower(end($fileName));

        $fileTypes = $this->fileTypes;

        if(isset($fileTypes)) {

            foreach($fileTypes as $fileType)  {

                if($thisFileType == strtolower($fileType["type"]) && $thisFileExt == strtolower($fileType["ext"])) {
                    $matchFound=true;
                    break;
                }
            }
        }

        return $matchFound;
    }

    public function resetOtherCurrent($facultyId, $currentId)
    {
        $data = [];
        $data["status"]=0;
        FacultyDean::query()->where("faculty_id",  "=", $facultyId)->whereNotIn("id", [$currentId])->update($data);
    }

    /**
     * @param $facultyId
     * @return array|false
     */
    public function getDefault($facultyId)
    {
        $data = false;
        $model = FacultyDean::with(["lecturer", "employee", "externalIndividual"])
            ->where("faculty_id",  "=", $facultyId)
            ->where("status",  "=", 1)->first();

        if ($model) {
            $data = $model->toArray();
        }

        return $data;
    }

    /**
     * @param $facultyId
     * @return array|false
     */
    public static function getDeanAdmin($facultyId)
    {
        $dean = FacultyDean::query()
            ->where("faculty_id",  "=", $facultyId)
            ->where("status",  "=", 1)->first();

        $admin = false;
        if ($dean) {

            $dean = $dean->toArray();

            if ($dean["dean_type"] === 1) {

                $admin = Admin::query()->where("lecturer_id", $dean["lecturer_id"])->first()->toArray();
            } else if ($dean["dean_type"] === 2) {

                $admin = Admin::query()->where("employee_id", $dean["employee_id"])->first()->toArray();
            } else if ($dean["dean_type"] === 3) {

                $admin = Admin::query()->where("external_individual_id", $dean["external_individual_id"])->first()->toArray();
            }
        }

        return $admin;
    }
}
