<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class EmployeeRepository extends BaseRepository
{
    public string $statusField = "status";

    public array $statuses = [
        ["id" => "1", "name" => "Enabled", "label" => "success"],
        ["id" => "0", "name" => "Disabled", "label" => "danger"]
    ];
    public array $staffTypes = [
        ["id" => "1", "name" => "Internal", "label" => "info"],      
    ];
    public function displayContactInfoAs()
    {
        return view("academic::employee.datatable.contact_info_ui");
    }
}
