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
use Modules\Academic\Repositories\LecturerWorkCategoryRepository;
use Modules\Admin\Repositories\AdminActivityRepository;

class LecturerWorkCategoryController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new LecturerWorkCategoryRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Lecturer Work Categories");

        $this->repository->initDatatable(new LecturerWorkCategory());

        $this->repository->setColumns("id", "category_name", "category_type", "types", "category_status", "created_at")
            ->setColumnLabel("category_status", "Status")
            ->setColumnLabel("category_type", "Category Type")
            ->setColumnLabel("types", "Work Types")
            ->setColumnDisplay("category_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses])
            ->setColumnDisplay("category_type", array($this->repository, 'displayStatusAs'), [$this->repository->categoryTypes])
            ->setColumnDisplay("types", array($this->repository, 'displayListButtonAs'), ["Work Types", URL::to("/academic/lecturer_work_type/")])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("category_name")
            ->setColumnFilterMethod("category_status", "select", $this->repository->statuses)
            ->setColumnFilterMethod("category_type", "select", $this->repository->categoryTypes)

            ->setColumnDBField("types", $this->repository->primaryKey)
            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Lecturer Work Categories | Trashed")
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Lecturer Work Categories")
                ->enableViewData("trashList", "trash", "export");
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

    /**
     * Show the form for creating a new resource.
     * @return Factory|View
     */
    public function create()
    {
        $model = new LecturerWorkCategory();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/".request()->path();

        $urls = [];
        $urls["listUrl"]=URL::to("/academic/lecturer_work_category");

        $this->repository->setPageUrls($urls);

        return view('academic::lecturer_work_category.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     */
    public function store()
    {
        $model = new LecturerWorkCategory();

        $model = $this->repository->getValidatedData($model, [
            "category_name" => "required",
            "category_type" => "required",
            "category_status" => "required",
        ]);

        if($this->repository->isValidData)
        {
            $response = $this->repository->saveModel($model);
        }
        else
        {
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
        $model = LecturerWorkCategory::query()->find($id);

        //dd(get_class($model));

        if($model)
        {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/lecturer_work_category/create");
            $urls["listUrl"]=URL::to("/academic/lecturer_work_category");

            $this->repository->setPageUrls($urls);

            return view('academic::lecturer_work_category.create', compact('formMode', 'formSubmitUrl', 'record'));
        }
        else
        {
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
        $model = LecturerWorkCategory::query()->find($id);

        if($model)
        {
            $model = $this->repository->getValidatedData($model, [
                "category_name" => "required",
                "category_type" => "required",
                "category_status" => "required",
            ]);

            if($this->repository->isValidData)
            {
                $response = $this->repository->saveModel($model);
            }
            else
            {
                $response = $model;
            }
        }
        else
        {
            $notify = array();
            $notify["status"]="failed";
            $notify["notify"][]="Details saving was failed. Requested record does not exist.";

            $response["notify"]=$notify;
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
        $model = LecturerWorkCategory::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = LecturerWorkCategory::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Search records
     * @param Request $request
     * @return JsonResponse
     */
    public function searchData(Request $request)
    {
        if($request->expectsJson())
        {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $typeNot = $request->post("type_not");
            $limit = $request->post("limit");

            $query = LecturerWorkCategory::query()
                ->select("id", "category_name")
                ->where("category_status", "=", "1")
                ->orderBy("category_name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if($searchText != "")
            {
                $query = $query->where("category_name", "LIKE", "%".$searchText."%");
            }

            if($idNot !== null)
            {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("id", $idNot);
            }

            if($typeNot !== null)
            {
                $typeNot = json_decode($typeNot, true);
                $query = $query->whereNotIn("category_type", $typeNot);
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
        $model = LecturerWorkCategory::query()->find($id);
        return $this->repository->updateStatus($model, "category_status");
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new LecturerWorkCategory();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
