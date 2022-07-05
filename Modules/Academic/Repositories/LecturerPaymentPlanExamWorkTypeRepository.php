<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\LecturerPaymentPlanExamWorkType;

class LecturerPaymentPlanExamWorkTypeRepository extends BaseRepository
{
    public function update($model)
    {
        $workTypeIds = request()->post("exam_work_types");

        $currentIds = $this->getCurrIds($model);
        $updatingIds = [];

        if(is_array($workTypeIds) && count($workTypeIds)>0)
        {
            $records = [];
            foreach ($workTypeIds as $workTypeId)
            {
                if(!in_array($workTypeId, $currentIds))
                {
                    $record = [];
                    $record["lecturer_payment_plan_id"]=$model->id;
                    $record["exam_work_type_id"]=$workTypeId;

                    $records[] = new LecturerPaymentPlanExamWorkType($record);
                }
                else
                {
                    $updatingIds[] = $workTypeId;
                }
            }

            if(count($records)>0)
            {
                $model->examWorkTypes()->saveMany($records);
            }
        }

        $notUpdatingIds = array_diff($currentIds, $updatingIds);

        if(count($notUpdatingIds)>0)
        {
            $model->examWorkTypes()->whereIn("exam_work_type_id", $notUpdatingIds)->delete();
        }
    }

    public function getCurrIds($model)
    {
        $examWorkTypes = $model->examWorkTypes->toArray();

        $ids = [];
        if(is_array($examWorkTypes) && count($examWorkTypes)>0)
        {
            foreach ($examWorkTypes as $workType)
            {
                $ids[] = $workType["exam_work_type_id"];
            }
        }

        return $ids;
    }
}
