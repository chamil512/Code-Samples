<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class SyllabusEntryCriteriaRepository extends BaseRepository
{
    public array $statuses = [
        ["id" => "1", "name" => "Enabled", "label" => "success"],
        ["id" => "0", "name" => "Disabled", "label" => "danger"]
    ];
}
