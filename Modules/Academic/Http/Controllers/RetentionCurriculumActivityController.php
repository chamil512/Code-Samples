<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\RetentionCurriculum;
use Modules\Academic\Entities\RetentionCurriculumActivity;
use Modules\Academic\Repositories\RetentionCurriculumActivityDocumentRepository;
use Modules\Academic\Repositories\RetentionCurriculumActivityLecturerRepository;
use Modules\Academic\Repositories\RetentionCurriculumActivityMemberRepository;
use Modules\Academic\Repositories\RetentionCurriculumActivityRepository;

class RetentionCurriculumActivityController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new RetentionCurriculumActivityRepository();
    }

    /**
     * Display a listing of the resource.
     * @param $curriculumId
     * @return Response
     */
    public function index($curriculumId)
    {
        $curriculum = RetentionCurriculum::query()->find($curriculumId);

        if ($curriculum) {
            $pageTitle = $curriculum["name"] . " | Retention Curriculum Activities";
            $tableTitle = $curriculum["name"] . " | Retention Curriculum Activities";

            $this->repository->setPageTitle($pageTitle);

            $this->repository->initDatatable(new RetentionCurriculumActivity());

            $this->repository->setColumns("id", "title", "type", "activity_date", "lecturers", "members", "documents", "created_at")
                ->setColumnLabel("version_status", "Status")

                ->setColumnDBField("type", "id")
                ->setColumnFKeyField("type", "rc_activity_type_id")
                ->setColumnRelation("type", "rcActivityType", "activity_type")

                ->setColumnDBField("lecturers", "id")
                ->setColumnFKeyField("lecturers", "rc_activity_id")
                ->setColumnRelation("lecturers", "rcActivityLecturers", "name")

                ->setColumnDBField("members", "id")
                ->setColumnFKeyField("members", "rc_activity_id")
                ->setColumnRelation("members", "rcActivityMembers", "name")

                ->setColumnDisplay("type", array($this->repository, 'displayRelationAs'), ["type", "id", "name"])
                ->setColumnDisplay("lecturers", array($this->repository, 'displayRelationManyAs'), ["lecturers", "lecturer", "lecturer_id", "name"])
                ->setColumnDisplay("members", array($this->repository, 'displayRelationManyAs'), ["members", "externalIndividual", "id", "name"])
                ->setColumnDisplay("documents", array($this->repository, 'displayListButtonAs'), ["Documents", URL::to("/academic/retention_curriculum_activity_document/")])
                ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

                ->setColumnFilterMethod("title")
                ->setColumnFilterMethod("activity_date", "date")

                ->setColumnSearchability("lecturers", false)
                ->setColumnSearchability("members", false)
                ->setColumnSearchability("documents", false)
                ->setColumnSearchability("created_at", false)

                ->setColumnDBField("documents", "id");

            if ($this->trash) {
                $query = $this->repository->model::onlyTrashed();

                $this->repository->setTableTitle($tableTitle . " | Trashed")
                    ->enableViewData("list", "restore", "export")
                    ->disableViewData("view", "edit", "delete")
                    ->setUrl("list", $this->repository->getUrl("list") . "/" . $curriculumId)
                    ->setUrl("add", $this->repository->getUrl("add") . "/" . $curriculumId);
            } else {
                $query = $this->repository->model::query();

                $this->repository->setTableTitle($tableTitle)
                    ->enableViewData("trashList", "trash", "export")
                    ->setUrl("trashList", $this->repository->getUrl("trashList") . "/" . $curriculumId)
                    ->setUrl("add", $this->repository->getUrl("add") . "/" . $curriculumId);
            }

            $query->where("retention_curriculum_id", "=", $curriculumId);

            $query = $query->with(["rcActivityType", "rcActivityDocuments", "rcActivityLecturers", "rcActivityMembers"]);

            return $this->repository->render("academic::layouts.master")->index($query);
        } else {
            abort(404);
        }
    }

    /**
     * Display a listing of the resource.
     * @param $curriculumId
     * @return Response
     */
    public function trash($curriculumId)
    {
        $this->trash = true;
        return $this->index($curriculumId);
    }

    /**
     * Show the form for creating a new resource.
     * @param int $curriculumId
     * @return Factory|View
     */
    public function create($curriculumId)
    {
        $curriculum = RetentionCurriculum::query()->find($curriculumId);

        if ($curriculum) {
            $this->repository->setPageTitle("Retention Curriculum Activities | Add New");

            $model = new RetentionCurriculumActivity();
            $model->rc_curriculum = $curriculum;

            $record = $model;

            $formMode = "add";
            $formSubmitUrl = request()->getPathInfo();

            $urls = [];
            $urls["listUrl"] = URL::to("/academic/retention_curriculum_activity/" . $curriculumId);

            $this->repository->setPageUrls($urls);

            return view('academic::retention_curriculum_activity.create', compact('formMode', 'formSubmitUrl', 'record'));
        } else {
            abort(404);
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param RetentionCurriculum $curriculumId
     * @return JsonResponse
     */
    public function store($curriculumId)
    {
        $curriculum = RetentionCurriculum::query()->find($curriculumId);

        if ($curriculum) {
            $model = new RetentionCurriculumActivity();

            $model = $this->repository->getValidatedData($model, [
                "title" => "required",
                "activity_date" => "required|date",
                "rc_activity_type_id" => "required",
                "remarks" => "",
            ], [], ["title" => "Activity Title", "activity_date" => "Date of Amendment", "rc_activity_type_id" => "Activity Type"]);

            if ($this->repository->isValidData) {
                $model->retention_curriculum_id = $curriculumId;
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] == "success") {

                    $docRepo = new RetentionCurriculumActivityDocumentRepository();
                    $docRepo->update($model);

                    $lecRepo = new RetentionCurriculumActivityLecturerRepository();
                    $lecRepo->update($model);

                    $memRepo = new RetentionCurriculumActivityMemberRepository();
                    $memRepo->update($model);
                }
            } else {
                $response = $model;
            }

            return $this->repository->handleResponse($response);
        } else {
            $response["notify"]["status"] = "failed";
            $response["notify"]["notify"][] = "Selected permission system does not exist.";
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Factory|View
     */
    public function edit($id)
    {
        $this->repository->setPageTitle("Retention Curriculum Activities | Edit");

        $model = RetentionCurriculumActivity::with(["rcCurriculum", "rcActivityDocuments"])->find($id);

        if ($model) {
            $lecRepo = new RetentionCurriculumActivityLecturerRepository();
            $memRepo = new RetentionCurriculumActivityMemberRepository();

            $model->lecturers = $lecRepo->getCurrRecords($model);
            $model->members = $memRepo->getCurrRecords($model);

            $record = $model->toArray();

            $formMode = "edit";
            $formSubmitUrl = request()->getPathInfo();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/retention_curriculum_activity/create/" . $record["retention_curriculum_id"]);
            $urls["listUrl"] = URL::to("/academic/retention_curriculum_activity/" . $record["retention_curriculum_id"]);
            $urls["downloadUrl"]=URL::to("/academic/retention_curriculum_activity_document/download/");

            $this->repository->setPageUrls($urls);

            return view('academic::retention_curriculum_activity.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = RetentionCurriculumActivity::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "title" => "required",
                "activity_date" => "required|date",
                "rc_activity_type_id" => "required",
                "remarks" => "",
            ], [], ["title" => "Activity Title", "activity_date" => "Date of Amendment", "rc_activity_type_id" => "Activity Type"]);

            if ($this->repository->isValidData) {
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] == "success") {

                    $docRepo = new RetentionCurriculumActivityDocumentRepository();
                    $docRepo->update($model);

                    $lecRepo = new RetentionCurriculumActivityLecturerRepository();
                    $lecRepo->update($model);

                    $memRepo = new RetentionCurriculumActivityMemberRepository();
                    $memRepo->update($model);

                    $response["data"]["documents"] = $model->rcActivityDocuments()->get()->toArray();
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
        $model = RetentionCurriculumActivity::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = RetentionCurriculumActivity::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new RetentionCurriculumActivity();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
