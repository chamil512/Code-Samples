<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\LecturerBankingInformation;

class LecturerBankingInformationRepository extends BaseRepository
{
    public function update($lecturer)
    {
        $ids = request()->post("lb_info_id");
        $types = request()->post("type");
        $taxIdNos = request()->post("tax_id_no");
        $bankNames = request()->post("bank_name");
        $bankBranches = request()->post("bank_branch");
        $bankBranchCodes = request()->post("bank_branch_code");
        $bankAccNames = request()->post("bank_acc_name");
        $bankAccNos = request()->post("bank_acc_number");
        $emgContactNames = request()->post("emg_contact_name");
        $emgContactNos = request()->post("emg_contact_no");
        $emgPostalAddresses = request()->post("emg_postal_address");

        $currentIds = $this->getRecordIds($lecturer);
        $updatingIds = [];

        if(is_array($ids) && count($ids)>0)
        {
            $records = [];
            $emgKey = -1;
            foreach ($ids as $key => $id)
            {
                $record = [];
                $record["type"]=$types[$key];

                $record["tax_id_no"]=$taxIdNos[$key];
                $record["bank_name"]=$bankNames[$key];
                $record["bank_branch"]=$bankBranches[$key];
                $record["bank_branch_code"]=$bankBranchCodes[$key];
                $record["bank_acc_name"]=$bankAccNames[$key];
                $record["bank_acc_number"]=$bankAccNos[$key];

                if($record["type"] == "2")
                {
                    $emgKey++;

                    $record["emg_contact_name"]=$emgContactNames[$emgKey];
                    $record["emg_contact_no"]=$emgContactNos[$emgKey];
                    $record["emg_postal_address"]=$emgPostalAddresses[$emgKey];
                }

                if($id == "" || !in_array($id, $currentIds))
                {
                    $records[] = new LecturerBankingInformation($record);
                }
                else
                {
                    $lecturer->bankingInfo()->where("lb_info_id", $id)->update($record);

                    $updatingIds[] = $id;
                }
            }

            if(count($records)>0)
            {
                $lecturer->bankingInfo()->saveMany($records);
            }
        }

        $notUpdatingIds = array_diff($currentIds, $updatingIds);

        if(count($notUpdatingIds)>0)
        {
            $lecturer->bankingInfo()->whereIn("lb_info_id", $notUpdatingIds)->delete();
        }
    }

    public function getRecordIds($lecturer)
    {
        $bankingInfo = $lecturer->bankingInfo->toArray();

        $ids = [];
        if(is_array($bankingInfo) && count($bankingInfo)>0)
        {
            foreach ($bankingInfo as $record)
            {
                $ids[] = $record["lb_info_id"];
            }
        }

        return $ids;
    }
}
