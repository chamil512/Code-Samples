<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class AcademicMeetingAgendaItemRepository extends BaseRepository
{
    protected function beforeDelete($model, $allowed): bool
    {
        $relations = [
            ["relation" => "documentSubmissionHeadings", "relationName" => "document submission headings"],
            ["relation" => "documentSubmissionSubHeadings", "relationName" => "document submission sub headings"],
        ];

        $isAllowed = $this->checkRelationsBeforeDelete($model, "agenda item", $relations);

        if(!$isAllowed)
        {
            $allowed =false;
        }

        return parent::beforeDelete($model, $allowed);
    }
}
