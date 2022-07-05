<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicCalendar;
use Modules\Academic\Repositories\AcademicCalendarExtraDateRepository;
use Modules\Academic\Repositories\AcademicCalendarRepository;

class AcademicCalendarController extends Controller
{
    private AcademicCalendarRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new AcademicCalendarRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $pageTitle = "Academic Calendars";

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new AcademicCalendar());

        $this->repository->setColumns("id", "name", "course", "batch", "academic_year",
            "semester", $this->repository->completeStatusField, "ac_status", "approval_status", "created_at")
            ->setColumnLabel("ac_status", "Status")
            ->setColumnLabel($this->repository->completeStatusField, "Start/Completion Status")
            ->setColumnDBField("course", "course_id")
            ->setColumnFKeyField("course", "course_id")
            ->setColumnRelation("course", "course", "course_name")
            ->setColumnDBField("academic_year", "academic_year_id")
            ->setColumnFKeyField("academic_year", "academic_year_id")
            ->setColumnRelation("academic_year", "academicYear", "year_name")
            ->setColumnDBField("semester", "semester_id")
            ->setColumnFKeyField("semester", "semester_id")
            ->setColumnRelation("semester", "semester", "semester_name")
            ->setColumnDBField("batch", "batch_id")
            ->setColumnFKeyField("batch", "batch_id")
            ->setColumnRelation("batch", "batch", "batch_name")
            ->setColumnDisplay("course", array($this->repository, 'displayRelationAs'), ["course", "course_id", "course_name", URL::to("/academic/course/view/")])
            ->setColumnDisplay("academic_year", array($this->repository, 'displayRelationAs'), ["academic_year", "academic_year_id", "year_name"])
            ->setColumnDisplay("semester", array($this->repository, 'displayRelationAs'), ["semester", "semester_id", "semester_name"])
            ->setColumnDisplay("batch", array($this->repository, 'displayRelationAs'), ["batch", "batch_id", "batch_name"])
            ->setColumnDisplay("ac_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses, "", "", true])
            ->setColumnDisplay($this->repository->completeStatusField, array($this->repository, 'displayStatusActionAs'),
                [$this->repository->completeStatuses, URL::to("/academic/academic_calendar/change_complete_status") . "/", "comp"])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnDisplay("approval_status", array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])
            ->setColumnFilterMethod("course", "select", [
                "options" => URL::to("/academic/course/search_data"),
                "basedColumns" => [
                    [
                        "column" => "department",
                        "param" => "dept_id",
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
            ->setColumnFilterMethod("batch", "select", [
                "options" => URL::to("/academic/batch/search_data"),
                "basedColumns" => [
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ]
                ],
            ])
            ->setColumnFilterMethod($this->repository->statusField, "select", $this->repository->statuses)
            ->setColumnFilterMethod($this->repository->approvalField, "select", $this->repository->approvalStatuses)
            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        $this->repository->setCustomFilters("faculty", "department")
            ->setColumnDBField("faculty", "faculty_id", true)
            ->setColumnFKeyField("faculty", "faculty_id", true)
            ->setColumnRelation("faculty", "faculty", "name", true)
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

            $tableTitle = "Academic Calendars | Trashed";

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = "Academic Calendars";

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export", "view");
        }

        $query = $query->with(["course", "academicYear", "semester", "batch"]);

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

    /**
     * Show the form for creating a new resource.
     * @return Factory|View
     */
    public function create()
    {
        $model = new AcademicCalendar();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/academic_calendar");

        $this->repository->setPageUrls($urls);
        $calendarEvents = $this->repository->getEmptyData();

        return view('academic::academic_calendar.create', compact('formMode', 'formSubmitUrl', 'record', 'calendarEvents'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(): JsonResponse
    {
        $batchIds = request()->post("batch");

        $response = [];
        $dateFields = [];
        if (is_array($batchIds) && count($batchIds) > 0) {
            $model = new AcademicCalendar();
            $model = $this->repository->getValidatedData($model, [
                "name" => "required",
                "faculty_id" => "required|exists:faculties,faculty_id",
                "dept_id" => "required|exists:departments,dept_id",
                "course_id" => "required|exists:courses,course_id",
                "academic_year_id" => "required|exists:academic_years,academic_year_id",
                "semester_id" => "required|exists:academic_semesters,semester_id",
            ], [], ["faculty_id" => "Faculty", "dept_id" => "Department", "course_id" => "Course", "academic_year_id" => "Academic Year", "semester_id" => "Semester"]);

            if ($this->repository->isValidData) {

                //assume data is valid
                $validData = true;
                foreach ($batchIds as $batchId) {
                    $model->batch_id = $batchId;
                    if ($this->repository->isOtherCalendarExist($model)) {
                        $response["status"] = "failed";
                        $response["notify"][] = $model->base_name . " academic calendar already exists.";

                        $validData = false;
                        break;
                    }
                }
            } else {
                $validData = false;
                $response = $model;
            }

            if ($validData) {
                foreach ($batchIds as $batchId) {
                    $batchModel = $model->replicate();

                    if (!$this->repository->isValidDates) {
                        $dateFields = $this->repository->getValidatedDateFields();

                        if (!$this->repository->isValidDates) {

                            $response = $dateFields;
                            break;
                        }
                    }

                    //set ac_status as 0 when inserting the record
                    $batchModel->batch_id = $batchId;

                    if (isset($batchModel->batch->name)) {

                        $batchModel->name .= " " . $batchModel->batch->name;
                    }

                    $response = $this->repository->saveModel($batchModel);

                    if ($response["notify"]["status"] == "success") {
                        $this->repository->updateCalendar($batchModel, $dateFields);

                        $acedRepo = new AcademicCalendarExtraDateRepository();
                        $acedRepo->update($model);

                        if (request()->post("send_for_approval") == "1") {

                            DB::beginTransaction();
                            $batchModel->{$this->repository->approvalField} = 0;
                            $batchModel->save();

                            $update = $this->repository->triggerApprovalProcess($batchModel);

                            if ($update["notify"]["status"] === "success") {

                                DB::commit();

                            } else {
                                DB::rollBack();

                                if (is_array($update["notify"]) && count($update["notify"]) > 0) {

                                    foreach ($update["notify"] as $message) {

                                        $response["notify"]["notify"][] = $message;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Batch required";
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Show the specified resource.
     * @param
     * @return Factory|View
     */
    public function show($id)
    {
        $model = AcademicCalendar::with(["faculty", "department", "course", "academicYear", "semester", "batch"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/academic_calendar/create");
            $urls["listUrl"] = URL::to("/academic/academic_calendar");

            $this->repository->setPageUrls($urls);

            $calendarEvents = $this->repository->getCalendarData($id);
            $calendarEvents = $this->repository->getDataByFields($calendarEvents);

            return view('academic::academic_calendar.view', compact('record', 'calendarEvents'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Show the form for editing the specified resource.
     * @param
     * @return Factory|View
     */
    public function edit($id)
    {
        $model = AcademicCalendar::with(["faculty", "department", "course", "academicYear", "semester", "batch"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/academic_calendar/create");
            $urls["listUrl"] = URL::to("/academic/academic_calendar");

            $this->repository->setPageUrls($urls);

            $calendarEvents = $this->repository->getCalendarData($id);
            $calendarEvents = $this->repository->getDataByFields($calendarEvents);
            $dates = $this->repository->getDates($model);

            return view('academic::academic_calendar.create', compact('formMode', 'formSubmitUrl', 'record', 'calendarEvents', 'dates'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Update the specified resource in storage.
     * @param
     * @return JsonResponse
     * @throws ValidationException
     */
    public function update($id): JsonResponse
    {
        $model = AcademicCalendar::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "name" => "required",
                "faculty_id" => "required|exists:faculties,faculty_id",
                "dept_id" => "required|exists:departments,dept_id",
                "course_id" => "required|exists:courses,course_id",
                "academic_year_id" => "required|exists:academic_years,academic_year_id",
                "semester_id" => "required|exists:academic_semesters,semester_id",
                "batch_id" => "required|exists:batches,batch_id",
            ], [], ["faculty_id" => "Faculty", "dept_id" => "Department", "course_id" => "Course",
                "academic_year_id" => "Academic Year", "semester_id" => "Semester", "batch_id" => "Batch"]);

            if ($this->repository->isValidData) {
                if (!$this->repository->isOtherCalendarExist($model)) {
                    $dateFields = $this->repository->getValidatedDateFields();

                    if (!$this->repository->isValidDates) {
                        $response = $dateFields;
                    } else {
                        $response = $this->repository->saveModel($model);

                        if ($response["notify"]["status"] == "success") {
                            $this->repository->updateCalendar($model, $dateFields);

                            $acedRepo = new AcademicCalendarExtraDateRepository();
                            $acedRepo->update($model);

                            if (request()->post("send_for_approval") == "1") {
                                DB::beginTransaction();
                                $model->{$this->repository->approvalField} = 0;
                                $model->save();

                                $update = $this->repository->triggerApprovalProcess($model);

                                if ($update["notify"]["status"] === "success") {

                                    DB::commit();

                                } else {
                                    DB::rollBack();

                                    if (is_array($update["notify"]) && count($update["notify"]) > 0) {

                                        foreach ($update["notify"] as $message) {

                                            $response["notify"]["notify"][] = $message;
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $response["status"] = "failed";
                    $response["notify"][] = $model->base_name . " academic calendar already exists.";
                }
            } else {
                $response = $model;
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
     * Move the record to trash
     * @param
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = AcademicCalendar::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = AcademicCalendar::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Search records
     * @param Request $request
     * @return JsonResponse
     */
    public function searchData(Request $request): JsonResponse
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

            $query = AcademicCalendar::query()
                ->where("ac_status", "=", "1")
                ->orderBy("name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($searchText !== null) {
                $query = $query->where("name", "LIKE", "%" . $searchText . "%");
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

            if ($idNot !== null) {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("id", $idNot);
            }

            $data = $query->get();

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }

    /**
     * Search records
     * @param Request $request
     * @return JsonResponse
     */
    public function getData(Request $request): JsonResponse
    {
        if ($request->expectsJson()) {
            $courseId = $request->post("course_id");
            $academicYearId = $request->post("academic_year_id");
            $semesterId = $request->post("semester_id");
            $batchId = $request->post("batch_id");

            $data = $this->repository->getAcademicCalendar($courseId, $academicYearId, $semesterId, $batchId);

            if ($data) {
                $response["status"] = "success";
                $response["data"] = $data;
            } else {
                $model = new AcademicCalendar();
                $model->course_id = $courseId;
                $model->academic_year_id = $academicYearId;
                $model->semester_id = $semesterId;
                $model->batch_id = $batchId;

                $response["status"] = "failed";
                $response["notify"][] = $model->base_name . " academic calendar does not exist.";
                $response["notify"][] = "Please add the academic calendar for the selected terms before adding the timetable";
            }

            return response()->json($response, 201);
        }

        abort("403", "You are not allowed to access this data");
    }

    /**
     * Update status of the specified resource in storage.
     * @param
     * @return JsonResponse|RedirectResponse|null
     */
    public function changeStatus($id)
    {
        $model = AcademicCalendar::query()->find($id);
        return $this->repository->updateStatus($model, $this->repository->statusField, "", "remarks");
    }

    /**
     * Update status of the specified resource in storage.
     * @param
     * @return JsonResponse|RedirectResponse
     */
    public function changeCompleteStatus($id)
    {
        $model = AcademicCalendar::query()->find($id);
        return $this->repository->updateStatus($model, $this->repository->completeStatusField);
    }

    public function verification($id)
    {
        $model = AcademicCalendar::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "verification");
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
    public function verificationSubmit($id)
    {
        $model = AcademicCalendar::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $id
     * @return Application|Factory|JsonResponse|RedirectResponse|View|null
     */
    public function approval($id)
    {
        $model = AcademicCalendar::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "approval");
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
    public function approvalSubmit($id)
    {
        $model = AcademicCalendar::query()->find($id);

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
        $model = new AcademicCalendar();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new AcademicCalendar();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
