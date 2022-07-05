<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\RetentionCurriculum;
use Modules\Academic\Repositories\RetentionCurriculumDocumentRepository;
use Modules\Academic\Repositories\RetentionCurriculumRepository;

class RetentionCurriculumController extends Controller
{
    private RetentionCurriculumRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new RetentionCurriculumRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $pageTitle = "Retention Curricula";

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new RetentionCurriculum());

        $this->repository->setColumns("id", "title", "category", "faculty", "department", "course", "activities", "documents", "created_at")
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnDBField("category", "id")
            ->setColumnFKeyField("category", "rc_category_id")
            ->setColumnRelation("category", "rc_category", "category_name")
            ->setColumnDBField("faculty", "faculty_id")
            ->setColumnFKeyField("faculty", "faculty_id")
            ->setColumnRelation("faculty", "faculty", "faculty_name")
            ->setColumnDBField("department", "dept_id")
            ->setColumnFKeyField("department", "dept_id")
            ->setColumnRelation("department", "department", "dept_name")
            ->setColumnDBField("course", "course_id")
            ->setColumnFKeyField("course", "course_id")
            ->setColumnRelation("course", "course", "course_name")
            ->setColumnDisplay("category", array($this->repository, 'displayRelationAs'), ["category", "id", "name"])
            ->setColumnDisplay("faculty", array($this->repository, 'displayRelationAs'), ["faculty", "id", "name"])
            ->setColumnDisplay("department", array($this->repository, 'displayRelationAs'), ["department", "id", "name"])
            ->setColumnDisplay("course", array($this->repository, 'displayRelationAs'), ["course", "id", "name"])
            ->setColumnDisplay("activities", array($this->repository, 'displayListButtonAs'), ["Activities", URL::to("/academic/retention_curriculum_activity/")])
            ->setColumnDisplay("documents", array($this->repository, 'displayListButtonAs'), ["Documents", URL::to("/academic/retention_curriculum_document/")])
            ->setColumnFilterMethod("category", "select", URL::to("/academic/retention_curriculum_category/search_data"))
            ->setColumnFilterMethod("faculty", "select", URL::to("/academic/faculty/search_data"))
            ->setColumnFilterMethod("department", "select", [
                "options" => URL::to("/academic/department/search_data"),
                "basedColumns" => [
                    [
                        "column" => "faculty",
                        "param" => "faculty_id",
                    ]
                ],
            ])
            ->setColumnFilterMethod("course", "select", [
                "options" => URL::to("/academic/course/search_data"),
                "basedColumns" => [
                    [
                        "column" => "department",
                        "param" => "dept_id",
                    ]
                ],
            ])
            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false)
            ->setColumnDBField("documents", "id")
            ->setColumnDBField("activities", "id");

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = "Retention Curricula | Trashed";
            $this->repository->setUrl("list", "/academic/retention_curriculum");

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = "Retention Curricula";
            $this->repository->setCustomControllerUrl("/academic/retention_curriculum", ["list"], false)
                ->setUrl("trashList", "/academic/retention_curriculum/trash");

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export");
        }

        $this->repository->setUrl("add", "/academic/retention_curriculum/create");

        $query = $query->with(["rcCategory", "faculty", "department", "course"]);

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
        $model = new RetentionCurriculum();

        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/retention_curriculum");

        $this->repository->setPageUrls($urls);

        return view('academic::retention_curriculum.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     */
    public function store()
    {
        $model = new RetentionCurriculum();

        $model = $this->repository->getValidatedData($model, [
            "title" => "required",
            "rc_category_id" => "required|exists:retention_curriculum_categories,id",
            "faculty_id" => "",
            "dept_id" => "",
            "course_id" => "",
            "remarks" => "",
        ], [], ["rc_category_id" => "Category", "faculty_id" => "Faculty", "dept_id" => "Department", "course_id" => "Course"]);

        if ($this->repository->isValidData) {
            $response = $this->repository->saveModel($model);

            if ($response["notify"]["status"] == "success") {
                $lCMRepo = new RetentionCurriculumDocumentRepository();
                $lCMRepo->update($model);
            }
        } else {
            $response = $model;
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Factory|View
     */
    public function show($id)
    {
        $model = RetentionCurriculum::query()->find($id);

        if ($model) {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/retention_curriculum/create");
            $urls["listUrl"] = URL::to("/academic/retention_curriculum");

            $this->repository->setPageUrls($urls);

            return view('academic::retention_curriculum.view', compact('record'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Factory|View
     */
    public function edit($id)
    {
        $model = RetentionCurriculum::with(["rcCategory", "faculty", "department", "course", "rcDocuments"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/retention_curriculum/create");
            $urls["listUrl"] = URL::to("/academic/retention_curriculum");
            $urls["downloadUrl"] = URL::to("/academic/retention_curriculum_document/download") . "/";

            $this->repository->setPageUrls($urls);

            return view('academic::retention_curriculum.create', compact('formMode', 'formSubmitUrl', 'record'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Update the specified resource in storage.
     * @param int $id
     * @return JsonResponse
     */
    public function update($id)
    {
        $model = RetentionCurriculum::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "title" => "required",
                "rc_category_id" => "required|exists:retention_curriculum_categories,id",
                "faculty_id" => "",
                "dept_id" => "",
                "course_id" => "",
                "remarks" => "",
            ], [], ["rc_category_id" => "Category", "faculty_id" => "Faculty", "dept_id" => "Department", "course_id" => "Course"]);

            if ($this->repository->isValidData) {
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] === "success") {
                    $lCMRepo = new RetentionCurriculumDocumentRepository();
                    $lCMRepo->update($model);

                    $response["data"]["documents"] = $model->rcDocuments()->get()->toArray();
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
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = RetentionCurriculum::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = RetentionCurriculum::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new RetentionCurriculum();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
