<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\LecturerWorkCategory;
use Modules\Academic\Entities\LecturerWorkType;
use Modules\Academic\Repositories\LecturerWorkTypeRepository;
use Modules\Admin\Repositories\AdminActivityRepository;

class LecturerWorkTypeController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new LecturerWorkTypeRepository();
    }


    /**
     * Display a listing of the resource.
     * @param mixed $categoryId
     * @return Factory|View
     */
    public function index($categoryId)
    {
        $categoryTitle = "";
        $cc = LecturerWorkCategory::query()->find($categoryId);

        if ($cc) {
            $categoryTitle = $cc["name"];
        } else {
            abort(404, "Lecturer Work Category not available");
        }

        $pageTitle = "Lecturer Work Types";
        if ($categoryTitle != "") {
            $pageTitle = $categoryTitle . " | " . $pageTitle;
        }

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new LecturerWorkType());

        $this->repository->setColumns("id", "type_name", "type_status", "created_at")
            ->setColumnLabel("type_name", "Lecturer Work Type")
            ->setColumnLabel("type_status", "Status")
            ->setColumnDisplay("type_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnFilterMethod("type_name")
            ->setColumnFilterMethod("type_status", "select", $this->repository->statuses)
            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = "Lecturer Work Types | Trashed";
            if ($categoryId) {
                if ($categoryTitle != "") {
                    $tableTitle = $categoryTitle . " | " . $tableTitle;

                    $this->repository->setUrl("list", "/academic/lecturer_work_type/" . $categoryId);
                }
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = "Lecturer Work Types";
            if ($categoryId) {
                if ($categoryTitle != "") {
                    $tableTitle = $categoryTitle . " | " . $tableTitle;

                    $this->repository->setUrl("trashList", "/academic/lecturer_work_type/trash/" . $categoryId);
                }
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export");
        }

        $this->repository->setUrl("add", "/academic/lecturer_work_type/create/" . $categoryId);

        $query->where("lecturer_work_category_id", $categoryId);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param $categoryId
     * @return Factory|View
     */
    public function trash($categoryId)
    {
        $this->trash = true;
        return $this->index($categoryId);
    }

    /**
     * Show the form for creating a new resource.
     * @param $categoryId
     * @return Factory|View
     */
    public function create($categoryId)
    {
        $category = LecturerWorkCategory::query()->find($categoryId);

        if (!$category) {

            abort(404, "Lecturer Work Category not available");
        }

        $model = new LecturerWorkType();
        $model->work_category = $category;

        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/lecturer_work_type/" . $categoryId);

        $this->repository->setPageUrls($urls);

        return view('academic::lecturer_work_type.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Show the form for creating a new resource.
     * @param $categoryId
     * @return Factory|View
     */
    public function store($categoryId)
    {
        $category = LecturerWorkCategory::query()->find($categoryId);

        if (!$category) {

            abort(404, "Lecturer Work Category not available");
        }

        $model = new LecturerWorkType();

        $model = $this->repository->getValidatedData($model, [
            "type_name" => "required",
            "type_status" => "required",
        ]);

        if ($this->repository->isValidData) {
            $model->lecturer_work_category_id = $categoryId;

            $response = $this->repository->saveModel($model);
        } else {
            $response = $model;
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
        $model = LecturerWorkType::with(["workCategory"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $categoryId = $record["lecturer_work_category_id"];

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/lecturer_work_type/create/" . $categoryId);
            $urls["listUrl"] = URL::to("/academic/lecturer_work_type/" . $categoryId);
            $urls["downloadUrl"] = URL::to("/academic/lecturer_work_type/download_document/" . $categoryId . "/");

            $this->repository->setPageUrls($urls);

            return view('academic::lecturer_work_type.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = LecturerWorkType::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "type_name" => "required",
                "type_status" => "required",
            ]);

            if ($this->repository->isValidData) {
                $response = $this->repository->saveModel($model);
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
        $model = LecturerWorkType::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = LecturerWorkType::withTrashed()->find($id);

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
            $categoryId = $request->post("category_id");
            $limit = $request->post("limit");

            $query = LecturerWorkType::query()
                ->select("id", "type_name")
                ->where("type_status", "=", "1")
                ->orderBy("type_name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if (is_array($categoryId) && count($categoryId) > 0) {

                $query = $query->whereIn("lecturer_work_category_id", $categoryId);
            } elseif($categoryId != "") {

                $query = $query->where("lecturer_work_category_id", $categoryId);
            }

            if ($searchText != "") {
                $query = $query->where("type_name", "LIKE", "%" . $searchText . "%");
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
        $model = LecturerWorkType::query()->find($id);
        return $this->repository->updateStatus($model, "type_status");
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new LecturerWorkType();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
