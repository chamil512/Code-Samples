<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicTimetable;
use Modules\Academic\Entities\AcademicTimetableCriteria;
use Modules\Academic\Repositories\AcademicTimetableCriteriaRepository;

class AcademicTimetableCriteriaController extends Controller
{
    private AcademicTimetableCriteriaRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new AcademicTimetableCriteriaRepository();
    }

    /**
     * Display a listing of the resource.
     * @param $timetableId
     * @return Factory|View
     */
    public function index($timetableId)
    {
        $timetable = AcademicTimetable::query()->find($timetableId);

        $ttTitle = "";
        if ($timetable) {
            $ttTitle = $timetable["name"];
        } else {
            abort(404, "Timetable not available");
        }
        $pageTitle = $ttTitle . " | Academic Timetable Criteria";

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new AcademicTimetableCriteria());

        $this->repository->setColumns("id", "delivery_mode", "mode_type", "created_at")
            ->setColumnLabel("mode_type", "Timetable Mode")

            ->setColumnDBField("delivery_mode", "delivery_mode_id")
            ->setColumnFKeyField("delivery_mode", "delivery_mode_id")
            ->setColumnRelation("delivery_mode", "deliveryMode", "mode_name")

            ->setColumnDisplay("delivery_mode", array($this->repository, 'displayRelationAs'), ["delivery_mode", "delivery_mode_id", "name"])

            ->setColumnFilterMethod("delivery_mode", "select", URL::to("/academic/module_delivery_mode/search_data"))

            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnSearchability("created_at", false);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = $ttTitle . " | Academic Timetable Criteria | Trashed";
            $this->repository->setUrl("list", "/academic/academic_timetable_criteria/" . $timetableId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "view", "restore", "export")
                ->disableViewData("edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = $ttTitle . " | Academic Timetable Criteria";
            $this->repository->setCustomControllerUrl("/academic/academic_timetable_criteria", ["list"], false)
                ->setUrl("trashList", "/academic/academic_timetable_criteria/trash/" . $timetableId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export")
                ->disableViewData("view");
        }

        $this->repository->setUrl("add", "/academic/academic_timetable_criteria/create/" . $timetableId);

        $query->where(["academic_timetable_id" => $timetableId]);
        $query->with(["deliveryMode"]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param int $timetableId
     * @return Factory|View
     */
    public function trash($timetableId)
    {
        $this->trash = true;
        return $this->index($timetableId);
    }

    /**
     * Show the form for creating a new resource.
     * @param mixed $timetableId
     * @return Factory|View
     */
    public function create($timetableId)
    {
        $timetable = AcademicTimetable::query()->find($timetableId);

        if (!$timetable) {
            abort(404, "Timetable not available");
        }

        $model = new AcademicTimetableCriteria();
        $model->academic_timetable_id = $timetableId;
        $model->timetable = $timetable;

        $record = $model;

        $formMode = "add";
        $formSubmitUrl = URL::to("/" . request()->path());

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/academic_timetable_criteria/" . $timetableId);

        $this->repository->setPageUrls($urls);

        return view('academic::academic_timetable_criteria.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @param $timetableId
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store($timetableId): JsonResponse
    {
        $timetable = AcademicTimetable::query()->find($timetableId);

        if (!$timetable) {
            abort(404, "Timetable not available");
        }

        $model = new AcademicTimetableCriteria();

        $model = $this->repository->getValidatedData($model, [
            "delivery_mode_id" => "required|exists:module_delivery_modes,delivery_mode_id",
            "mode_type" => "required",
            "week_days" => "",
            "timetable_criteria" => "",
        ], [], ["delivery_mode_id" => "Delivery Mode", "mode_type" => "Timetable Mode"]);

        if ($this->repository->isValidData) {

            $model->academic_timetable_id = $timetableId;

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
        $model = AcademicTimetableCriteria::with(["timetable", "deliveryMode"])->find($id);

        if ($model) {
            $timetableId = $model->academic_timetable_id;

            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = URL::to("/" . request()->path());

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/academic_timetable_criteria/create/" . $timetableId);
            $urls["listUrl"] = URL::to("/academic/academic_timetable_criteria/" . $timetableId);

            $this->repository->setPageUrls($urls);

            return view('academic::academic_timetable_criteria.create', compact('formMode', 'formSubmitUrl', 'record'));
        } else {
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
        $model = AcademicTimetableCriteria::query()->find($id);

        if ($model) {

            $model = $this->repository->getValidatedData($model, [
                "delivery_mode_id" => "required|exists:module_delivery_modes,delivery_mode_id",
                "mode_type" => "required",
                "week_days" => "",
                "timetable_criteria" => "",
            ], [], ["delivery_mode_id" => "Delivery Mode", "mode_type" => "Timetable Mode"]);

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
        $model = AcademicTimetableCriteria::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = AcademicTimetableCriteria::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $options = [];
        $options["title"] = "Academic Timetable Criteria";

        $model = new AcademicTimetableCriteria();
        return $this->repository->recordHistory($model, $modelHash, $id, $options);
    }
}
