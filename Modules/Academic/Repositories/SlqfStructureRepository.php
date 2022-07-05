<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\Course;
use Modules\Academic\Entities\SlqfStructure;

class SlqfStructureRepository extends BaseRepository
{
    public string $statusField= "slqf_status";

    public array $statuses = [
        ["id" =>"1", "name" =>"Enabled", "label"=>"success"],
        ["id" =>"0", "name" =>"Disabled", "label"=>"danger"]
    ];

    public static function generateSlqfCode()
    {
        //get max slqf code
        $slqf_code = SlqfStructure::withTrashed()->max("slqf_code");

        if($slqf_code!=null)
        {
            $slqf_code = intval($slqf_code);
            $slqf_code++;

            if($slqf_code<10)
            {
                $slqf_code = "0".$slqf_code;
            }
        }
        else
        {
            $slqf_code = "01";
        }

        return $slqf_code;
    }

    /**
     * Return column UI for the datatable of the model
     * @return Factory|View
     */
    public function display_versions_as()
    {
        $url = URL::to("/academic/slqf_version/");
        return view("academic::slqf_structure.datatable.slqf_versions_ui", compact('url'));
    }

    public function isLinked($slqfId)
    {
        $record = Course::query()->where(["slqf_id" => $slqfId])->first();

        if($record)
        {
            return true;
        }

        return false;
    }

    protected function beforeDelete($model, $allowed): bool
    {
        $relations = [
            ["relation" => "courses", "relationName" => "course"]
        ];

        $isAllowed = $this->checkRelationsBeforeDelete($model, "SLQF structure", $relations);

        if(!$isAllowed)
        {
            $allowed =false;
        }

        return parent::beforeDelete($model, $allowed);
    }
}
