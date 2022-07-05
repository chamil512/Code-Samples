<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Academic\Entities\Batch;
use Modules\Academic\Entities\BatchCoordinator;
use Modules\Academic\Repositories\BatchCoordinatorRepository;

class BatchCoordinatorController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new BatchCoordinatorRepository();
    }

    /**
     * Display a listing of the resource.
     * @param mixed $batchId
     * @return Factory|View
     */
    public function index($batchId=false)
    {
        $depTitle = "";
        if ($batchId) {
            $cc = Batch::query()->find($batchId);

            if ($cc) {
                $depTitle = $cc["batch_name"];
            } else {
                abort(404, "Batch not available");
            }
        }

        $pageTitle = "Batch Coordinators";
        if($depTitle != "")
        {
            $pageTitle = $depTitle." | ".$pageTitle;
        }

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new BatchCoordinator());

        $this->repository->setColumns("id", "name", "batch", "date_from", "date_till", "status", "created_at")
            ->setColumnLabel("name", "Batch Coordinator")
            ->setColumnLabel("batch", "Batch")
            ->setColumnLabel("date_from", "Period From")
            ->setColumnLabel("date_till", "Period Till")
            ->setColumnLabel("status", "Current/Former Status")

            ->setColumnDBField("batch", "batch_id")
            ->setColumnFKeyField("batch", "batch_id")
            ->setColumnRelation("batch", "batch", "batch_name")

            ->setColumnDisplay("batch", array($this->repository, 'displayRelationAs'), ["batch", "batch_id", "batch_name"])
            ->setColumnDisplay("status", array($this->repository, 'displayStatusAs'), [$this->repository->statuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("batch", "select", URL::to("/academic/batch/search_data"))
            ->setColumnFilterMethod("date_from", "date_after")
            ->setColumnFilterMethod("date_till", "date_before")
            ->setColumnFilterMethod("status", "select", $this->repository->statuses)

            ->setColumnSearchability("name", false)
            ->setColumnOrderability("name", false)
            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = "Batch Coordinators | Trashed";
            if ($batchId) {
                if ($depTitle != "") {
                    $tableTitle = $depTitle . " | " . $tableTitle;

                    $this->repository->setUrl("list", "/academic/batch_coordinator/" . $batchId);
                }
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = "Batch Coordinators";
            if ($batchId) {
                if ($depTitle != "") {
                    $tableTitle = $depTitle . " | " . $tableTitle;

                    $this->repository->setUrl("trashList", "/academic/batch_coordinator/trash/" . $batchId);
                }
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export");
        }

        if ($batchId) {
            $this->repository->setUrl("add", "/academic/batch_coordinator/create/" . $batchId);

            $this->repository->unsetColumns("batch");
            $query = $query->where(["batch_id" => $batchId]);
        } else {
            $query = $query->with(["batch"]);
        }

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param $batchId
     * @return Factory|View
     */
    public function trash($batchId = false)
    {
        $this->trash = true;
        return $this->index($batchId);
    }

    /**
     * Show the form for creating a new resource.
     * @param $batchId
     * @return Factory|View
     */
    public function create($batchId = false)
    {
        $model = new BatchCoordinator();

        if ($batchId) {
            $model->batch_id = $batchId;
            $model->batch()->first();
        }
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        if ($batchId) {
            $urls["listUrl"] = URL::to("/academic/batch_coordinator/" . $batchId);
        } else {
            $urls["listUrl"] = URL::to("/academic/batch_coordinator");
        }

        $this->repository->setPageUrls($urls);

        return view('academic::batch_coordinator.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     */
    public function store()
    {
        $model = new BatchCoordinator();

        $model = $this->repository->getValidatedData($model, [
            "batch_id" => "required|exists:batches,batch_id",
            "admin_id" => "required|exists:admins,admin_id",
            "description" => "",
            "date_from" => "required|date",
            "date_till" => [Rule::requiredIf(function () {
                return request()->post("status") == "0";
            })],
            "status" => "required",
        ], [], ["batch_id" => "Batch", "admin_id" => "Batch Coordinator Name", "date_from" => "Date From", "date_till" => "Date Till"]);

        if ($this->repository->isValidData) {

            $response = $this->repository->validateHOD($model->batch_id);

            if ($this->repository->isValidHOD) {

                $response = $this->repository->saveModel($model);
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
        $model = BatchCoordinator::query()->find($id);

        if ($model) {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/batch_coordinator/create");
            $urls["listUrl"] = URL::to("/academic/batch_coordinator");

            $this->repository->setPageUrls($urls);

            return view('academic::batch_coordinator.view', compact('record'));
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
        $model = BatchCoordinator::with(["batch", "admin"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/batch_coordinator/create");
            $urls["listUrl"] = URL::to("/academic/batch_coordinator");

            $this->repository->setPageUrls($urls);

            return view('academic::batch_coordinator.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = BatchCoordinator::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "batch_id" => "required|exists:batches,batch_id",
                "admin_id" => "required|exists:admins,admin_id",
                "description" => "",
                "date_from" => "required|date",
                "date_till" => [Rule::requiredIf(function () {
                    return request()->post("status") == "0";
                })],
                "status" => "required",
            ], [], ["batch_id" => "Batch", "admin_id" => "Batch Coordinator Name", "date_from" => "Date From", "date_till" => "Date Till"]);

            if ($this->repository->isValidData) {

                $response = $this->repository->validateHOD($model->batch_id);

                if ($this->repository->isValidHOD) {

                    $response = $this->repository->saveModel($model);
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
        $model = BatchCoordinator::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = BatchCoordinator::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new BatchCoordinator();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
