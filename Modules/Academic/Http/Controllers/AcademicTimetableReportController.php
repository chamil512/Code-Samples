<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Academic\Entities\AcademicTimetableInformationSubgroup;
use Modules\Academic\Exports\ConductedLectureExport;
use Modules\Academic\Exports\CourseLectureScheduleExport;
use Modules\Academic\Exports\ExcelExport;
use Modules\Academic\Repositories\AcademicTimetableReportRepository;
use Modules\Academic\Repositories\AcademicTimetableRepository;
use Modules\Academic\Repositories\AcademicTimetableSubgroupRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AcademicTimetableReportController extends Controller
{
    private $repository;

    public function __construct()
    {
        $this->repository = new AcademicTimetableReportRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function dashboard()
    {
        return view('academic::academic_timetable_report.index');
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function lectureSchedule()
    {
        $pageTitle = "Lecture Schedule";
        $this->repository->setPageTitle($pageTitle);

        $this->repository->reportType = "lecture_schedule";

        $this->repository->initDatatable(new AcademicTimetableInformationSubgroup());

        $this->repository->setColumns("id", "course", "batch", "module", "subgroup", "delivery_mode",
            "delivery_mode_special", "tt_type", "tt_date", "start_time", "end_time", "actual_start_time",
            "actual_end_time", "lecturer_attendance", "student_attendance", "spaces", "lecturers")

            ->setColumnLabel("tt_date", "Date")
            ->setColumnLabel("tt_type", "Timetable Type")
            ->setColumnLabel("delivery_mode_special", "Special Delivery Mode")

            ->setColumnDBField("course", "course_id")
            ->setColumnFKeyField("course", "course_id")
            ->setColumnRelation("course", "course", "course_name")

            ->setColumnDBField("batch", "batch_id")
            ->setColumnFKeyField("batch", "batch_id")
            ->setColumnRelation("batch", "batch", "batch_name")

            ->setColumnDBField("module", "sg_module_id")
            ->setColumnFKeyField("module", "module_id")
            ->setColumnRelation("module", "sgModule", "module_name")

            ->setColumnDBField("subgroup", "subgroup_id")
            ->setColumnFKeyField("subgroup", "id")
            ->setColumnRelation("subgroup", "subgroup", "sg_name")

            ->setColumnDBField("delivery_mode", "delivery_mode_id")
            ->setColumnFKeyField("delivery_mode", "delivery_mode_id")
            ->setColumnRelation("delivery_mode", "deliveryMode", "mode_name")

            ->setColumnDBField("delivery_mode_special", "delivery_mode_id_special")
            ->setColumnFKeyField("delivery_mode_special", "delivery_mode_id")
            ->setColumnRelation("delivery_mode_special", "deliveryModeSpecial", "mode_name")

            ->setColumnDBField("spaces", "academic_timetable_information_id")
            ->setColumnFKeyField("spaces", "academic_timetable_information_id")
            ->setColumnRelation("spaces", "ttInfoSpaces", "space_id")
            ->setColumnCoRelation("spaces", "space", "sg_name", "space_id")

            ->setColumnDBField("lecturers", "academic_timetable_information_id")
            ->setColumnFKeyField("lecturers", "academic_timetable_information_id")
            ->setColumnRelation("lecturers", "ttInfoLecturers", "lecturer_id")
            ->setColumnCoRelation("lecturers", "lecturer", "name_with_init", "lecturer_id")

            ->setColumnDisplay("course", array($this->repository, 'displayRelationAs'), ["course", "id", "name"])
            ->setColumnDisplay("batch", array($this->repository, 'displayRelationAs'), ["batch", "id", "name"])
            ->setColumnDisplay("module", array($this->repository, 'displayRelationAs'), ["module", "id", "name"])
            ->setColumnDisplay("subgroup", array($this->repository, 'displayRelationAs'), ["subgroup", "id", "name"])
            ->setColumnDisplay("delivery_mode", array($this->repository, 'displayRelationAs'), ["delivery_mode", "delivery_mode_id", "name"])
            ->setColumnDisplay("delivery_mode_special", array($this->repository, 'displayRelationAs'), ["delivery_mode_special", "delivery_mode_id", "name"])
            ->setColumnDisplay("spaces", array($this->repository, 'displayRelationManyAs'), ["spaces", "space", "id", "name"])
            ->setColumnDisplay("lecturers", array($this->repository, 'displayRelationManyAs'), ["lecturers", "lecturer", "lecturer_id", "name"])
            ->setColumnDisplay("actual_start_time", array($this->repository, 'displayRelationAs'), ["attendance", "id", "start_time"])
            ->setColumnDisplay("actual_end_time", array($this->repository, 'displayRelationAs'), ["attendance", "id", "end_time"])
            ->setColumnDisplay("lecturer_attendance", array($this->repository, 'displayRelationAs'), ["attendance", "id", "lecturer_attendance"])
            ->setColumnDisplay("student_attendance", array($this->repository, 'displayRelationAs'), ["attendance", "id", "student_attendance"])
            ->setColumnDisplay("tt_type", array($this->repository, 'displayStatusAs'), [$this->repository->timetableTypes])

            ->setColumnDBField("actual_start_time", "academic_timetable_information_id")
            ->setColumnDBField("actual_end_time", "academic_timetable_information_id")
            ->setColumnDBField("lecturer_attendance", "academic_timetable_information_id")
            ->setColumnDBField("student_attendance", "academic_timetable_information_id")

            ->setColumnFilterMethod("tt_date", "date_between")

            ->setColumnFilterMethod("course", "select", [
                "options" => URL::to("/academic/course/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "department",
                        "param" => "dept_id",
                    ]
                ],
            ])

            ->setColumnFilterMethod("subgroup", "select", [
                "options" => URL::to("/academic/subgroup/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "group",
                        "param" => "group_id",
                    ]
                ],
            ])

            ->setColumnFilterMethod("batch", "select", [
                "options" => URL::to("/academic/batch/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ]
                ],
            ])

            ->setColumnFilterMethod("module", "select", [
                "options" => URL::to("/academic/course_module/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ]
                ],
            ])

            ->setColumnFilterMethod("delivery_mode", "select", URL::to("/academic/module_delivery_mode/search_data"))
            ->setColumnFilterMethod("delivery_mode_special", "select", URL::to("/academic/module_delivery_mode/search_data"))
            ->setColumnFilterMethod("spaces", "select", URL::to("/academic/academic_space/search_data"))
            ->setColumnFilterMethod("lecturers", "select", URL::to("/academic/lecturer/search_data"))
            ->setColumnFilterMethod("tt_type", "select", $this->repository->timetableTypes);

        $this->repository->setCustomFilters("faculty", "department", "timetable_type")
            ->setColumnDBField("faculty", "faculty_id", true)
            ->setColumnFKeyField("faculty", "faculty_id", true)
            ->setColumnRelation("faculty", "faculty", "faculty_name", true)
            ->setColumnFilterMethod("faculty", "select", URL::to("/academic/faculty/search_data"), true)

            ->setColumnDBField("department", "dept_id", true)
            ->setColumnFKeyField("department", "dept_id", true)
            ->setColumnRelation("department", "department", "dept_name", true)
            ->setColumnFilterMethod("department", "select", [
                "options" => URL::to("/academic/department/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "faculty",
                        "param" => "faculty_id",
                    ]
                ],
            ], true);

        $this->repository->setTableTitle($pageTitle)
            ->disableViewData("add", "edit", "view", "trash", "delete")
            ->enableViewData("export");

        $query = $this->repository->model::query();

        $query->where("slot_status", 1);

        $query->with(["course", "batch", "lecturers", "spaces", "subgroup", "sgModule",
            "deliveryMode", "deliveryModeSpecial", "ttInfoSpaces", "ttInfoLecturers", "attendance"]);

        return $this->repository->render("academic::layouts.timetable_report")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @return Application|Factory|View|BinaryFileResponse
     */
    public function lectureScheduleGrid($type = "view")
    {
        $data = [];
        $data["type"] = $type;
        $relations = ["timetable", "module", "deliveryMode", "deliveryModeSpecial", "examType",
            "examCategory", "lecturers", "subgroups", "spaces", "attendance", "subgroup", "department", "course", "batch"];

        if ($type === "view") {

            $urls = [];
            $urls["exportUrl"] = URL::to("/academic/academic_timetable_report/lecture_schedule_grid/export");

            $this->repository->setPageUrls($urls);

            $formSubmitUrl = "/" . request()->path() . "/fetch";

            $pageTitle = "All Lecture Schedules Grid Report";
            $this->repository->setPageTitle($pageTitle);

            return view('academic::academic_timetable_report.lecture_schedule_grid.view', compact('formSubmitUrl'));
        }
        else if ($type === "fetch") {
            $aTSRepo = new AcademicTimetableSubgroupRepository();
            $response = $aTSRepo->getFilteredData($relations);

            if ($response["notify"]["status"] === "success") {

                $data = array_merge($data, $response["data"]);
            }

            $export = new CourseLectureScheduleExport();
            $export->prepareData($data);

            $data = $export->getPreparedData();
            return view('academic::academic_timetable_report.lecture_schedule_grid.export', compact('data'));
        } else {
            $aTSRepo = new AcademicTimetableSubgroupRepository();
            $response = $aTSRepo->getFilteredData($relations);

            if ($response["notify"]["status"] === "success") {

                $data = $response["data"];
            }

            $export = new CourseLectureScheduleExport();
            $export->prepareData($data);

            $export->view = "academic::academic_timetable_report.lecture_schedule_grid.export";

            return Excel::download($export, "All Lecture Schedules Grid Report.xlsx");
        }
    }

    /**
     * @param string $type
     * @return Application|Factory|View|BinaryFileResponse
     */
    public function departmentBatchLectureSchedule($type = "view")
    {
        $data = [];
        $data["type"] = $type;
        $data["records"] = [];

        if ($type === "view") {

            $urls = [];
            $urls["exportUrl"] = URL::to("/academic/academic_timetable_report/department_batch_lecture_schedule/export");

            $this->repository->setPageUrls($urls);

            $formSubmitUrl = "/" . request()->path() . "/fetch";

            $pageTitle = "Department & Batch wise Lecture Schedules Report";
            $this->repository->setPageTitle($pageTitle);

            return view('academic::academic_timetable_report.lecture_schedule_grid.view', compact('formSubmitUrl'));
        }
        else if ($type === "fetch") {
            $data["records"] = $this->repository->getDepBatchScheduleData();

            return view('academic::academic_timetable_report.department_batch_lecture_schedule.export', compact('data'));
        } else {
            $data["records"] = $this->repository->getDepBatchScheduleData();

            $export = new ExcelExport();
            $export->data = $data;
            $export->view = "academic::academic_timetable_report.department_batch_lecture_schedule.export";

            return Excel::download($export, "Department-Batch Lecture Schedules Report.xlsx");
        }
    }

    /**
     * Display a listing of the resource.
     * @return Application|Factory|View|BinaryFileResponse
     */
    public function conductedLectures($type = "view")
    {
        $data = [];
        $data["type"] = $type;

        $relations = [
            "timetableCourseBatch",
            "module",
            "lecturers",
            "lecturers.faculty",
            "lecturers.department",
            "attendanceLecturers",
            "lecturerPayments"
        ];
        $options = ["withCancelled" => "yes", "directOnly" => "yes"];

        $pageTitle = "Conducted Lectures Report";
        $this->repository->setPageTitle($pageTitle);

        if ($type === "view") {

            $urls = [];
            $urls["exportUrl"] = URL::to("/academic/academic_timetable_report/conducted_lecture/export");

            $this->repository->setPageUrls($urls);

            $formSubmitUrl = "/" . request()->path() . "/fetch";

            $isConductedReport = "Y";
            return view('academic::academic_timetable_report.lecture_schedule_grid.view', compact('formSubmitUrl', 'isConductedReport'));
        }
        else if ($type === "fetch") {

            $aTRepo = new AcademicTimetableRepository();
            $data["records"] = $aTRepo->getFilteredData($relations, $options);

            $export = new ConductedLectureExport();
            $export->prepareData($data);

            $data = $export->getPreparedData();

            return view('academic::academic_timetable_report.conducted_lecture.export', compact('data'));
        } else {

            $aTRepo = new AcademicTimetableRepository();
            $data["records"] = $aTRepo->getFilteredData($relations, $options);

            $export = new ConductedLectureExport();
            $export->prepareData($data);

            $export->view = "academic::academic_timetable_report.conducted_lecture.export";

            return Excel::download($export, "Conducted Lectures Report.xlsx");
        }
    }
}
