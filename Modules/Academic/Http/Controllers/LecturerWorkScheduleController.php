<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\LecturerWorkSchedule;
use Modules\Academic\Repositories\LecturerRepository;
use Modules\Academic\Repositories\LecturerWorkScheduleDocumentRepository;
use Modules\Academic\Repositories\LecturerWorkScheduleRepository;

class LecturerWorkScheduleController extends Controller
{
    private LecturerWorkScheduleRepository $repository;
    private bool $trash = false;
    private bool $own = false;

    public function __construct()
    {
        $this->repository = new LecturerWorkScheduleRepository();
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $admin = auth("admin")->user();

        $pageTitle = "Lecturer Work Schedules";
        $tableTitle = "Lecturer Work Schedules";
        if ($this->own) {
            $pageTitle = "My Work Schedules";
            $tableTitle = "My Work Schedules";
        }

        $lecRepo = new LecturerRepository();

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new LecturerWorkSchedule());

        $this->repository->setColumns("id", "lecturer", "faculty", "department", "staff_type", "title", "work_category", "work_type", "delivery_mode", "work_date", "start_time", "end_time", "created_at")

            ->setColumnDBField("lecturer", "lecturer_id")
            ->setColumnFKeyField("lecturer", "id")
            ->setColumnRelation("lecturer", "lecturer", "name_with_init")

            ->setColumnDBField("faculty", "lecturer_id")
            ->setColumnFKeyField("faculty", "id")
            ->setColumnRelation("faculty", "lecturer", "name_with_init")
            ->setColumnCoRelation("faculty", "faculty", "faculty_name",
                "faculty_id", "faculty_id")

            ->setColumnDBField("department", "lecturer_id")
            ->setColumnFKeyField("department", "id")
            ->setColumnRelation("department", "lecturer", "name_with_init")
            ->setColumnCoRelation("department", "department", "dept_name",
                "dept_id", "dept_id")

            ->setColumnDBField("work_category", "lecturer_work_category_id")
            ->setColumnFKeyField("work_category", "lecturer_work_category_id")
            ->setColumnRelation("work_category", "workCategory", "category_name")

            ->setColumnDBField("work_type", "lecturer_work_type_id")
            ->setColumnFKeyField("work_type", "lecturer_work_type_id")
            ->setColumnRelation("work_type", "workType", "type_name")

            ->setColumnDBField("delivery_mode", "delivery_mode_id")
            ->setColumnFKeyField("delivery_mode", "delivery_mode_id")
            ->setColumnRelation("delivery_mode", "deliveryMode", "mode_name")

            ->setColumnDBField("staff_type", "lecturer_id")
            ->setColumnFKeyField("staff_type", "id")
            ->setColumnRelation("staff_type", "lecturer", "staff_type")
            ->setColumnRelationOtherField("staff_type", "staff_type")

            ->setColumnDisplay("staff_type", array($this->repository, 'displayRelationStatusAs'),
                ["staff_type", "staff_type", $lecRepo->staffTypes, "lecturer"])

