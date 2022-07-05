<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;

class ExternalIndividualRepository extends BaseRepository
{
    public string $statusField= "status";

    public array $statuses = [
        ["id" =>"1", "name" =>"Enabled", "label"=>"success"],
        ["id" =>"0", "name" =>"Disabled", "label"=>"danger"]
    ];

    public function displayContactInfoAs()
    {
        return view("academic::external_individual.datatable.contact_info_ui");
    }
}
