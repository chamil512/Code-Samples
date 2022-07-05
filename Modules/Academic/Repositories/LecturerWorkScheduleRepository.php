<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Entities\Lecturer;
use Modules\Academic\Entities\LecturerWorkSchedule;

class LecturerWorkScheduleRepository extends BaseRepository
{
    public static function addWorkSchedule($lecturerId, $timeSlot, $timeInfo, $remarks)
    {
        $workCategory = LecturerWorkCategoryRepository::getLectureWorkCategory();

        if ($workCategory) {

            $deliveryMode = $timeSlot->deliveryMode;

            if (isset($timeSlot->deliveryModeSpecial->id)) {
                $deliveryMode = $timeSlot->deliveryModeSpecial;
            }

            //check if this record already exists
            $model = LecturerWorkSchedule::query()
                ->where("lecturer_id", $lecturerId)
                ->where("academic_timetable_information_id", $timeSlot->id)
                ->first();

            if (!$model) {

                $model = new LecturerWorkSchedule();
            }

            $model->lecturer_id = $lecturerId;
            $model->title = $timeSlot->module->name . " - " . $deliveryMode->name . " session";
            $model->work_date = $timeSlot->tt_date;
            $model->lecturer_work_category_id = $workCategory["id"];
            $model->delivery_mode_id = $deliveryMode->id;
            $model->academic_timetable_information_id = $timeSlot->id;
            $model->start_time = $timeInfo["start_time"];
            $model->end_time = $timeInfo["end_time"];
            $model->work_count = 1;
            $model->note = $remarks;

            $model->save();
        }
    }

    /**
     * @param $relations
     * @param bool $onlyIds
     * @return array
     */
    public function getFilteredData($relations, bool $onlyIds = false): array
    {
        $request = request();

        $facultyId = $request->post("faculty_id");
        $deptId = $request->post("dept_id");
        $lecturerIds = $request->post("lecturer_id");
        $lecturerWorkCategoryIds = $request->post("lecturer_work_category_id");
        $lecturerWorkTypeIds = $request->post("lecturer_work_type_id");
        $deliveryModeIds = $request->post("delivery_mode_id");
        $staffType = $request->post("staff_type");
        $dateFrom = $request->post("date_from");
        $dateTill = $request->post("date_till");
        $workDate = $request->post("work_date");

        if (!$dateFrom) {

            $dateFrom = date("Y-m-d", time());
        }

        if (!$dateTill) {

            $dateTill = date("Y-m-d", time());
        }

        $query = LecturerWorkSchedule::query();

        if ($onlyIds) {

            $query->select("lecturer_id")
                ->groupBy("lecturer_id");
        } else {
            if (is_array($relations) && count($relations) > 0) {

                $query->with($relations);
            }
        }

        $query->whereHas("lecturer", function ($query) use ($facultyId, $deptId, $staffType) {

            if ($facultyId) {

                $query->whereIn("faculty_id", $facultyId);
            }

            if ($deptId) {

                $query->whereIn("dept_id", $deptId);
            }

            if ($staffType) {

                $query->where("staff_type", $staffType);
            }
        });

        if ($lecturerIds) {

            $query->whereIn("lecturer_id", $lecturerIds);
        }

        if ($lecturerWorkCategoryIds) {

            $query->whereIn("lecturer_work_category_id", $lecturerWorkCategoryIds);
        }

        if ($lecturerWorkTypeIds) {

            $query->whereIn("lecturer_work_type_id", $lecturerWorkTypeIds);
        }

        if ($deliveryModeIds) {

            $query->whereIn("delivery_mode_id", $deliveryModeIds);
        }

        if ($dateFrom !== null && $dateTill !== null) {

            $query->whereDate("work_date", ">=", $dateFrom);
            $query->whereDate("work_date", "<=", $dateTill);
        } elseif ($workDate !== null) {

            $query->where("work_date", $workDate);
        }

        if ($onlyIds) {

            return array_keys($query->get()->keyBy("lecturer_id")->toArray());
        } else {

            return $query->get()->toArray();
        }
    }

    /**
     * @return array
     */
    public function getFilteredDataSF(): array
    {
        $request = request();

        $facultyId = $request->post("faculty_id");
        $deptId = $request->post("dept_id");
        $lecturerIds = $request->post("lecturer_id");
        $staffType = $request->post("staff_type");

        $query = Lecturer::query()
            ->with(["faculty", "department"])
            ->select("title_id", "id", "name_with_init", "given_name", "surname", "faculty_id", "dept_id", "staff_type")
            ->where("status", 1);

        if ($facultyId) {

            $query->whereIn("faculty_id", $facultyId);
        }

        if ($deptId) {

            $query->whereIn("dept_id", $deptId);
        }

        if ($lecturerIds) {

            $query->whereIn("id", $lecturerIds);
        }

        if ($staffType) {

            $query->where("staff_type", $staffType);
        }

        return $query->get()->toArray();
    }
}