            ->setColumnDisplay("lecturer", array($this->repository, 'displayRelationAs'), ["lecturer", "lecturer_id", "name"])
            ->setColumnDisplay("faculty", array($this->repository, 'displayCoRelationAs'), ["faculty", "faculty_id", "name"])
            ->setColumnDisplay("department", array($this->repository, 'displayCoRelationAs'), ["department", "dept_id", "name"])
            ->setColumnDisplay("work_category", array($this->repository, 'displayRelationAs'), ["work_category", "lecturer_work_category_id", "name"])
            ->setColumnDisplay("work_type", array($this->repository, 'displayRelationAs'), ["work_type", "lecturer_work_type_id", "name"])
            ->setColumnDisplay("delivery_mode", array($this->repository, 'displayRelationAs'), ["delivery_mode", "delivery_mode_id", "name"])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("faculty", "select", URL::to("/academic/faculty/search_data"))
            ->setColumnFilterMethod("department", "select", [
                "options" => URL::to("/academic/department/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "faculty",
                        "param" => "faculty_id",
                    ]
                ],
            ])

            ->setColumnFilterMethod("lecturer", "select", [
                "options" => URL::to("/academic/lecturer/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "faculty",
                        "param" => "faculty_id",
                    ],
                    [
                        "column" => "department",
                        "param" => "dept_id",
                    ]
                ],
            ])

            ->setColumnFilterMethod("work_category", "select", URL::to("/academic/lecturer_work_category/search_data/"))

            ->setColumnFilterMethod("work_type", "select", [
                "options" => URL::to("/academic/lecturer_work_type/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "work_category",
                        "param" => "category_id",
                    ]
                ],
            ])
            ->setColumnFilterMethod("staff_type", "select", $lecRepo->staffTypes)
            ->setColumnFilterMethod("delivery_mode", "select", URL::to("/academic/module_delivery_mode/search_data/"))
            ->setColumnFilterMethod("work_date", "date_between")

            ->setColumnSearchability("documents", false)
            ->setColumnSearchability("created_at", false);

        if ($this->trash) {

            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle($tableTitle . " | Trashed")
                ->enableViewData("list", "restore", "view", "export")
                ->disableViewData("edit", "delete");

        } else {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("view", "trashList", "trash", "export");
        }

        $query = $query->with(["lecturer", "lecturer.faculty", "lecturer.department", "workCategory", "workType", "deliveryMode"]);

        if ($this->own) {

            $lecturerId = false;
            if (isset($admin->lecturer_id)) {

                $lecturerId = $admin->lecturer_id;
            }

            $query->where("lecturer_id", $lecturerId);

            $this->repository->setCustomControllerUrl(URL::to("/academic/lecturer_work_schedule/"));
            $this->repository->setUrl("list", URL::to("/academic/lecturer_work_schedule/own"));
            $this->repository->setUrl("trash", URL::to("/academic/lecturer_work_schedule/trash/own"));
        }
        $this->repository->setUrl("view", URL::to("/academic/lecturer_work_schedule_document/"));
        $this->repository->setUrlLabel("view", "Documents");

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     */
    public function trash()
    {
        $this->trash = true;
        return $this->index();
    }

    /**
     * Display a listing of the resource.
     */
    public function indexOwn()
    {
        $this->own = true;
        return $this->index();
    }

    /**
     * Display a listing of the resource.
     */
    public function trashOwn()
    {
        $this->own = true;
        $this->trash = true;
        return $this->index();
    }

    /**
     * @return Application|Factory|View|void
     *
     */
    public function create()
    {
        $admin = auth("admin")->user();

        $lecturerId = false;
        if (isset($admin->lecturer_id)) {

            $lecturerId = $admin->lecturer_id;
        }

        if ($lecturerId) {

            $this->repository->setPageTitle("Lecturer Work Schedules | Add New");

            $model = new LecturerWorkSchedule();

            $record = $model;

            $formMode = "add";
            $formSubmitUrl = request()->getPathInfo();

            $urls = [];
            $urls["listUrl"] = URL::to("/academic/lecturer_work_schedule/");

            $this->repository->setPageUrls($urls);

            return view('academic::lecturer_work_schedule.create', compact('formMode', 'formSubmitUrl', 'record'));
        } else {

            abort(403, "You need to be a lecturer to add work schedules.");
        }
    }

    /**
     * @return JsonResponse | void
     * @throws ValidationException
     */
    public function store()
    {
        $admin = auth("admin")->user();

        $lecturerId = false;
        if (isset($admin->lecturer_id)) {

            $lecturerId = $admin->lecturer_id;
        }

        if ($lecturerId) {

            $model = new LecturerWorkSchedule();
            $model = $this->repository->getValidatedData($model, [
                "title" => "required",
                "work_date" => "required|date",
                "start_time" => "required",
                "end_time" => "required",
                "work_count" => "required",
                "lecturer_work_category_id" => "required",
                "lecturer_work_type_id" => "required",
                "note" => "",
            ], [], [
                    "title" => "Activity Title",
                    "work_date" => "Work date",
                    "start_time" => "Start Time",
                    "end_time" => "End Time",
                    "work_count" => "Work Count",
                    "lecturer_work_category_id" => "Work category",
                    "lecturer_work_type_id" => "Work Type"
                ]
            );

            if ($this->repository->isValidData) {

                $model->lecturer_id = $lecturerId;

                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] === "success") {

                    $docRepo = new LecturerWorkScheduleDocumentRepository();
                    $docRepo->update($model);
                }
            } else {
                $response = $model;
            }

            return $this->repository->handleResponse($response);
        } else {

            abort(403, "You need to be a lecturer to add work schedules.");
        }
    }

    /**
     * @param $id
     * @return Application|Factory|View|void
     */
    public function edit($id)
    {
        $admin = auth("admin")->user();

        $lecturerId = false;
        if (isset($admin->lecturer_id)) {

            $lecturerId = $admin->lecturer_id;
        }

        if ($lecturerId) {

            $this->repository->setPageTitle("Lecturer Work Schedules | Edit");

            $model = LecturerWorkSchedule::with(["workCategory", "workType", "wsDocuments"])->find($id);

            if ($model) {

                if ($model->lecturer_id === $lecturerId) {

                    $record = $model->toArray();

                    $formMode = "edit";
                    $formSubmitUrl = request()->getPathInfo();

                    $urls = [];
                    $urls["addUrl"] = URL::to("/academic/lecturer_work_schedule/create");
                    $urls["listUrl"] = URL::to("/academic/lecturer_work_schedule/");
                    $urls["downloadUrl"]=URL::to("/academic/lecturer_work_schedule_document/download/");

                    $this->repository->setPageUrls($urls);

                    return view('academic::lecturer_work_schedule.create', compact('formMode', 'formSubmitUrl', 'record'));
                } else {

                    abort(403, "You can not edit other lecturers' work schedules.");
                }
            } else {

                abort(404, "Requested record does not exist.");
            }
        } else {

            abort(403, "You need to be a lecturer to add work schedules.");
        }
    }

    /**
     * Update the specified resource in storage.
     * @param $id
     * @return JsonResponse
     * @throws ValidationException
     */
    public function update($id): JsonResponse
    {
        $admin = auth("admin")->user();

        $lecturerId = false;
        if (isset($admin->lecturer_id)) {

            $lecturerId = $admin->lecturer_id;
        }

        if ($lecturerId) {

            $model = LecturerWorkSchedule::query()->find($id);

            if ($model) {

                if ($model->lecturer_id === $lecturerId) {

                    $model = $this->repository->getValidatedData($model, [
                        "title" => "required",
                        "work_date" => "required|date",
                        "start_time" => "required",
                        "end_time" => "required",
                        "work_count" => "required",
                        "lecturer_work_category_id" => "required",
                        "lecturer_work_type_id" => "required",
                        "note" => "",
                    ], [], [
                            "title" => "Activity Title",
                            "work_date" => "Work date",
                            "start_time" => "Start Time",
                            "end_time" => "End Time",
                            "work_count" => "Work Count",
                            "lecturer_work_category_id" => "Work category",
                            "lecturer_work_type_id" => "Work Type"
                        ]
                    );

                    if ($this->repository->isValidData) {
                        $response = $this->repository->saveModel($model);

                        if ($response["notify"]["status"] === "success") {

                            $docRepo = new LecturerWorkScheduleDocumentRepository();
                            $docRepo->update($model);

                            $response["data"]["documents"] = $model->wsDocuments()->get()->toArray();
                        }
                    } else {
                        $response = $model;
                    }
                } else {

                    $notify = array();
                    $notify["status"] = "failed";
                    $notify["notify"][] = "You can not edit other lecturers' work schedules.";

                    $response["notify"] = $notify;
                }
            } else {

                $notify = array();
                $notify["status"] = "failed";
                $notify["notify"][] = "Details saving was failed. Requested record does not exist.";

                $response["notify"] = $notify;
            }
        } else {

            $notify = array();
            $notify["status"] = "failed";
            $notify["notify"][] = "You need to be a lecturer to add work schedules.";

            $response["notify"] = $notify;
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Move the record to trash
     * @param $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = LecturerWorkSchedule::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = LecturerWorkSchedule::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new LecturerWorkSchedule();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
