<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\Storage;
use Modules\Academic\Entities\DepartmentHead;
use Modules\Admin\Entities\Admin;

class DepartmentHeadRepository extends BaseRepository
{
    public string $statusField = "status";

    public array $statuses = [
        ["id" => "1", "name" => "Current HOD", "label" => "success"],
        ["id" => "0", "name" => "Former HOD", "label" => "info"]
    ];

    public array $hodTypes = [
        ["id" => "1", "name" => "HOD", "label" => "info"],
        ["id" => "2", "name" => "Assistant HOD", "label" => "info"],
    ];

    public string $upload_dir = "public/department_head_images/";
    private string $formFileName = "file_name";
    private array $fileTypes = [
        ["type" => "image/jpeg", "ext" => "jpg"],
        ["type" => "image/jpeg", "ext" => "jpg"],
        ["type" => "image/png", "ext" => "png"],
        ["type" => "image/gif", "ext" => "gif"],
    ];

    /**
     * @param string $currFileName
     * @return string
     */
    public function uploadImage(string $currFileName = ""): string
    {
        $fileName = "";
        if (isset($_FILES[$this->formFileName]["name"]) && $this->isValidImage()) {

            $fileName = preg_replace("/[^a-zA-Z0-9.\-_]+/", "", $_FILES[$this->formFileName]["name"]);
            $fileName = md5(microtime() . $fileName) . "-" . $fileName;

            if (Storage::disk('local')->put($this->upload_dir . $fileName, file_get_contents($_FILES[$this->formFileName]["tmp_name"]))) {

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
        if ($fileName != "") {

            Storage::delete($this->upload_dir . $fileName);
        }
    }

    /**
     * @return bool
     */
    private function isValidImage(): bool
    {
        $matchFound = false;
        $thisFileType = strtolower($_FILES[$this->formFileName]["type"]);
        $expDel = "."; //end delimiter

        $fileName = $_FILES[$this->formFileName]["name"];
        $fileName = explode($expDel, $fileName);
        $thisFileExt = strtolower(end($fileName));

        $fileTypes = $this->fileTypes;

        if (isset($fileTypes)) {

            foreach ($fileTypes as $fileType) {

                if ($thisFileType == strtolower($fileType["type"]) && $thisFileExt == strtolower($fileType["ext"])) {
                    $matchFound = true;
                    break;
                }
            }
        }

        return $matchFound;
    }

    function resetOtherCurrent($deptId, $currentId, $hodType)
    {
        $data = [];
        $data["status"] = 0;
        DepartmentHead::query()
            ->where("dept_id", "=", $deptId)
            ->where("hod_type", "=", $hodType)
            ->whereNotIn("id", [$currentId])
            ->update($data);
    }

    /**
     * @param $deptId
     * @return array|false
     */
    public function getDefault($deptId)
    {
        $data = false;
        $model = DepartmentHead::with(["person"])
            ->where("dept_id", "=", $deptId)
            ->where("status", "=", 1)->first();

        if ($model) {
            $data = $model->toArray();
        }

        return $data;
    }

    /**
     * @param $deptId
     * @return array|false
     */
    public static function getHODAdmin($deptId)
    {
        $result = DepartmentHead::query()
            ->with(["person.admin"])
            ->where("dept_id", "=", $deptId)
            ->where("hod_type", "=", 1)
            ->where("status", "=", 1)->first();

        $admin = false;
        if ($result) {

            if (isset($result["person"]["admin"])) {

                $admin = $result["person"]["admin"];
            }
        }

        return $admin;
    }

    /**
     * @param $deptId
     * @return array
     */
    public static function getHODAdmins($deptId): array
    {
        $results = DepartmentHead::query()
            ->with(["person"])
            ->where("dept_id", "=", $deptId)
            ->where("status", "=", 1)->get()->toArray();

        $data = [];
        if ($results) {

            foreach ($results as $result) {

                if (isset($result["person"]["admin_id"])) {

                    $data[] = $result["person"]["admin_id"];
                }
            }
        }

        return $data;
    }
}
