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
use Modules\Academic\Entities\ModuleDeliveryMode;
use Modules\Academic\Repositories\ModuleDeliveryModeRepository;

class ModuleDeliveryModeController extends Controller
{
    private ModuleDeliveryModeRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new ModuleDeliveryModeRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Module Delivery Modes");

        $this->repository->initDatatable(new ModuleDeliveryMode());

        $this->repository->setColumns("id", "mode_name", "type", "mode_status", "created_at")
            ->setColumnLabel("mode_status", "Status")
            ->setColumnDisplay("type", array($this->repository, 'displayStatusAs'), [$this->repository->types])
            ->setColumnDisplay("mode_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("type", "select", $this->repository->types)
            ->setColumnFilterMethod("mode_status", "select", $this->repository->statuses)

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Module Delivery Modes | Trashed")
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Module Delivery Modes")
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
        $model = new ModuleDeliveryMode();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/".request()->path();

        $urls = [];
        $urls["listUrl"]=URL::to("/academic/module_delivery_mode");

        $this->repository->setPageUrls($urls);

        $types = $this->repository->types;

        return view('academic::module_delivery_mode.create', compact('formMode', 'formSubmitUrl', 'record', 'types'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(): JsonResponse
    {
        $model = new ModuleDeliveryMode();

        $model = $this->repository->getValidatedData($model, [
            "mode_name" => "required",
            "type" => "required",
        ]);

        if($this->repository->isValidData)
        {
            //set mode_status as 0 when inserting the record
            $model->mode_status = 1;

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
        $model = ModuleDeliveryMode::query()->find($id);

        if($model)
        {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/module_delivery_mode/create");
            $urls["listUrl"]=URL::to("/academic/module_delivery_mode");

            $this->repository->setPageUrls($urls);

            return view('academic::module_delivery_mode.view', compact('record'));
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
        $model = ModuleDeliveryMode::query()->find($id);

        if($model)
        {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/module_delivery_mode/create");
            $urls["listUrl"]=URL::to("/academic/module_delivery_mode");

            $this->repository->setPageUrls($urls);

            $types = $this->repository->types;

            return view('academic::module_delivery_mode.create', compact('formMode', 'formSubmitUrl', 'record', 'types'));
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
        $model = ModuleDeliveryMode::query()->find($id);

        if($model)
        {
            $model = $this->repository->getValidatedData($model, [
                "mode_name" => "required",
                "type" => "required",
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
        $model = ModuleDeliveryMode::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = ModuleDeliveryMode::withTrashed()->find($id);

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

            $query = ModuleDeliveryMode::query()
                ->select("delivery_mode_id", "mode_name", "type")
                ->where("mode_status", "=", "1")
                ->orderBy("mode_name");

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
                $query = $query->where("mode_name", "LIKE", "%".$searchText."%");
            }

            if($idNot != "")
            {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("delivery_mode_id", $idNot);
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
        $model = ModuleDeliveryMode::query()->find($id);
        return $this->repository->updateStatus($model, "mode_status");
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new ModuleDeliveryMode();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
