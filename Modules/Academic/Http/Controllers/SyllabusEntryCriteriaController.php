<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\CourseSyllabus;
use Modules\Academic\Entities\SyllabusEntryCriteria;
use Modules\Academic\Repositories\SyllabusEntryCriteriaDocumentRepository;
use Modules\Academic\Repositories\SyllabusEntryCriteriaRepository;

class SyllabusEntryCriteriaController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new SyllabusEntryCriteriaRepository();
    }


    /**
     * Display a listing of the resource.
     * @param mixed $syllabusId
     * @return Factory|View
     */
    public function index($syllabusId)
    {
        $syllabusTitle = "";
        $cc = CourseSyllabus::query()->find($syllabusId);

        if ($cc) {
            $syllabusTitle = $cc["name"];
        } else {
            abort(404, "Syllabus not available");
        }

        $pageTitle = "Entry Criteria";
        if ($syllabusTitle != "") {
            $pageTitle = $syllabusTitle . " | " . $pageTitle;
        }

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new SyllabusEntryCriteria());

        $this->repository->setColumns("id", "criteria_name", "criteria_status", "created_at")
            ->setColumnLabel("criteria_name", "Entry Criteria")
            ->setColumnLabel("criteria_status", "Status")
            ->setColumnDisplay("criteria_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnFilterMethod("criteria_name")
            ->setColumnFilterMethod("criteria_status", "select", $this->repository->statuses)
            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = "Entry Criteria | Trashed";
            if ($syllabusId) {
                if ($syllabusTitle != "") {
                    $tableTitle = $syllabusTitle . " | " . $tableTitle;

                    $this->repository->setUrl("list", "/academic/syllabus_entry_criteria/" . $syllabusId);
                }
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "view", "restore", "export")
                ->disableViewData("edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = "Entry Criteria";
            if ($syllabusId) {
                if ($syllabusTitle != "") {
                    $tableTitle = $syllabusTitle . " | " . $tableTitle;

                    $this->repository->setUrl("trashList", "/academic/syllabus_entry_criteria/trash/" . $syllabusId);
                }
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("view", "trashList", "trash", "export");
        }

        $this->repository->setUrl("add", "/academic/syllabus_entry_criteria/create/" . $syllabusId);

        $query->where("syllabus_id", $syllabusId);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param $syllabusId
     * @return Factory|View
     */
    public function trash($syllabusId)
    {
        $this->trash = true;
        return $this->index($syllabusId);
    }

    /**
     * Show the form for creating a new resource.
     * @param $syllabusId
     * @return Factory|View
     */
    public function create($syllabusId)
    {
        $syllabus = CourseSyllabus::query()->find($syllabusId);

        if (!$syllabus) {

            abort(404, "Syllabus not available");
        }

        $model = new SyllabusEntryCriteria();
        $model->syllabus = $syllabus;

        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/syllabus_entry_criteria/" . $syllabusId);

        $this->repository->setPageUrls($urls);

        return view('academic::syllabus_entry_criteria.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Show the form for creating a new resource.
     * @param $syllabusId
     * @return Factory|View
     */
    public function store($syllabusId)
    {
        $syllabus = CourseSyllabus::query()->find($syllabusId);

        if (!$syllabus) {

            abort(404, "Syllabus not available");
        }

        $model = new SyllabusEntryCriteria();

        $model = $this->repository->getValidatedData($model, [
            "criteria_name" => "required",
            "description" => "required",
            "remarks" => "",
            "criteria_status" => "required",
        ]);

        if ($this->repository->isValidData) {
            $model->syllabus_id = $syllabusId;

            $response = $this->repository->saveModel($model);

            if ($response["notify"]["status"] == "success") {

                $docRepo = new SyllabusEntryCriteriaDocumentRepository();
                $docRepo->update($model);
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
        $model = SyllabusEntryCriteria::withTrashed()->with([
            "createdUser",
            "updatedUser",
            "deletedUser"])->find($id);

        if($model)
        {
            $record = $model->toArray();

            $controllerUrl = URL::to("/academic/syllabus_entry_criteria/");

            $urls = [];
            $urls["addUrl"]=URL::to($controllerUrl . "/create");
            $urls["editUrl"]=URL::to($controllerUrl . "/edit/" .$model->id);
            $urls["listUrl"]=URL::to($controllerUrl);
            $urls["adminUrl"]=URL::to("/admin/admin/view/");
            $urls["recordHistoryUrl"]=$this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);
            $urls["approvalHistoryUrl"]=$this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);

            $this->repository->setPageUrls($urls);

            $statusInfo = [];
            $statusInfo["status"] = $this->repository->getStatusInfo($model);

            return view('academic::syllabus_entry_criteria.view', compact('record', 'statusInfo'));
        }
        else
        {
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
        $model = SyllabusEntryCriteria::with(["syllabus", "ecDocuments"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $syllabusId = $record["syllabus_id"];

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/syllabus_entry_criteria/create/" . $syllabusId);
            $urls["listUrl"] = URL::to("/academic/syllabus_entry_criteria/" . $syllabusId);
            $urls["downloadUrl"] = URL::to("/academic/syllabus_entry_criteria/download_document/" . $syllabusId . "/");

            $this->repository->setPageUrls($urls);

            return view('academic::syllabus_entry_criteria.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = SyllabusEntryCriteria::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "criteria_name" => "required",
                "description" => "required",
                "remarks" => "",
                "criteria_status" => "required",
            ]);

            if ($this->repository->isValidData) {
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] == "success") {

                    $docRepo = new SyllabusEntryCriteriaDocumentRepository();
                    $docRepo->update($model);
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
        $model = SyllabusEntryCriteria::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = SyllabusEntryCriteria::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Search records
     * @param Request $request
     * @return JsonResponse
     */
    public function searchData(Request $request)
    {
        if ($request->expectsJson()) {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $limit = $request->post("limit");

            $query = SyllabusEntryCriteria::query()
                ->select("id", "criteria_name")
                ->where("criteria_status", "=", "1")
                ->orderBy("criteria_name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($searchText != "") {
                $query = $query->where("criteria_name", "LIKE", "%" . $searchText . "%");
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("id", $idNot);
            }

            $data = $query->get();

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return mixed
     */
    public function changeStatus($id)
    {
        $model = SyllabusEntryCriteria::query()->find($id);
        return $this->repository->updateStatus($model, "criteria_status");
    }

    /**
     * Download lecturer's document
     * @param $id
     * @param $documentId
     * @return mixed
     */
    public function downloadDocument($id, $documentId)
    {
        $model = SyllabusEntryCriteria::query()->find($id);

        if($model)
        {
            $docRepo = new SyllabusEntryCriteriaDocumentRepository();
            return $docRepo->triggerDownloadDocument($model, $documentId);
        }
        else
        {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new SyllabusEntryCriteria();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
