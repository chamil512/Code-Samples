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
use Modules\Academic\Entities\BatchAvailabilityRestriction;
use Modules\Academic\Repositories\BatchAvailabilityDateRepository;
use Modules\Academic\Repositories\BatchAvailabilityRestrictionRepository;

class BatchAvailabilityRestrictionController extends Controller
{
    private BatchAvailabilityRestrictionRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new BatchAvailabilityRestrictionRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Batch Availability Restrictions");

        $this->repository->initDatatable(new BatchAvailabilityRestriction());

        $this->repository->setColumns("id", "name", "batch", "academic_year", "semester", "created_at")

            ->setColumnDBField("batch", "batch_id")
            ->setColumnFKeyField("batch", "batch_id")
            ->setColumnRelation("batch", "batch", "batch_name")

            ->setColumnDBField("academic_year", "academic_year_id")
            ->setColumnFKeyField("academic_year", "academic_year_id")
            ->setColumnRelation("academic_year", "academicYear", "year_name")

            ->setColumnDBField("semester", "semester_id")
            ->setColumnFKeyField("semester", "semester_id")
            ->setColumnRelation("semester", "semester", "semester_name")

            ->setColumnDisplay("batch", array($this->repository, 'displayRelationAs'), ["batch", "batch_id", "batch_name"])
            ->setColumnDisplay("academic_year", array($this->repository, 'displayRelationAs'), ["academic_year", "academic_year_id", "year_name"])
            ->setColumnDisplay("semester", array($this->repository, 'displayRelationAs'), ["semester", "semester_id", "semester_name"])

            ->setColumnFilterMethod("batch", "select", URL::to("/academic/batch/search_data"))
            ->setColumnFilterMethod("academic_year", "select", URL::to("/academic/academic_year/search_data"))
            ->setColumnFilterMethod("semester", "select", URL::to("/academic/academic_semester/search_data"))

            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnSearchability("created_at", false);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Batch Availability Restrictions | Trashed")
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Batch Availability Restrictions")
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
        $model = new BatchAvailabilityRestriction();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = URL::to("/" . request()->path());

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/batch_availability_restriction");

        $this->repository->setPageUrls($urls);

        return view('academic::batch_availability_restriction.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(): JsonResponse
    {
        $model = new BatchAvailabilityRestriction();

        $model = $this->repository->getValidatedData($model, [
            "name" => "required",
            "batch_id" => "required|exists:batches,batch_id",
            "academic_year_id" => "required|exists:academic_years,academic_year_id",
            "semester_id" => "required|exists:academic_semesters,semester_id",
        ], [], ["batch_id" => "Batch", "academic_year_id" => "Academic Year", "semester_id" => "Semester"]);

        if ($this->repository->isValidData) {
            $response = $this->repository->saveModel($model);

            if ($response["notify"]["status"] === "success"){

                $badRepo = new BatchAvailabilityDateRepository();
                $badRepo->update($model);
            }
        } else {
            $response = $model;
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Show the specified resource.
     * @param
     * @return Application|Factory|View|void
     */
    public function show($id)
    {
        $model = BatchAvailabilityRestriction::query()->find($id);

        if ($model) {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/batch_availability_restriction/create");
            $urls["listUrl"] = URL::to("/academic/batch_availability_restriction");

            $this->repository->setPageUrls($urls);

            return view('academic::batch_availability_restriction.view', compact('record'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Show the form for editing the specified resource.
     * @param
     * @return Application|Factory|View|void
     */
    public function edit($id)
    {
        $model = BatchAvailabilityRestriction::query()->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = URL::to("/" . request()->path());

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/batch_availability_restriction/create");
            $urls["listUrl"] = URL::to("/academic/batch_availability_restriction");

            $this->repository->setPageUrls($urls);

            $dates = $this->repository->getDates($model);

            return view('academic::batch_availability_restriction.create', compact('formMode', 'formSubmitUrl', 'record', 'dates'));
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
        $model = BatchAvailabilityRestriction::query()->find($id);

        if ($model) {

            $model = $this->repository->getValidatedData($model, [
                "name" => "required",
                "batch_id" => "required|exists:batches,batch_id",
                "academic_year_id" => "required|exists:academic_years,academic_year_id",
                "semester_id" => "required|exists:academic_semesters,semester_id",
            ], [], ["batch_id" => "Batch", "academic_year_id" => "Academic Year", "semester_id" => "Semester"]);

            if ($this->repository->isValidData) {
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] === "success"){

                    $badRepo = new BatchAvailabilityDateRepository();
                    $badRepo->update($model);

                    $response["data"]["dates"] = $this->repository->getDates($model);
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
        $model = BatchAvailabilityRestriction::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = BatchAvailabilityRestriction::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new BatchAvailabilityRestriction();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
