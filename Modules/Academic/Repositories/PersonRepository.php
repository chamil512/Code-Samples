<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\Lecturer;
use Modules\Academic\Entities\Person;

class PersonRepository extends BaseRepository
{
    public string $statusField= "status";

    public array $statuses = [
        ["id" =>"1", "name" =>"Enabled", "label"=>"success"],
        ["id" =>"0", "name" =>"Disabled", "label"=>"danger"]
    ];

    public $staffTypes = [
        ["id" =>"1", "name" =>"Internal", "label"=>"info"],
        ["id" =>"2", "name" =>"Visiting", "label"=>"info"]
    ];

    public function displayContactInfoAs()
    {
        return view("academic::lecturer.datatable.contact_info_ui");
    }

    public function importData()
    {
        $results = Lecturer::withTrashed()->get();

        foreach ($results as $lecturer) {

            $person = new Person();

            $attributes = $lecturer->getAttributes();
            $attributes = array_keys($attributes);

            $person->setAttribute("id", $lecturer->id);

            foreach ($attributes as $attribute) {
                if ($attribute !== "employee_id" && $attribute !== "lecturer_id") {

                    if ($lecturer->$attribute != "") {

                        $person->setAttribute($attribute, $lecturer->$attribute);
                    }
                }
            }

            $person->setAttribute("person_type", "employee");
            if ($lecturer->staff_type === 2) {
                $person->setAttribute("person_type", "lecturer");
            }

            $person->save();
        }
    }
}
