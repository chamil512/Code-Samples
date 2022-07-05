<?php

namespace Modules\Academic\Http\Controllers;

use App\Helpers\Helper;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Academic\Entities\AcademicCalendar;
use Modules\Academic\Entities\AcademicTimetable;
use Modules\Academic\Entities\AcademicTimetableCriteria;
use Modules\Academic\Entities\AcademicTimetableInformation;
use Modules\Academic\Entities\Lecturer;
use Modules\Academic\Entities\ModuleDeliveryMode;
use Modules\Academic\Entities\Subgroup;
use Modules\Academic\Entities\SyllabusModule;
use Modules\Academic\Exports\TimetableExport;
use Modules\Academic\Repositories\AcademicCalendarRepository;
use Modules\Academic\Repositories\AcademicSpaceRepository;
use Modules\Academic\Repositories\AcademicTimetableAutoGenRepository;
use Modules\Academic\Repositories\AcademicTimetableRepository;
use Modules\Academic\Entities\ExamCategory;
use Modules\Academic\Entities\ExamType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AcademicTimetableController extends Controller
{
    private AcademicTimetableRepository $repository;
    private bool $trash = false;
    private bool $academic = false;
    private bool $isOldTT = false;

    public function __construct()
    {
        $this->repository = new AcademicTimetableRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        if ($this->academic) {
            $pageTitle = "Academic Timetables";
        } else {
            $pageTitle = "Master Timetables";
        }

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new AcademicTimetable());

        $this->repository->setColumns("id", "timetable_name", "course", "syllabus", "batch",
            "academic_year", "semester", "auto_gen_status", "status", "approval_status", "created_at")
            ->setColumnLabel("auto_gen_status", "Auto Generation Status")
            ->setColumnDBField("course", "course_id")
            ->setColumnFKeyField("course", "course_id")
            ->setColumnRelation("course", "course", "course_name")
            ->setColumnDBField("batch", "batch_id")
            ->setColumnFKeyField("batch", "batch_id")
            ->setColumnRelation("batch", "batch", "batch_name")
            ->setColumnDBField("academic_year", "academic_year_id")
            ->setColumnFKeyField("academic_year", "academic_year_id")
            ->setColumnRelation("academic_year", "academicYear", "year_name")
            ->setColumnDBField("semester", "semester_id")
            ->setColumnFKeyField("semester", "semester_id")
            ->setColumnRelation("semester", "semester", "semester_name")
            ->setColumnDBField("syllabus", "syllabus_id")
            ->setColumnFKeyField("syllabus", "syllabus_id")
            ->setColumnRelation("syllabus", "syllabus", "syllabus_name")
            ->setColumnDisplay("course", array($this->repository, 'displayRelationAs'), ["course", "course_id", "course_name", URL::to("/academic/course/view/")])
            ->setColumnDisplay("academic_year", array($this->repository, 'displayRelationAs'), ["academic_year", "academic_year_id", "year_name"])
            ->setColumnDisplay("semester", array($this->repository, 'displayRelationAs'), ["semester", "semester_id", "semester_name"])
            ->setColumnDisplay("batch", array($this->repository, 'displayRelationAs'), ["batch", "batch_id", "batch_name"])
            ->setColumnDisplay("syllabus", array($this->repository, 'displayRelationAs'), ["syllabus", "id", "name", URL::to("/academic/course_syllabus/view/")])
            ->setColumnDisplay("auto_gen_status", array($this->repository, 'displayStatusAs'), [$this->repository->autoGenStatuses])
            ->setColumnDisplay("status", array($this->repository, 'displayStatusAs'), [$this->repository->statuses])
            ->setColumnDisplay("approval_status", array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnFilterMethod("course", "select", [
                "options" => URL::to("/academic/course/search_data"),
                "basedColumns" => [
                    [
                        "column" => "department",
                        "param" => "dept_id",
                    ]
                ],
            ])
            ->setColumnFilterMethod("batch", "select", [
                "options" => URL::to("/academic/batch/search_data"),
                "basedColumns" => [
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ]
                ],
            ])
            ->setColumnFilterMethod("syllabus", "select", [
                "options" => URL::to("/academic/course_syllabus/search_data"),
                "basedColumns" => [
                    [
                        "column" => "syllabus",
                        "param" => "syllabus_id",
                    ]
                ],
            ])
            ->setColumnFilterMethod("academic_year", "select", [
                "options" => URL::to("/academic/academic_year/search_data"),
                "basedColumns" => [
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ]
                ],
            ])
            ->setColumnFilterMethod("semester", "select", [
                "options" => URL::to("/academic/academic_semester/search_data"),
                "basedColumns" => [
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ]
                ],
            ])
            ->setColumnFilterMethod("auto_gen_status", "select", $this->repository->autoGenStatuses)
            ->setColumnFilterMethod("status", "select", $this->repository->statuses)
            ->setColumnFilterMethod("approval_status", "select", $this->repository->approvalStatuses)
            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        $this->repository->setCustomFilters("faculty", "department")
            ->setColumnDBField("faculty", "faculty_id", true)
            ->setColumnFKeyField("faculty", "faculty_id", true)
            ->setColumnRelation("faculty", "faculty", "faculty_name", true)
            ->setColumnFilterMethod("faculty", "select", URL::to("/academic/faculty/search_data"), true)
            ->setColumnDBField("department", "dept_id", true)
            ->setColumnFKeyField("department", "dept_id", true)
            ->setColumnRelation("department", "department", "dept_name", true)
            ->setColumnFilterMethod("department", "select", [
                "options" => URL::to("/academic/department/search_data"),
                "basedColumns" => [
                    [
                        "column" => "faculty",
                        "param" => "faculty_id",
                    ]
                ],
            ], true);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = $pageTitle . " | Trashed";

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");

            if ($this->academic) {
                $this->repository->setUrl("list", "/academic/academic_timetable/academic/");
            }
        } else {
            $query = $this->repository->model::query();

            $tableTitle = $pageTitle;

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export", "view")
                ->setRowActionBeforeButton(URL::to("/academic/academic_timetable_criteria/"), "Set&nbsp;Criteria", "", "fas fa-cog")
                ->setRowActionBeforeButton(URL::to("/academic/academic_timetable/prepare/"), "Prepare", "", "fa fa-calendar");

            if ($this->academic) {
                $this->repository->setUrl("trashList", "/academic/academic_timetable/academic_trash/");
            }
        }

        $this->repository->setUrlIcon("view", "fa fa-calendar-alt");

        $query = $query->with(["course", "batch", "academicYear", "semester", "syllabus"]);

        if ($this->academic) {
            $this->repository->unsetColumns("approval_status", "auto_gen_status");
            $this->repository->setUrl("create", "/academic/academic_timetable/create");
            $this->repository->setUrl("edit", "/academic/academic_timetable/edit/");
            $this->repository->setUrl("view", "/academic/academic_timetable/view/");
            $this->repository->setUrl("trash", "/academic/academic_timetable/delete/");
            $this->repository->setUrl("restore", "/academic/academic_timetable/restore/");
            $query->where("type", "2");
        } else {
            $query->where("type", "1");
        }

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function trash()
    {
        $this->trash = true;
        return $this->index();
    }

    public function academic()
    {
        $this->academic = true;
        return $this->index();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function academicTrash()
    {
        $this->trash = true;
        $this->academic = true;
        return $this->index();
    }

    /**
     * Show the form for creating a new resource.
     * @return Factory|View
     */
    public function create()
    {
        $model = new AcademicTimetable();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = URL::to("/" . request()->path());

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/academic_timetable");

        $this->repository->setPageUrls($urls);

        return view('academic::academic_timetable.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(): JsonResponse
    {
        $model = new AcademicTimetable();
        $model = $this->repository->getValidatedData($model, [
            "timetable_name" => "required",
            "academic_calendar_id" => "required|exists:academic_calendars,academic_calendar_id",
            "syllabus_id" => "required|exists:course_syllabi,syllabus_id",
            "syllabus_lesson_plan_id" => "required|exists:syllabus_lesson_plans,id",
            "batch_availability_type" => "required",
            "course_id" => "",
            "academic_year_id" => "",
            "semester_id" => "",
            "batch_id" => "",
            "auto_gen_status" => "",
        ], [], ["academic_calendar_id" => "Academic Calendar", "syllabus_id" => "Syllabus", "syllabus_lesson_plan_id" => "Syllabus Lesson Plan"]);

        if ($this->repository->isValidData) {
            $model->type = 1; //set as master timetable

            $model->dept_id = $this->repository->getDepartmentIdFromCourseId($model->course_id);
            $model->faculty_id = $this->repository->getFacultyIdFromDepartmentId($model->dept_id);

            $response = $this->repository->saveModel($model);
        } else {
            $response = $model;
        }

        return $this->repository->handleResponse($response);
    }

    public function export($id)
    {
        return $this->show($id, true);
    }

    /**
     * @param $id
     * @param mixed $export
     * @return Application|Factory|JsonResponse|RedirectResponse|View|BinaryFileResponse|void|null
     */
    public function show($id, $export = false)
    {
        $model = AcademicTimetable::with(["academic", "course", "academicYear", "semester", "batch", "syllabus"])->find($id);

        if ($model) {

            if ($model->auto_gen_status !== 1) {

                $academicYearId = $model->academic_year_id;
                $semesterId = $model->semester_id;
                $syllabusId = $model->syllabus_id;

                $acaCalRepo = new AcademicCalendarRepository();
                $academicCalendar = $acaCalRepo->getAcademicCalendarInfo($model->academic_calendar_id);
                $this->repository->deliveryModeId = $model->delivery_mode_id;

                if ($academicCalendar) {

                    //check if this timetable has at least single criteria
                    $criteriaRecords = AcademicTimetableCriteria::query()
                        ->where("academic_timetable_id", $id)
                        ->get()->toArray();

                    $deliveryModes = null;
                    if (count($criteriaRecords) > 0) {

                        $deliveryModes = [];
                        foreach ($criteriaRecords as $criteriaRecord) {

                            $deliveryModes[] = $criteriaRecord["delivery_mode_id"];
                        }
                    } else {

                        if (!empty($model->delivery_mode_id)) {

                            $this->isOldTT = true;

                            $deliveryModes[] = $model->delivery_mode_id;
                        } else {

                            $response = [];
                            $response["status"] = "failed";
                            $response["notify"][] = "Please finish timetable criteria setup before preparing timetable.";

                            return $this->repository->handleResponse($response);
                        }
                    }

                    if ($deliveryModes) {

                        $model->department = $model->course->department;
                        $model->faculty = $model->department->faculty->toArray();

                        $model->department = $model->department->toArray();

                        $syllabusModules = SyllabusModule::with(["module", "deliveryModes"])
                            ->where("syllabus_id", $syllabusId)
                            ->whereHas("module", function ($query) use ($academicYearId, $semesterId) {

                                $query->where("academic_year_id", $academicYearId);
                                $query->where("semester_id", $semesterId);
                            })->get();

                        $courseLecturers = $model->course->lecturers->where("status", "1")->toArray();

                        $courseLecturersPrepared = [];
                        if (isset($courseLecturers) && count($courseLecturers) > 0) {
                            foreach ($courseLecturers as $lec) {
                                $lecturer = [];
                                $lecturer["id"] = $lec["id"];
                                $lecturer["name"] = $lec["name"];

                                $courseLecturersPrepared[] = $lecturer;
                            }

                            $courseLecturers = $courseLecturersPrepared;
                        }

                        $modules = [];
                        $moduleHoursByMode = [];
                        if (count($syllabusModules) > 0) {

                            foreach ($syllabusModules as $syllabusModule) {

                                $moduleModel = $syllabusModule->module;

                                if ($moduleModel !== null) {

                                    $lecturers = $moduleModel->moduleLecturerIds->keyBy("lecturer_id")->toArray();
                                    $lecturerIds = array_keys($lecturers);

                                    $mod = $moduleModel->toArray();
                                    $sMDMs = $syllabusModule->deliveryModes;

                                    $modes = [];
                                    if (count($sMDMs) > 0) {

                                        foreach ($sMDMs as $sMDM) {

                                            $dm = $sMDM->deliveryMode;

                                            $mode = [];
                                            $mode["id"] = $dm->id;
                                            $mode["name"] = $dm->name;
                                            $mode["hours"] = $sMDM->hours;

                                            $modes[] = $mode;
                                            $moduleHoursByMode[$mod["id"]][$dm->id] = $sMDM->hours;
                                        }
                                    }

                                    $module = [];
                                    $module["id"] = $mod["id"];
                                    $module["name"] = $mod["name"];
                                    $module["code"] = $mod["module_code"];
                                    $module["modes"] = $modes;
                                    $module["color_code"] = $mod["module_color_code"];
                                    $module["text_color"] = $this->repository->getTextColor($mod["module_color_code"]);
                                    $module["lecturerIds"] = $lecturerIds;

                                    $modules[] = $module;
                                }
                            }
                        }

                        $model->modules = $modules;
                        $model->lecturers = $courseLecturers;

                        $record = $model->toArray();

                        $lecturersById = [];
                        if (is_array($record["lecturers"]) && count($record["lecturers"]) > 0) {
                            foreach ($record["lecturers"] as $lecturer) {
                                $lecturersById[$lecturer["id"]] = $lecturer;
                            }
                        }

                        $record["lecturersById"] = $lecturersById;

                        $masterId = false;
                        $academicId = false;
                        if ($model->type === 2) {

                            $masterId = $model->master_timetable_id;
                        } else {

                            if (isset($model->academic->id)) {

                                $academicId = $model->academic->id;
                            }
                        }

                        $record["academicCalendar"] = $academicCalendar;
                        $query = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic",
                            "deliveryMode", "deliveryModeSpecial", "examType",
                            "examCategory", "lecturers", "subgroups", "spaces", "attendance"])
                            ->where("slot_status", 1);

                        if ($this->isOldTT) {
                            $query->whereHas("ttInfoSubgroups", function ($query) use ($model) {

                                $query->where("subgroup_id", $model->subgroup_id);
                            });
                        } else {

                            $query->where(function ($query) use ($model) {

                                $query->where(function ($query) use ($model) {

                                    $query->where("academic_timetable_id", "=", $model->id);
                                })->orWhereHas("ttInfoSubgroups", function ($query) use ($model) {

                                    $query->whereHas("subgroup", function ($query) use ($model) {

                                        $query->where("batch_id", $model->batch_id);
                                        $query->where("year", $model->academic_year_id);
                                        $query->where("semester", $model->semester_id);
                                    });
                                });
                            });
                        }

                        $query->whereHas("module");
                        $query->whereHas("timetable", function ($query) use ($model) {

                            $query->where(function ($query) use ($model) {

                                $query->where("type", 1);
                                $query->where(function ($query) use ($model) {

                                    $query->where("academic_timetable_id", "=", $model->id);
                                })->orWhere(function ($query) use ($model) {

                                    $query->where("academic_timetable_id", "!=", $model->id)->whereDoesntHave("academic");
                                });
                            })->orWhere(function ($query) {

                                $query->where("type", 2);
                            });
                        });

                        if ($masterId) {
                            $query->where("academic_timetable_id", "!=", $masterId);
                        } elseif ($academicId) {

                            $query->where("academic_timetable_id", "!=", $academicId);
                        }

                        $records = $query->get()->toArray();

                        $this->repository->prepareLecturerHours = true;
                        $record["timetable"] = $this->repository->getTimetable($records);

                        if (count($deliveryModes) > 1) {

                            $pickedModuleHours = $this->repository->getPickedBatchModuleHours($model);
                            $moduleSubgroups = $this->repository->getTimetableModuleSubgroups($model);
                        } else {

                            $pickedModuleHours = $this->repository->getPickedBatchModuleHours($model, $deliveryModes[0]);
                            $moduleSubgroups = $this->repository->getTimetableModuleSubgroups($model, $deliveryModes[0]);
                        }

                        $record["moduleHoursByMode"] = $moduleHoursByMode;
                        $record["pickedModuleHours"] = $pickedModuleHours;
                        $record["moduleSubgroups"] = $moduleSubgroups;

                        $urls = [];
                        $urls["editUrl"] = URL::to("/academic/academic_timetable/edit/" . $id);
                        $urls["exportUrl"] = URL::to("/academic/academic_timetable/export/" . $id);
                        $urls["prepareUrl"] = URL::to("/academic/academic_timetable/prepare/" . $id);
                        $urls["addUrl"] = URL::to("/academic/academic_timetable/create");
                        $urls["listUrl"] = URL::to("/academic/academic_timetable");
                        $urls["lecturerUrl"] = URL::to("/academic/lecturer/view/");
                        $urls["lecturerTTUrl"] = URL::to("/academic/lecturer/timetable/");
                        $urls["moduleCompletionUrl"] = URL::to("/academic/timetable_completed_module/" . $id);

                        if ($record["type"] === 1) {
                            $slave = $this->repository->hasAcademicTimetable($id);
                            if ($slave) {
                                $urls["slaveUrl"] = URL::to("/academic/academic_timetable/view/" . $slave->id);
                            }
                        } else {
                            $urls["masterUrl"] = URL::to("/academic/academic_timetable/view/" . $record["master_timetable_id"]);
                        }

                        $this->repository->setPageUrls($urls);

                        $record["ttLecturers"] = $this->repository->ttLecturers;
                        $record["ttLecturerHours"] = $this->repository->ttLecturerHours;

                        $ttExport = new TimetableExport();
                        $ttExport->record = $record;
                        $ttExport->prepareData();

                        $record["moduleSubgroupHours"] = Helper::convertObjectToArray($ttExport->record["moduleSubgroupHours"]);

                        if ($export) {

                            return Excel::download($ttExport, Str::slug($model->name) . ".xlsx");
                        } else {
                            $record["availableLecturersFetchUrl"] = URL::to("/academic/academic_timetable/get_available_lecturers/" . $id);
                            $record["availableSpacesFetchUrl"] = URL::to("/academic/academic_timetable/get_available_spaces/" . $id);
                            $record["spaceIds"] = AcademicSpaceRepository::getAcademicSpaceIds();

                            return view('academic::academic_timetable.view', compact('record'));
                        }
                    }
                } else {
                    $aCModel = new AcademicCalendar();
                    $aCModel->course_id = $model->course_id;
                    $aCModel->academic_year_id = $model->academic_year_id;
                    $aCModel->semester_id = $model->semester_id;
                    $aCModel->batch_id = $model->batch_id;

                    $response["status"] = "failed";
                    $response["notify"][] = $aCModel->name . " academic calendar has been removed.";
                    $response["notify"][] = "Academic timetable will not be available until fix this issue.";

                    return $this->repository->handleResponse($response);
                }
            } else {

                $response = [];
                $response["status"] = "failed";
                $response["notify"][] = "Timetable update is not available while auto generation of this timetable is in progress.";

                return $this->repository->handleResponse($response);
            }
        } else {

            $response = [];
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $id
     * @return Application|Factory|JsonResponse|RedirectResponse|View|null
     */
    public function edit($id)
    {
        $model = AcademicTimetable::with(["academicCalendar", "syllabus", "lessonPlan"])->find($id);

        if ($model) {
            if ($model->auto_gen_status === 1) {
                $notify = array();
                $notify["status"] = "failed";
                $notify["notify"][] = "Timetable update is not available while auto generation of this timetable is in progress";

                $response["notify"] = $notify;

                return $this->repository->handleResponse($response, true);
            }

            if ($this->repository->isMasterTimetable($model)) {
                if ($this->repository->hasAcademicTimetable($model->id)) {
                    $notify = array();
                    $notify["status"] = "failed";
                    $notify["notify"][] = "Master timetable is not allowed to edit.";

                    $response["notify"] = $notify;

                    return $this->repository->handleResponse($response, true);
                }
            }

            $model->department = $model->course->department;
            $model->faculty = $model->department->faculty->toArray();

            $model->department = $model->department->toArray();

            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = URL::to("/" . request()->path());

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/academic_timetable/create");
            $urls["listUrl"] = URL::to("/academic/academic_timetable");
            $urls["exportUrl"] = URL::to("/academic/academic_timetable/export/" . $id);

            $this->repository->setPageUrls($urls);

            return view('academic::academic_timetable.create', compact('formMode', 'formSubmitUrl', 'record'));
        } else {

            $response = [];
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $id
     * @return JsonResponse
     * @throws ValidationException
     */
    public function update($id): JsonResponse
    {
        $model = AcademicTimetable::query()->find($id);

        if ($model) {
            if ($this->repository->isMasterTimetable($model)) {
                if ($this->repository->hasAcademicTimetable($model->id) && $model->status == "1") {
                    $notify = array();
                    $notify["status"] = "failed";
                    $notify["notify"][] = "Master timetable is not allowed to edit.";

                    $response["notify"] = $notify;

                    return $this->repository->handleResponse($response, true);
                }
            }

            if ($model->auto_gen_status !== 1) {

                $model = $this->repository->getValidatedData($model, [
                    "timetable_name" => "required",
                    "academic_calendar_id" => "required|exists:academic_calendars,academic_calendar_id",
                    "syllabus_id" => "required|exists:course_syllabi,syllabus_id",
                    "syllabus_lesson_plan_id" => "required|exists:syllabus_lesson_plans,id",
                    "batch_availability_type" => "required",
                    "course_id" => "",
                    "academic_year_id" => "",
                    "semester_id" => "",
                    "batch_id" => "",
                    "auto_gen_status" => "",
                ], [], ["academic_calendar_id" => "Academic Calendar", "syllabus_id" => "Syllabus", "syllabus_lesson_plan_id" => "Syllabus Lesson Plan"]);

                if ($this->repository->isValidData) {

                    $generateNow = false;
                    if (request()->post("generate_tt") == "1") {

                        $generateNow = true;
                        $model->auto_gen_status = 0;
                    }

                    $model->dept_id = $this->repository->getDepartmentIdFromCourseId($model->course_id);
                    $model->faculty_id = $this->repository->getFacultyIdFromDepartmentId($model->dept_id);

                    $response = $this->repository->saveModel($model);

                    if ($response["notify"]["status"] == "success") {

                        if ($generateNow) {
                            $autoGenRepo = new AcademicTimetableAutoGenRepository();
                            $autoGenRepo->autoGenerateTimetable($model);
                        }
                    }
                } else {
                    $response = $model;
                }
            } else {
                $notify = array();
                $notify["status"] = "failed";
                $notify["notify"][] = "Details saving was failed.";
                $notify["notify"][] = "Timetable update is not available while auto generation of this timetable is in progress";

                $response["notify"] = $notify;
            }
        } else {
            $notify = array();
            $notify["status"] = "failed";
            $notify["notify"][] = "Details saving was failed. Requested record does not exist.";

            $response["notify"] = $notify;
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * @param $id
     * @return JsonResponse|RedirectResponse|null
     */
    public function delete($id)
    {
        $model = AcademicTimetable::query()->find($id);
        if (!$model || $model->auto_gen_status !== 1) {
            return $this->repository->delete($model);
        } else {
            $notify = array();
            $notify["status"] = "failed";
            $notify["notify"][] = "Timetable deletion was failed.";
            $notify["notify"][] = "Timetable update is not available while auto generation of this timetable is in progress";

            $response = [];
            $response["notify"] = $notify;

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $id
     * @return JsonResponse|RedirectResponse|null
     */
    public function restore($id)
    {
        $model = AcademicTimetable::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function timetable($id)
    {
        $model = AcademicTimetable::query()
            ->select(["academic_timetable_id", "timetable_name", "delivery_mode_id"])
            ->find($id);

        if ($model) {

            //check if this timetable has at least single criteria
            $criteriaRecord = AcademicTimetableCriteria::query()
                ->where("academic_timetable_id", $id)
                ->first();

            if ($criteriaRecord) {

                $pageTitle = $model->name . " | Timetable preparation Selection";

                $this->repository->setPageTitle($pageTitle);

                $urls = [];
                $urls["addUrl"] = URL::to("/academic/academic_timetable/create");
                $urls["editUrl"] = URL::to("/academic/academic_timetable/edit/" . $id);

                $options = [];
                $options["fetchUrl"] = URL::to("/academic/academic_timetable/prepare/" . $id) . "/criteria";

                $this->repository->setPageUrls($urls);
                $this->repository->initDatatable(new AcademicTimetableCriteria(), $options);

                $this->repository->setColumns("id", "delivery_mode", "mode_type")
                    ->setColumnLabel("mode_type", "Timetable Mode")
                    ->setColumnDBField("delivery_mode", "delivery_mode_id")
                    ->setColumnFKeyField("delivery_mode", "delivery_mode_id")
                    ->setColumnRelation("delivery_mode", "deliveryMode", "mode_name")
                    ->setColumnDisplay("delivery_mode", array($this->repository, 'displayRelationAs'), ["delivery_mode", "deliveryMode", "name"])
                    ->setColumnFilterMethod("delivery_mode", "select", URL::to("/academic/module_delivery_mode/search_data"))
                    ->setColumnDBField("id", "delivery_mode_id");

                $query = $this->repository->model::withTrashed();

                $this->repository->setTableTitle($pageTitle)
                    ->enableViewData("edit")
                    ->disableViewData("add", "view", "delete", "restore", "trashList", "trash", "export");

                $url = URL::to("/academic/academic_timetable/prepare/" . $id) . "/";
                $this->repository->setUrl("edit", $url);

                $query->where("academic_timetable_id", $id);

                $query = $query->with(["deliveryMode"]);

                return $this->repository->render("academic::layouts.master")->index($query);
            } else {

                if (!empty($model->delivery_mode_id)) {

                    $this->isOldTT = true;
                    return $this->prepare($id, $model->delivery_mode_id);
                } else {

                    $response["status"] = "failed";
                    $response["notify"][] = "Please finish timetable criteria setup before preparing timetable.";

                    return $this->repository->handleResponse($response);
                }
            }
        } else {

            $response = [];
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * Show the form for editing the specified resource.
     * @param $id
     * @param $deliveryModeId
     * @return Factory|JsonResponse|RedirectResponse|View
     */
    public function prepare($id, $deliveryModeId)
    {
        $model = AcademicTimetable::with(["course", "academicYear", "semester", "batch", "syllabus"])->find($id);

        if ($model) {
            if ($this->repository->isMasterTimetable($model)) {
                if ($this->repository->hasAcademicTimetable($model->id)) {
                    $notify = array();
                    $notify["status"] = "failed";
                    $notify["notify"][] = "Master timetable is not allowed to edit.";

                    $response["notify"] = $notify;

                    return $this->repository->handleResponse($response, true);
                }
            }

            if ($model->auto_gen_status !== 1) {
                $academicYearId = $model->academic_year_id;
                $semesterId = $model->semester_id;
                $syllabusId = $model->syllabus_id;

                $acaCalRepo = new AcademicCalendarRepository();
                $academicCalendar = $acaCalRepo->getAcademicCalendarInfo($model->academic_calendar_id);

                if ($academicCalendar) {
                    $model->department = $model->course->department;
                    $model->faculty = $model->department->faculty->toArray();

                    $model->department = $model->department->toArray();

                    $deliveryModeId = intval($deliveryModeId);

                    $syllabusModules = SyllabusModule::with(["module", "deliveryModes"])
                        ->where("syllabus_id", $syllabusId)
                        ->whereHas("deliveryModes", function ($query) use ($deliveryModeId) {

                            $query->where("delivery_mode_id", $deliveryModeId);
                        })
                        ->whereHas("module", function ($query) use ($academicYearId, $semesterId) {

                            $query->where("academic_year_id", $academicYearId);
                            $query->where("semester_id", $semesterId);
                        })->get();
                    $courseLecturers = $model->course->lecturers->where("status", "1")->toArray();

                    $courseLecturersPrepared = [];
                    $courseLecturersPreparedIds = [];
                    if (isset($courseLecturers) && count($courseLecturers) > 0) {
                        foreach ($courseLecturers as $lec) {
                            if (!in_array($lec["id"], $courseLecturersPreparedIds)) {

                                $lecturer = [];
                                $lecturer["id"] = $lec["id"];
                                $lecturer["name"] = $lec["name"];

                                $courseLecturersPrepared[] = $lecturer;
                                $courseLecturersPreparedIds[] = $lec["id"];
                            }
                        }

                        $courseLecturers = $courseLecturersPrepared;
                    }

                    $modules = [];
                    if (count($syllabusModules) > 0) {

                        foreach ($syllabusModules as $syllabusModule) {

                            $moduleModel = $syllabusModule->module;

                            if ($moduleModel !== null) {

                                $sMDMs = $syllabusModule->deliveryModes;

                                $hours = 0;
                                foreach ($sMDMs as $sMDM) {

                                    if ($sMDM->delivery_mode_id === $deliveryModeId) {

                                        $hours = $sMDM->hours;
                                        break;
                                    }
                                }

                                $mod = $moduleModel->toArray();

                                $lecturers = $moduleModel->moduleLecturerIds->keyBy("lecturer_id")->toArray();
                                $lecturerIds = array_keys($lecturers);

                                $module = [];
                                $module["id"] = $mod["id"];
                                $module["name"] = $mod["name"];
                                $module["code"] = $mod["module_code"];
                                $module["hours"] = $hours;
                                $module["lecturerIds"] = $lecturerIds;

                                $modules[] = $module;
                            }
                        }
                    }

                    $model->modules = $modules;
                    $model->moduleSubgroups = $this->repository->getTimetableModuleSubgroups($model, $deliveryModeId);
                    $model->lecturers = $courseLecturers;
                    $model->spaceIds = AcademicSpaceRepository::getAcademicSpaceIds();

                    $model->deliveryModes = ModuleDeliveryMode::query()->select("delivery_mode_id", "mode_name")->where("mode_status", "1")->get()->toArray();
                    $model->examTypes = ExamType::query()->select("exam_type_id", "exam_type")->where("type_status", "1")->get()->toArray();
                    $model->examCategories = ExamCategory::query()->select("exam_category_id", "exam_category")->where("category_status", "1")->get()->toArray();

                    $record = $model->toArray();

                    $record["delivery_mode"] = ModuleDeliveryMode::query()->find($deliveryModeId)->toArray();

                    $formSubmitUrl = URL::to("/" . request()->path());
                    if ($this->isOldTT) {

                        $formSubmitUrl .= "/" . $deliveryModeId;
                    }

                    $record["academicCalendar"] = $academicCalendar;
                    $record["timetableDataUrl"] = URL::to("/academic/academic_timetable/timetable_data/" . $id . "/" . $deliveryModeId);
                    $record["availableLecturersFetchUrl"] = URL::to("/academic/academic_timetable/get_available_lecturers/" . $id);
                    $record["availableSubgroupsFetchUrl"] = URL::to("/academic/academic_timetable/get_available_subgroups/" . $id);
                    $record["availableSpacesFetchUrl"] = URL::to("/academic/academic_timetable/get_available_spaces/" . $id);
                    $record["cancelledSlotFetchUrl"] = URL::to("/academic/academic_timetable_information/search_data");
                    $record["topicUrl"] = URL::to("/academic/syllabus_lesson_topic/search_data");
                    $record["rescheduleUrl"] = URL::to("/academic/academic_timetable_slot_reschedule/create");
                    $record["revisionUrl"] = URL::to("/academic/academic_timetable_slot_revision/create");
                    $record["reliefUrl"] = URL::to("/academic/academic_timetable_slot_relief/create");
                    $record["defaultUrl"] = URL::to("/academic/academic_timetable_information/create");
                    $record["deliveryModeId"] = $deliveryModeId;

                    $urls = [];
                    $urls["viewUrl"] = URL::to("/academic/academic_timetable/view/");
                    $urls["editUrl"] = URL::to("/academic/academic_timetable/edit/" . $id);
                    $urls["exportUrl"] = URL::to("/academic/academic_timetable/export/" . $id);
                    $urls["addUrl"] = URL::to("/academic/academic_timetable/create");
                    $urls["listUrl"] = URL::to("/academic/academic_timetable");
                    $urls["lecturerUrl"] = URL::to("/academic/lecturer/view/");
                    $urls["lecturerTTUrl"] = URL::to("/academic/lecturer/timetable/");

                    if ($record["type"] == 1) {
                        $slave = $this->repository->hasAcademicTimetable($id);
                        if ($slave) {
                            $urls["slaveUrl"] = URL::to("/academic/academic_timetable/prepare/" . $slave->id);
                        }
                    } else {
                        $urls["masterUrl"] = URL::to("/academic/academic_timetable/prepare/" . $record["master_timetable_id"]);
                    }

                    $this->repository->setPageUrls($urls);

                    return view('academic::academic_timetable.timetable', compact('formSubmitUrl', 'record', 'deliveryModeId'));
                } else {
                    $aCModel = new AcademicCalendar();
                    $aCModel->course_id = $model->course_id;
                    $aCModel->academic_year_id = $model->academic_year_id;
                    $aCModel->semester_id = $model->semester_id;
                    $aCModel->batch_id = $model->batch_id;

                    $response["status"] = "failed";
                    $response["notify"][] = $aCModel->name . " academic calendar has been removed.";
                    $response["notify"][] = "Academic timetable will not be available until fix this issue.";
                }
            } else {

                $response = [];
                $response["status"] = "failed";
                $response["notify"][] = "Timetable update is not available while auto generation of this timetable is in progress.";
            }
        } else {

            $response = [];
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";
        }

        return $this->repository->handleResponse($response);
    }

    public function updateTimetable($id, $deliveryModeId)
    {
        $model = AcademicTimetable::query()->find($id);

        if ($model) {
            $timetable = request()->post("timetable");
            $this->repository->deliveryModeId = intval($deliveryModeId);

            if ($timetable != "") {
                $timetable = json_decode($timetable, true);
                $response = $this->repository->updateTimetable($model, $timetable);

                if ($response["notify"]["status"] == "success") {

                    $records = $response["data"];

                    $response["data"] = [];
                    $response["data"]["records"] = $records;
                    $response["data"]["pickedModuleHours"] = $this->repository->getPickedBatchModuleHours($model, $deliveryModeId);

                    if ($model->approval_status == "") {
                        if (request()->post("send_for_approval") == "1") {
                            $response = $this->repository->startApprovalProcess($model, 0, $response);
                        }
                    }
                }
            } else {
                $notify = array();
                $notify["status"] = "failed";
                $notify["notify"][] = "Details saving was failed. Timetable data missing in request.";

                $response["notify"] = $notify;
            }
        } else {
            $notify = array();
            $notify["status"] = "failed";
            $notify["notify"][] = "Details saving was failed. Requested record does not exist.";

            $response["notify"] = $notify;
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * @param $id
     * @param $deliveryModeId
     * @return JsonResponse
     */
    public function getTimetableData($id, $deliveryModeId): JsonResponse
    {
        $model = AcademicTimetable::with(["academic", "master"])->find($id);

        if ($model) {

            $weekDays = request()->post("week_days");
            $this->repository->deliveryModeId = intval($deliveryModeId);

            if ($weekDays !== "") {
                $weekDays = json_decode($weekDays, true);
            } else {
                $weekDays = [];
            }

            if (count($weekDays) === 0) {
                if (!empty($model->week_days)) {
                    $weekDays = $model->week_days;
                }
            }

            $acaCalRepo = new AcademicCalendarRepository();
            $academicCalendar = $acaCalRepo->getAcademicCalendarInfo($model->academic_calendar_id);

            if ($academicCalendar) {
                $records = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic",
                    "deliveryMode", "deliveryModeSpecial", "examType",
                    "examCategory", "lecturers", "subgroups", "spaces", "attendance"])
                    ->where("academic_timetable_id", $id)
                    ->where("delivery_mode_id", $deliveryModeId)
                    ->where(function ($query) {

                        //active
                        $query->where("slot_status", 1)
                            ->orWhere(function ($query) {

                                //or pending reschedule, revision, relief
                                $query->whereIn("slot_type", [3, 4, 5])
                                    ->whereIn("approval_status", [0, 3, 5, 7, 9, 11]);
                            });
                    })
                    ->whereHas("module")
                    ->get()->toArray();

                $data = $this->repository->getTimetableInfo($model, $records, $academicCalendar, $weekDays);

                $notify["status"] = "success";

                $response["data"]["records"] = $data;
                $response["data"]["pickedModuleHours"] = $this->repository->getPickedBatchModuleHours($model, $deliveryModeId);
            } else {
                $aCModel = new AcademicCalendar();
                $aCModel->course_id = $model->course_id;
                $aCModel->academic_year_id = $model->academic_year_id;
                $aCModel->semester_id = $model->semester_id;
                $aCModel->batch_id = $model->batch_id;

                $notify["status"] = "failed";
                $notify["notify"][] = $aCModel->name . " academic calendar has been removed.";
                $notify["notify"][] = "Academic timetable will not be available until fix this issue.";
            }
        } else {
            $notify["status"] = "failed";
            $notify["notify"][] = "Requested timetable data does not exist.";
        }

        $response["notify"] = $notify;

        return response()->json($response, 201);
    }

    /**
     * @param Request $request
     * @return JsonResponse|RedirectResponse|null
     */
    public function getAvailableLecturers(Request $request)
    {
        if ($request->expectsJson()) {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $timetableInfoId = $request->post("timetable_info_id");
            $date = $request->post("date");
            $startTime = $request->post("start_time");
            $endTime = $request->post("end_time");
            $lecturerIds = $request->post("lecturer_ids");
            $limit = $request->post("limit");

            //get available lecturer id's first
            $availableLecturerIds = $this->repository->getAvailableLecturerIds($timetableInfoId, $date, $startTime, $endTime, $lecturerIds);

            $query = Lecturer::query()
                ->select("id", "given_name", "surname", "title_id")
                ->where("status", "1")
                ->whereIn("id", $availableLecturerIds)
                ->orderBy("given_name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($searchText != "") {
                $query->where(function ($query) use ($searchText) {
                    $query->where("name_with_init", "LIKE", "%" . $searchText . "%")
                        ->orWhere("name_in_full", "LIKE", "%" . $searchText . "%")
                        ->orWhere("given_name", "LIKE", "%" . $searchText . "%")
                        ->orWhere("surname", "LIKE", "%" . $searchText . "%");
                });
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("id", $idNot);
            }

            $data = $query->get();

            $response["status"] = "success";
            $response["data"] = $data;

            return response()->json($response, 201);
        } else {

            $response = [];
            $response["status"] = "failed";
            $response["notify"][] = "You are not allowed to access this data";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse|RedirectResponse|null
     */
    public function getAvailableSpaces(Request $request)
    {
        if ($request->expectsJson()) {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $timetableInfoId = $request->post("timetable_info_id");
            $date = $request->post("date");
            $startTime = $request->post("start_time");
            $endTime = $request->post("end_time");
            $capacity = $request->post("capacity");
            $spaceIds = $request->post("space_ids");
            $spaceIds = @json_decode($spaceIds, true);

            //get available space ids first
            $availableSpaceIds = $this->repository->getAvailableSpaceIds($timetableInfoId, $date, $startTime, $endTime, $spaceIds);

            $query = DB::table("spaces_assign", "space")
                ->select("space.id", DB::raw("CONCAT(space_name.name, ' [', space_type.type_name, '] [', space.std_count, ' Max]') AS name"), "space.std_count AS capacity")
                ->join("space_categoryname AS space_name", "space.cn_id", "=", "space_name.id")
                ->join("space_categorytypes AS space_type", "space.type_id", "=", "space_type.id")
                ->whereNull("space.deleted_at")
                ->whereNull("space_name.deleted_at")
                ->whereNull("space_type.deleted_at")
                ->whereIn("space.id", $availableSpaceIds);

            if ($searchText != "") {
                $query = $query->where(DB::raw("CONCAT(space_name.name, ' [', space_type.type_name, ']')"), "LIKE", "%" . $searchText . "%");
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("space.id", $idNot);
            }

            $data = $query->get();

            $response["status"] = "success";
            $response["data"] = $data;

            return response()->json($response, 201);
        } else {

            $response = [];
            $response["status"] = "failed";
            $response["notify"][] = "You are not allowed to access this data";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * Search & get available lecturers
     * @param Request $request
     * @return JsonResponse
     */
    public function getAvailableSubgroups(Request $request): JsonResponse
    {
        if ($request->expectsJson()) {

            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $timetableInfoId = $request->post("timetable_info_id");
            $date = $request->post("date");
            $startTime = $request->post("start_time");
            $endTime = $request->post("end_time");
            $deliveryModeId = $request->post("delivery_mode_id");
            $moduleId = $request->post("module_id");

            //get matching subgroup id's first
            $subgroupIds = $this->repository->getMatchingSubgroupIds($deliveryModeId, $moduleId);

            $availableSubgroupIds = $this->repository->getAvailableSubgroupIds($timetableInfoId, $date, $startTime, $endTime, $subgroupIds);

            $query = Subgroup::query()
                ->select(["id", "sg_name", "max_students"])
                ->whereIn("id", $availableSubgroupIds);

            if ($searchText) {

                $query = $query->where("sg_name", "LIKE", "%" . $searchText . "%");
            }

            if ($idNot) {

                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("id", $idNot);
            }

            $data = $query->get();

            $response["status"] = "success";
            $response["data"] = $data;
            $response["subgroupIds"] = $subgroupIds;
            $response["availableSubgroupIds"] = $availableSubgroupIds;
        } else {

            $response["status"] = "failed";
            $response["notify"][] = "You are not allowed to access this data";
        }

        return response()->json($response, 201);
    }

    /**
     * @return JsonResponse
     */
    public function getTimetableBaseInfo(): JsonResponse
    {
        $id = request()->post("academic_timetable_id");
        $deliveryModeId = request()->post("delivery_mode_id");

        $model = AcademicTimetable::query()->find($id);

        if ($model) {

            if ($deliveryModeId) {

                $deliveryModeId = intval($deliveryModeId);
                $data = $this->repository->getTimetableBaseInfo($model, $deliveryModeId);

                $notify = [];
                $notify["status"] = "success";
                $response["data"] = $data;
            } else {

                $notify = [];
                $notify["status"] = "failed";
                $notify["notify"][] = "Delivery mode required.";
            }
        } else {

            $notify = [];
            $notify["status"] = "failed";
            $notify["notify"][] = "Requested timetable does not exist.";
        }

        $response["notify"] = $notify;

        return response()->json($response, 201);
    }

    /**
     * @param $timetableId
     * @return Application|Factory|JsonResponse|RedirectResponse|View|null
     */
    public function showTimetableFilter($timetableId = null)
    {
        if ($timetableId) {
            $model = AcademicTimetable::with(["course", "academicYear", "semester", "batch"])->find($timetableId);

            if ($model) {
                if ($model->auto_gen_status !== 1) {

                    $acaCalRepo = new AcademicCalendarRepository();
                    $academicCalendar = $acaCalRepo->getAcademicCalendarInfo($model->academic_calendar_id);

                    if ($academicCalendar) {
                        $model->department = $model->course->department;
                        $model->faculty = $model->department->faculty->toArray();

                        if (!$model->deliveryMode) {
                            $model->deliveryMode = $model->subgroup->deliveryMode->toArray();
                        }

                        $record = $model->toArray();

                        $record["academicCalendar"] = $academicCalendar;
                        $records = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic",
                            "deliveryMode", "deliveryModeSpecial", "examType",
                            "examCategory", "lecturers", "subgroups", "spaces", "attendance"])
                            ->where("academic_timetable_id", $timetableId)
                            ->whereHas("module")
                            ->whereHas("timetable")
                            ->get()->toArray();

                        $record["timetable"] = $this->repository->getTimetable($records);

                        return view('academic::academic_timetable.timetable_filter', compact('record'));
                    } else {
                        $aCModel = new AcademicCalendar();
                        $aCModel->course_id = $model->course_id;
                        $aCModel->academic_year_id = $model->academic_year_id;
                        $aCModel->semester_id = $model->semester_id;
                        $aCModel->batch_id = $model->batch_id;

                        $response["status"] = "failed";
                        $response["notify"][] = $aCModel->name . " academic calendar has been removed.";
                        $response["notify"][] = "Academic timetable will not be available until fix this issue.";

                        return $this->repository->handleResponse($response);
                    }
                } else {

                    $response = [];
                    $response["status"] = "failed";
                    $response["notify"][] = "Timetable update is not available while auto generation of this timetable is in progress.";

                    return $this->repository->handleResponse($response);
                }
            } else {

                $response = [];
                $response["status"] = "failed";
                $response["notify"][] = "Requested record does not exist.";

                return $this->repository->handleResponse($response);
            }
        } else {
            $model = new AcademicTimetable();
            $record = $model;

            $record["timetable"] = [];

            return view('academic::academic_timetable.timetable_filter', compact('record'));
        }
    }

    /**
     * @return JsonResponse|RedirectResponse|BinaryFileResponse|null
     */
    public function exportTimetableFilter()
    {
        $response = $this->repository->getFilteredTimetableData();

        if ($response["notify"]["status"] === "success") {

            $record = [];
            $record["id"] = "";
            $record["name"] = "Filtered Timetable Data";
            $record["timetable"] = $response["data"]["timetable"];
            $record["view"] = "academic::academic_timetable.export.export_timetable";

            $ttExport = new TimetableExport();
            $ttExport->record = $record;

            //return $ttExport->view();
            return Excel::download($ttExport, "filtered-timetable.xlsx");
        } else {

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @return JsonResponse
     */
    public function filterTimetables(): JsonResponse
    {
        $response = $this->repository->getFilteredTimetableData();

        return response()->json($response, 201);
    }

    /**
     * @param Request $request
     * @return JsonResponse|RedirectResponse|null
     */
    public function searchData(Request $request)
    {
        if ($request->expectsJson()) {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $limit = $request->post("limit");
            $facultyId = $request->post("faculty_id");
            $deptId = $request->post("dept_id");
            $courseId = $request->post("course_id");
            $academicYearId = $request->post("academic_year_id");
            $semesterId = $request->post("semester_id");
            $batchId = $request->post("batch_id");

            $query = AcademicTimetable::query()
                ->select("academic_timetable_id", "timetable_name")
                ->where("status", "=", "1")
                ->where("academic_timetables.type", "1")
                ->where("academic_timetables.master_timetable_id", "0")
                ->where("academic_timetables.approval_status", "=", "1")
                ->orderBy("timetable_name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($searchText != "") {
                $query = $query->where("timetable_name", "LIKE", "%" . $searchText . "%");
            }

            if ($facultyId !== null) {
                if (is_array($facultyId) && count($facultyId) > 0) {

                    $query = $query->whereIn("faculty_id", $facultyId);
                } else {
                    $query = $query->where("faculty_id", $facultyId);
                }
            }

            if ($deptId !== null) {
                if (is_array($deptId) && count($deptId) > 0) {

                    $query = $query->whereIn("dept_id", $deptId);
                } else {
                    $query = $query->where("dept_id", $deptId);
                }
            }

            if ($courseId !== null) {
                if (is_array($courseId) && count($courseId) > 0) {

                    $query = $query->whereIn("course_id", $courseId);
                } else {
                    $query = $query->where("course_id", $courseId);
                }
            }

            if ($academicYearId !== null) {
                if (is_array($academicYearId) && count($academicYearId) > 0) {

                    $query = $query->whereIn("academic_year_id", $academicYearId);
                } else {
                    $query = $query->where("academic_year_id", $academicYearId);
                }
            }

            if ($semesterId !== null) {
                if (is_array($semesterId) && count($semesterId) > 0) {

                    $query = $query->whereIn("semester_id", $semesterId);
                } else {
                    $query = $query->where("semester_id", $semesterId);
                }
            }

            if ($batchId !== null) {
                if (is_array($batchId) && count($batchId) > 0) {

                    $query = $query->whereIn("batch_id", $batchId);
                } else {
                    $query = $query->where("batch_id", $batchId);
                }
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("academic_timetable_id", $idNot);
            }

            $data = $query->get();

            return response()->json($data, 201);
        } else {

            $response = [];
            $response["status"] = "failed";
            $response["notify"][] = "You are not allowed to access this data";

            return $this->repository->handleResponse($response);
        }
    }

    public function verification($timetableId)
    {
        $model = AcademicTimetable::with(["information"])->find($timetableId);

        if ($model) {
            return $this->repository->renderApprovalView($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $timetableId
     * @return JsonResponse|RedirectResponse|null
     */
    public function verificationSubmit($timetableId)
    {
        $model = AcademicTimetable::with(["information"])->find($timetableId);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalHOD($timetableId)
    {
        $model = AcademicTimetable::with(["information"])->find($timetableId);

        if ($model) {
            return $this->repository->renderApprovalView($model, "pre_approval_hod");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $timetableId
     * @return JsonResponse|RedirectResponse|null
     */
    public function preApprovalHODSubmit($timetableId)
    {
        $model = AcademicTimetable::with(["information"])->find($timetableId);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "pre_approval_hod");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalSAR($id)
    {
        $model = AcademicTimetable::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "pre_approval_sar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $id
     * @return JsonResponse|RedirectResponse|null
     */
    public function preApprovalSARSubmit($id)
    {
        $model = AcademicTimetable::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "pre_approval_sar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalRegistrar($id)
    {
        $model = AcademicTimetable::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "pre_approval_registrar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $id
     * @return JsonResponse|RedirectResponse|null
     */
    public function preApprovalRegistrarSubmit($id)
    {
        $model = AcademicTimetable::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "pre_approval_registrar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalVC($id)
    {
        $model = AcademicTimetable::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "pre_approval_vc");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $id
     * @return JsonResponse|RedirectResponse|null
     */
    public function preApprovalVCSubmit($id)
    {
        $model = AcademicTimetable::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "pre_approval_vc");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function approval($timetableId)
    {
        $model = AcademicTimetable::with(["information"])->find($timetableId);

        if ($model) {
            return $this->repository->renderApprovalView($model, "approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $timetableId
     * @return JsonResponse|RedirectResponse|null
     */
    public function approvalSubmit($timetableId)
    {
        $model = AcademicTimetable::with(["information"])->find($timetableId);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function approvalHistory($modelHash, $id)
    {
        $model = new AcademicTimetable();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new AcademicTimetable();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
