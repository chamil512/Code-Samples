<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\ExternalIndividualContactInformation;

class ExternalIndividualContactInformationRepository extends BaseRepository
{
    public function update($externalIndividual)
    {
        $ids = request()->post("lc_info_id");
        $contactTypes = request()->post("contact_type");
        $contactDetails = request()->post("contact_detail");

        $currentIds = $this->getRecordIds($externalIndividual);
        $updatingIds = [];

        if(is_array($ids) && count($ids)>0)
        {
            $records = [];
            foreach ($ids as $key => $id)
            {
                $record = [];
                $record["contact_type"]=$contactTypes[$key];
                $record["contact_detail"]=$contactDetails[$key];

                if($id == "" || !in_array($id, $currentIds))
                {
                    $records[] = new ExternalIndividualContactInformation($record);
                }
                else
                {
                    $externalIndividual->contactInfo()->where("id", $id)->update($record);

                    $updatingIds[] = $id;
                }
            }

            if(count($records)>0)
            {
                $externalIndividual->contactInfo()->saveMany($records);
            }
        }

        $notUpdatingIds = array_diff($currentIds, $updatingIds);

        if(count($notUpdatingIds)>0)
        {
            $externalIndividual->contactInfo()->whereIn("id", $notUpdatingIds)->delete();
        }
    }

    public function getRecordIds($externalIndividual)
    {
        $contactInfo = $externalIndividual->contactInfo->toArray();

        $ids = [];
        if(is_array($contactInfo) && count($contactInfo)>0)
        {
            foreach ($contactInfo as $record)
            {
                $ids[] = $record["id"];
            }
        }

        return $ids;
    }
}
