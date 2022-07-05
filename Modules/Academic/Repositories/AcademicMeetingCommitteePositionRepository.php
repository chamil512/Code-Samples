<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class AcademicMeetingCommitteePositionRepository extends BaseRepository
{
    public array $statuses = [
        ["id" => "1", "name" => "Enabled", "label" => "success"],
        ["id" => "0", "name" => "Disabled", "label" => "danger"]
    ];

    protected function beforeDelete($model, $allowed): bool
    {
        $relations = [
            ["relation" => "committeePosition", "relationName" => "committee member positions"],
        ];

        $isAllowed = $this->checkRelationsBeforeDelete($model, "academic meeting committee position", $relations);

        if (!$isAllowed) {
            $allowed = false;
        }

        return parent::beforeDelete($model, $allowed);
    }
}
