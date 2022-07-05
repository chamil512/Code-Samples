<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\RetentionCurriculumActivityType;
use Modules\Academic\Repositories\RetentionCurriculumActivityTypeRepository;

class RetentionCurriculumActivityTypeController extends Controller
{
    private RetentionCurriculumActivityTypeRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new RetentionCurriculumActivityTypeRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Retention Curriculum Activity Types");

        $this->repository->initDatatable(new RetentionCurriculumActivityType());

        $this->repository->setColumns("id", "activity_type", "type_status", "created_at")
            ->setColumnLabel("type_status", "Status")
            ->setColumnDisplay("type_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("activity_type")
            ->setColumnFilterMethod("type_status", "select", $this->repository->statuses)

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Retention Curriculum Activity Types | Trashed")
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Retention Curriculum Activity Types")
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
        $model = new RetentionCurriculumActivityType();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/".request()->path();

        $urls = [];
        $urls["listUrl"]=URL::to("/academic/retention_curriculum_activity_type");

        $this->repository->setPageUrls($urls);

        return view('academic::retention_curriculum_activity_type.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(): JsonResponse
    {
        $model = new RetentionCurriculumActivityType();

        $model = $this->repository->getValidatedData($model, [
            "activity_type" => "required",
            "type_status" => "required|digits:1",
        ]);

        if($this->repository->isValidData)
        {
            //set type_status as 0 when inserting the record
            $model->type_status = 1;

            $response = $this->repository->saveModel($model);
        }
        else
        {
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
        $model = RetentionCurriculumActivityType::query()->find($id);

        if($model)
        {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/retention_curriculum_activity_type/create");
            $urls["listUrl"]=URL::to("/academic/retention_curriculum_activity_type");

            $this->repository->setPageUrls($urls);

            return view('academic::retention_curriculum_activity_type.view', compact('record'));
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
        $model = RetentionCurriculumActivityType::query()->find($id);

        //dd(get_class($model));

        if($model)
        {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/retention_curriculum_activity_type/create");
            $urls["listUrl"]=URL::to("/academic/retention_curriculum_activity_type");

            $this->repository->setPageUrls($urls);

            return view('academic::retention_curriculum_activity_type.create', compact('formMode', 'formSubmitUrl', 'record'));
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
     * @throws ValidationException
     */
    public function update($id): JsonResponse
    {
        $model = RetentionCurriculumActivityType::query()->find($id);

        if($model)
        {
            $model = $this->repository->getValidatedData($model, [
                "activity_type" => "required",
                "type_status" => "required|digits:1",
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
        $model = RetentionCurriculumActivityType::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = RetentionCurriculumActivityType::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Search records
     * @param Request $request
     * @return JsonResponse
     */
    public function searchData(Request $request): JsonResponse
    {
        if($request->expectsJson())
        {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $limit = $request->post("limit");

            $query = RetentionCurriculumActivityType::query()
                ->select("id", "activity_type")
                ->where("type_status", "=", "1")
                ->orderBy("activity_type");

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
                $query = $query->where("activity_type", "LIKE", "%".$searchText."%");
            }

            if($idNot != "")
            {
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
     * @return JsonResponse|RedirectResponse|null
     */
    public function changeStatus($id)
    {
        $model = RetentionCurriculumActivityType::query()->find($id);
        return $this->repository->updateStatus($model, "type_status");
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new RetentionCurriculumActivityType();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
