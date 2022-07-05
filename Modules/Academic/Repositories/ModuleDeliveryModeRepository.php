<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class ModuleDeliveryModeRepository extends BaseRepository
{
    public array $statuses = [
        ["id" =>"1", "name" =>"Enabled", "label"=>"success"],
        ["id" =>"0", "name" =>"Disabled", "label"=>"danger"]
    ];

    public $types = [
        ["id" =>"exam", "name" =>"Exam", "label"=>"info"],
        ["id" =>"other", "name" =>"Other", "label"=>"info"]
    ];

    protected function beforeDelete($model, $allowed): bool
    {
        $relations = [
            ["relation" => "courseModuleDeliveryModes", "relationName" => "course module delivery mode"]
        ];

        $isAllowed = $this->checkRelationsBeforeDelete($model, "delivery mode", $relations);

        if(!$isAllowed)
        {
            $allowed =false;
        }

        return parent::beforeDelete($model, $allowed);
    }
}
