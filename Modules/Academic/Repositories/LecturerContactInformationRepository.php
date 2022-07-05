<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\LecturerContactInformation;

class LecturerContactInformationRepository extends BaseRepository
{
    public function update($lecturer)
    {
        $ids = request()->post("lc_info_id");
        $contactTypes = request()->post("contact_type");
        $contactDetails = request()->post("contact_detail");

        $currentIds = $this->getRecordIds($lecturer);
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
                    $records[] = new LecturerContactInformation($record);
                }
                else
                {
                    $lecturer->contactInfo()->where("lc_info_id", $id)->update($record);

                    $updatingIds[] = $id;
                }
            }

            if(count($records)>0)
            {
                $lecturer->contactInfo()->saveMany($records);
            }
        }

        $notUpdatingIds = array_diff($currentIds, $updatingIds);

        if(count($notUpdatingIds)>0)
        {
            $lecturer->contactInfo()->whereIn("lc_info_id", $notUpdatingIds)->delete();
        }
    }

    public function getRecordIds($lecturer)
    {
        $contactInfo = $lecturer->contactInfo->toArray();

        $ids = [];
        if(is_array($contactInfo) && count($contactInfo)>0)
        {
            foreach ($contactInfo as $record)
            {
                $ids[] = $record["lc_info_id"];
            }
        }

        return $ids;
    }
}
