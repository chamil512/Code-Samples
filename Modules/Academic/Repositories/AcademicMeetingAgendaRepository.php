<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Entities\AcademicMeetingAgendaItem;

class AcademicMeetingAgendaRepository extends BaseRepository
{
    protected function beforeDelete($model, $allowed): bool
    {
        $relations = [
            ["relation" => "academicMeetings", "relationName" => "academic meetings"],
        ];

        $isAllowed = $this->checkRelationsBeforeDelete($model, "academic meeting agenda", $relations);

        if (!$isAllowed) {
            $allowed = false;
        }

        return parent::beforeDelete($model, $allowed);
    }

    public function triggerUpdateAgendaItems($model)
    {
        $currentIds = $this->getAgendaItemIds($model);
        $updatingIds = [];

        $items = request()->post("items");
        $items = @json_decode($items, true);

        if (is_array($items) && count($items) > 0) {
            $updatingIds = $this->updateAgendaItems($model, "", $items, $updatingIds, "");
        }

        $notUpdatingIds = array_diff($currentIds, $updatingIds);

        if (count($notUpdatingIds) > 0) {
            $model->agendaItems()->whereIn("id", $notUpdatingIds)->delete();
        }
    }

    private function updateAgendaItems($model, $parentId, $items, $updatingIds, $prefix)
    {
        if (is_array($items) && count($items) > 0) {
            $itemOrder = 0;
            $itemNo = 0;
            foreach ($items as $item) {

                $itemOrder++;
                $itemNo++;
                if (isset($item["id"]) && $item["id"] != "") {
                    $updatingIds[] = $item["id"];

                    $record = AcademicMeetingAgendaItem::query()->find($item["id"]);

                    if ($parentId !== "") {
                        $record->parent_item_id = $parentId;
                    }
                    $record->item_heading = $item["itemHeading"];
                    $record->item_status = $item["itemStatus"];
                    $record->item_order = $itemOrder;

                    if($item["itemStatus"] == "1") {
                        $itemNo++;

                        $record->item_number = $prefix . $itemNo . ".";
                        $subPrefix = $record->item_number;
                    } else {
                        $subPrefix = "";
                        $record->item_number = "";
                    }

                    if ($record->save()) {
                        if (isset($item["items"]) && count($item["items"]) > 0) {
                            $updatingIds = $this->updateAgendaItems($model, $record->id, $item["items"], $updatingIds, $subPrefix);
                        }
                    }
                } else {
                    $record = new AcademicMeetingAgendaItem();
                    $record->academic_meeting_agenda_id = $model->id;

                    if ($parentId !== "") {
                        $record->parent_item_id = $parentId;
                    }
                    $record->item_heading = $item["itemHeading"];
                    $record->item_status = $item["itemStatus"];
                    $record->item_order = $itemOrder;

                    if($item["itemStatus"] == "1") {
                        $itemNo++;

                        $record->item_number = $prefix . $itemNo . ".";
                        $subPrefix = $record->item_number;
                    } else {
                        $subPrefix = "";
                        $record->item_number = "";
                    }

                    if ($record->save()) {
                        if (isset($item["items"]) && count($item["items"]) > 0) {
                            $updatingIds = $this->updateAgendaItems($model, $record->id, $item["items"], $updatingIds, $subPrefix);
                        }
                    }
                }
            }
        }

        return $updatingIds;
    }

    private function getAgendaItemIds($model)
    {
        $agendaItems = $model->agendaItems->keyBy("id")->toArray();

        return array_keys($agendaItems);
    }

    public function getAgendaItems($model)
    {
        $agendaItems = $model->agendaItems->where("item_status", "1")->toArray();

        $records = [];
        if (is_array($agendaItems) && count($agendaItems) > 0) {
            foreach ($agendaItems as $agendaItem) {

                $record = [];
                $record["id"] = $agendaItem["id"];
                $record["parentId"] = $agendaItem["parent_item_id"];
                $record["itemHeading"] = $agendaItem["item_heading"];
                $record["itemStatus"] = $agendaItem["item_status"];
                $record["itemOrder"] = $agendaItem["item_order"];

                $records[] = $record;
            }
        }

        return $this->getPrepareAgendaItems($records, "");
    }

    private function getPrepareAgendaItems($agendaItems, $parentId)
    {
        $records = [];

        if (is_array($agendaItems) && count($agendaItems) > 0) {
            foreach ($agendaItems as $agendaItem) {
                if ($agendaItem["parentId"] == $parentId) {
                    //get sub items
                    $agendaItem["items"] = $this->getPrepareAgendaItems($agendaItems, $agendaItem["id"]);

                    $records[] = $agendaItem;
                }
            }
        }

        $orderColumn = array_column($records, "itemOrder");
        array_multisort($orderColumn, SORT_ASC, $records);

        return $records;
    }

}
