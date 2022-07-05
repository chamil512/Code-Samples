<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicTimetable;
use Modules\Academic\Entities\TimetableCompletedModule;
use Modules\Academic\Repositories\TimetableCompletedModuleRepository;

class TimetableCompletedModuleController extends Controller
{
    private TimetableCompletedModuleRepository $repository;

    public function __construct()
    {
        $this->repository = new TimetableCompletedModuleRepository();
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
        $pageTitle = $ttTitle . " | Academic Timetable Completed Modules";

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new TimetableCompletedModule());

        $this->repository->setColumns("id", "module", $this->repository->statusField, "created_at")
            ->setColumnLabel($this->repository->statusField, "Completion Status")

            ->setColumnDBField("module", "module_id")
            ->setColumnFKeyField("module", "module_id")
            ->setColumnRelation("module", "module", "name")

            ->setColumnDisplay("module", array($this->repository, 'displayRelationAs'), ["module", "module_id", "name"])
            ->setColumnDisplay($this->repository->statusField, array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses, "", "", true])

            ->setColumnFilterMethod("module", "select", URL::to("/academic/module_module/search_data"))
            ->setColumnFilterMethod($this->repository->statusField, "select", $this->repository->statuses)

            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnSearchability("created_at", false);

        $query = $this->repository->model::query();

        $tableTitle = $ttTitle . " | Academic Timetable Completed Modules";
        $this->repository->setCustomControllerUrl("/academic/timetable_completed_module", ["list"], false);

        $this->repository->setTableTitle($tableTitle)
            ->enableViewData("export")
            ->disableViewData("view", "edit", "delete", "trashList", "trash");

        $this->repository->setUrl("add", "/academic/timetable_completed_module/update/" . $timetableId);
        $this->repository->setUrlLabel("add", "Update Completion Status");

        $query->where(["academic_timetable_id" => $timetableId]);
        $query->with(["module"]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Show the form for creating a new resource.
     * @param mixed $timetableId
     * @return Factory|View
     */
    public function update($timetableId)
    {
        $timetable = AcademicTimetable::query()->find($timetableId);

        if (!$timetable) {
            abort(404, "Timetable not available");
        }

        $formMode = "add";
        $formSubmitUrl = URL::to("/" . request()->path());

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/timetable_completed_module/" . $timetableId);

        $this->repository->setPageUrls($urls);

        $records = $this->repository->getData($timetable);

        $record = $timetable->toArray();

        return view('academic::timetable_completed_module.create',
            compact('formMode', 'formSubmitUrl', 'record', 'records'));
    }

    /**
     * Store a newly created resource in storage.
     * @param $timetableId
     * @return JsonResponse
     */
    public function save($timetableId): JsonResponse
    {
        $timetable = AcademicTimetable::query()->find($timetableId);

        if (!$timetable) {
            abort(404, "Timetable not available");
        }

        $response = $this->repository->updateData($timetable);

        return $this->repository->handleResponse($response);
    }

    /**
     * Move the record to trash
     * @param $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = TimetableCompletedModule::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = TimetableCompletedModule::withTrashed()->find($id);

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
        $options["title"] = "Academic Timetable Completed Module";

        $model = new TimetableCompletedModule();
        return $this->repository->recordHistory($model, $modelHash, $id, $options);
    }
}
