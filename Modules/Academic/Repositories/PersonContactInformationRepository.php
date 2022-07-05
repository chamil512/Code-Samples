<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\PersonContactInformation;

class PersonContactInformationRepository extends BaseRepository
{
    public function update($person)
    {
        $ids = request()->post("lc_info_id");
        $contactTypes = request()->post("contact_type");
        $contactDetails = request()->post("contact_detail");

        $currentIds = $this->getRecordIds($person);
        $updatingIds = [];

        if (is_array($ids) && count($ids) > 0) {
            $records = [];
            foreach ($ids as $key => $id) {
                $record = [];
                $record["contact_type"] = $contactTypes[$key];
                $record["contact_detail"] = $contactDetails[$key];

                if ($id == "" || !in_array($id, $currentIds)) {
                    $records[] = new PersonContactInformation($record);
                } else {
                    $person->contactInfo()->where("id", $id)->update($record);

                    $updatingIds[] = $id;
                }
            }

            if (count($records) > 0) {
                $person->contactInfo()->saveMany($records);
            }
        }

        $notUpdatingIds = array_diff($currentIds, $updatingIds);

        if (count($notUpdatingIds) > 0) {
            $person->contactInfo()->whereIn("id", $notUpdatingIds)->delete();
        }
    }

    public function getRecordIds($person): array
    {
        $contactInfo = $person->contactInfo->toArray();

        $ids = [];
        if (is_array($contactInfo) && count($contactInfo) > 0) {
            foreach ($contactInfo as $record) {
                $ids[] = $record["id"];
            }
        }

        return $ids;
    }
}
