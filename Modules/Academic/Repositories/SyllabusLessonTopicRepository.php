<?php

namespace Modules\Academic\Repositories;

use App\Helpers\Helper;
use App\Repositories\BaseRepository;
use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Entities\SyllabusLessonTopic;
use Modules\Academic\Entities\SyllabusModuleDeliveryMode;

class SyllabusLessonTopicRepository extends BaseRepository
{
    /**
     * @param $planId
     * @param $moduleId
     * @return array
     */
    public function update($planId, $moduleId): array
    {
        DB::beginTransaction();

        try {
            $records = request()->post("data");
            $records = json_decode($records, true);

            $currentIds = $this->_getCurrIds($planId, $moduleId);
            $updatingIds = [];

            if (is_array($records) && count($records) > 0) {

                foreach ($records as $record) {

                    $deliveryModeId = $record["id"];
                    $topics = $record["topics"] ?? [];

                    if (is_array($topics) && count($topics) > 0) {

                        $records = [];
                        foreach ($topics as $key => $topic) {

                            $id = $topic["id"];
                            $name = $topic["name"];
                            $hours = $topic["hours"]["id"] ?? 0;
                            $lecturerId = $topic["lecturer"]["id"] ?? null;

                            if ($hours > 0) {

                                if ($name !== "") {

                                    $record = [];
                                    $record["name"] = $name;
                                    $record["lecturer_id"] = $lecturerId;
                                    $record["hours"] = Helper::convertMinutesToHours($hours);
                                    $record["lesson_order"] = $key + 1;

                                    if (!in_array($id, $currentIds)) {

                                        $record["syllabus_lesson_plan_id"] = $planId;
                                        $record["module_id"] = $moduleId;
                                        $record["delivery_mode_id"] = $deliveryModeId;

                                        $records[] = $record;
                                    } else {
                                        $updatingIds[] = $id;

                                        SyllabusLessonTopic::query()->where("id", $id)->update($record);
                                    }
                                }
                            }
                        }

                        if (count($records) > 0) {
                            SyllabusLessonTopic::query()->insert($records);
                        }
                    }
                }
            }

            $notUpdatingIds = array_diff($currentIds, $updatingIds);

            if (count($notUpdatingIds) > 0) {
                SyllabusLessonTopic::query()->whereIn("id", $notUpdatingIds)->delete();
            }

            $updated = true;
            $error = "";
        } catch (Exception $exception) {

            $updated = false;
            $error = $exception->getMessage();
        }

        if ($updated) {
            DB::commit();

            $notify = [];
            $notify["status"] = "success";
            $notify["notify"][] = "Successfully saved the details.";

        } else {

            DB::rollBack();

            $notify = [];
            $notify["status"] = "failed";
            $notify["notify"][] = "Details saving was failed.";
            $notify["error"] = $error;

        }

        $response["notify"] = $notify;

        return $response;
    }

    public function getRecordPrepared($record)
    {
        $record["hours"] = Helper::convertHoursToMinutes($record["hours"]);
        $record["hours"] = Helper::convertMinutesToHumanTime($record["hours"]);

        return parent::getRecordPrepared($record);
    }

    private function _getCurrIds($planId, $moduleId): array
    {
        $results = SyllabusLessonTopic::query()
            ->select(["id"])
            ->where("syllabus_lesson_plan_id", $planId)
            ->where("module_id", $moduleId)
            ->get()
            ->keyBy("id")
            ->toArray();

        return array_keys($results);
    }

    public function getRecords($plan, $moduleId): array
    {
        $modes = SyllabusModuleDeliveryMode::with(["deliveryMode:delivery_mode_id,mode_name"])
            ->select([DB::raw("DISTINCT delivery_mode_id"), "hours"])
            ->where("syllabus_id", $plan->syllabus_id)
            ->where("module_id", $moduleId)
            ->whereHas("deliveryMode", function ($query) {

                $query->where("type", "!=", "exam");
            })
            ->get()
            ->toArray();

        $data = [];
        if (is_array($modes) && count($modes) > 0) {

            $results = SyllabusLessonTopic::with(["lecturer", "deliveryMode"])
                ->select(["id", "name", "hours", "lecturer_id", "delivery_mode_id"])
                ->where("syllabus_lesson_plan_id", $plan->id)
                ->where("module_id", $moduleId)
                ->orderBy("lesson_order")
                ->get()
                ->groupBy("delivery_mode_id")
                ->toArray();

            foreach ($modes as $mode) {

                $record = [];
                $record["id"] = $mode["delivery_mode_id"];
                $record["mode"] = $mode["delivery_mode"];
                $record["hours"] = $mode["hours"];
                $record["topics"] = [];

                if (isset($results[$mode["delivery_mode_id"]])) {

                    $topicRecords = $results[$mode["delivery_mode_id"]];

                    $topics = [];
                    if (count($topicRecords) > 0) {

                        foreach ($topicRecords as $topic) {

                            $topic["hours"] = Helper::convertHoursToMinutes($topic["hours"]);
                            $topics[] = $topic;
                        }
                    }

                    $record["topics"] = $topics;
                }

                $data[] = $record;
            }
        }

        return $data;
    }

    public static function getRecordsByModuleAndMode($planId): array
    {
        $results = SyllabusLessonTopic::query()
            ->select(["id", "module_id", "delivery_mode_id", "lecturer_id", "hours"])
            ->where("syllabus_lesson_plan_id", $planId)
            ->where("hours", ">",  0)
            ->orderBy("lesson_order")
            ->get()
            ->toArray();

        $data = [];
        if (is_array($results) && count($results) > 0) {

            foreach ($results as $result) {

                if (!isset($data[$result["module_id"]])) {

                    $data[$result["module_id"]] = [];
                }

                if (!isset($data[$result["module_id"]][$result["delivery_mode_id"]])) {

                    $data[$result["module_id"]][$result["delivery_mode_id"]] = [];
                }

                $data[$result["module_id"]][$result["delivery_mode_id"]][] = $result;
            }
        }

        return $data;
    }
}
