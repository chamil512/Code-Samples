<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\SyllabusModule;
use Modules\Academic\Entities\SyllabusModuleDeliveryMode;

class SyllabusModuleRepository extends BaseRepository
{
    public $mandatoryStatuses = [
        ["id" => "1", "name" => "Compulsory", "label" => "primary"],
        ["id" => "2", "name" => "Optional", "label" => "primary"],
        ["id" => "3", "name" => "Elective", "label" => "primary"],
        ["id" => "4", "name" => "Exempted", "label" => "primary"],
    ];

    public $exemptedStatuses = [
        ["id" => "0", "name" => "Default", "label" => "primary"],
        ["id" => "1", "name" => "Exempted", "label" => "primary"],
    ];

    public function updateMDModes($model)
    {
        $modeIds = request()->post("delivery_mode_id");
        $types = request()->post("type");
        $hours = request()->post("hours");
        $credits = request()->post("credits");
        $smdm_ids = request()->post("smdm_id");

        $currentIds = $this->getCurrDMIds($model);
        $updatingIds = [];

        $totalHours = 0;
        $totalCredits = 0;
        if (is_array($modeIds) && count($modeIds) > 0) {

            $records = [];
            foreach ($modeIds as $key => $modeId) {
                $smdm_id = $smdm_ids[$key];

                $record = [];
                $record["hours"] = $hours[$key];
                $record["credits"] = $credits[$key];
                $record["syllabus_id"] = $model->syllabus_id;
                $record["module_id"] = $model->module_id;

                if ($types[$key] !== "exam") {

                    $totalHours += $record["hours"];
                    $totalCredits += $record["credits"];
                }

                if (!in_array($modeId, $currentIds)) {
                    $record["delivery_mode_id"] = $modeId;

                    $records[] = new SyllabusModuleDeliveryMode($record);
                } else {
                    if ($smdm_id != "0") {
                        //updating this record as like this bcz it's not triggering updating event which accesses from observer
                        //when it's updating using ORM
                        $smdm = SyllabusModuleDeliveryMode::query()->where(["smdm_id" => $smdm_id])->first();

                        $smdm->update($record);
                    } else {
                        $model->deliveryModes()->where("delivery_mode_id", $modeId)->update($record);
                    }

                    $updatingIds[] = $modeId;
                }
            }

            if (count($records) > 0) {
                $model->deliveryModes()->saveMany($records);
            }
        }

        //update model data
        $model->total_hours = $totalHours;
        $model->total_credits = $totalCredits;
        $model->save();

        $notUpdatingIds = array_diff($currentIds, $updatingIds);

        if (count($notUpdatingIds) > 0) {
            $model->deliveryModes()->whereIn("delivery_mode_id", $notUpdatingIds)->delete();
        }
    }

    public function getCurrDMIds($model)
    {
        $deliveryModes = $model->deliveryModes->toArray();

        $ids = [];
        if (is_array($deliveryModes) && count($deliveryModes) > 0) {
            foreach ($deliveryModes as $deliveryMode) {
                $ids[] = $deliveryMode["delivery_mode_id"];
            }
        }

        return $ids;
    }

    public function updateAllSyllabusModules()
    {
        $models = SyllabusModule::withTrashed()->get();

        foreach ($models as $model) {

            $this->updateMDModesManual($model);
        }
    }

    public function updateMDModesManual($model)
    {
        $totalHours = 0;
        $totalCredits = 0;

        $records = $model->deliveryModes->toArray();
        if (is_array($records) && count($records) > 0) {
            foreach ($records as $record) {

                if ($record["delivery_mode"]["type"] !== "exam") {

                    $totalHours += $record["hours"];
                    $totalCredits += $record["credits"];
                }
            }
        }

        //update model data
        $model->total_hours = $totalHours;
        $model->total_credits = $totalCredits;
        $model->save();
    }
}
