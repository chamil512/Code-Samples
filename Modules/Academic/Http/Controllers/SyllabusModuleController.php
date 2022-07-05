<?php
namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\SyllabusModule;
use Modules\Academic\Entities\CourseSyllabus;
use Modules\Academic\Repositories\SyllabusModuleRepository;

class SyllabusModuleController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new SyllabusModuleRepository();
    }

    /**
     * Display a listing of the resource.
     * @param int $syllabusId
     * @return Factory|View
     */
    public function index($syllabusId)
    {
        $syllabusTitle = "";
        $syllabus = CourseSyllabus::query()->find($syllabusId);

        if($syllabus)
        {
            $syllabusTitle = $syllabus["syllabus_name"];
        }
        else
        {
            abort(404, "Course Syllabus not available");
        }

        $pageTitle = "Syllabus Modules";
        if($syllabusTitle != "")
        {
            $pageTitle = $syllabusTitle." | ".$pageTitle;
        }

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new SyllabusModule());

        $this->repository->setColumns("id", "syllabus", "module", "academic_year", "semester", "mandatory_status", "total_hours", "total_credits", "exam_types", "created_at")

            ->setColumnDBField("syllabus", "syllabus_id")
            ->setColumnFKeyField("syllabus", "syllabus_id")
            ->setColumnRelation("syllabus", "syllabus", "syllabus_name")

            ->setColumnDBField("module", "module_id")
            ->setColumnFKeyField("module", "module_id")
            ->setColumnRelation("module", "module", "module_name")

            ->setColumnDBField("academic_year", "module_id")
            ->setColumnFKeyField("academic_year", "module_id")
            ->setColumnRelation("academic_year", "module", "module_name")
            ->setColumnCoRelation("academic_year", "academicYear", "year_name", "academic_year_id")

            ->setColumnDBField("semester", "module_id")
            ->setColumnFKeyField("semester", "module_id")
            ->setColumnRelation("semester", "module", "module_name")
            ->setColumnCoRelation("semester", "semester", "semester_name", "semester_id")

            ->setColumnDisplay("syllabus", array($this->repository, 'displayRelationAs'), ["syllabus", "syllabus_id", "syllabus_name", URL::to("/academic/syllabus/view/")])
            ->setColumnDisplay("module", array($this->repository, 'displayRelationAs'), ["module", "module_id", "module_name"])
            ->setColumnDisplay("academic_year", array($this->repository, 'displayRelationAs'), ["module", "module_id", "year_name"])
            ->setColumnDisplay("semester", array($this->repository, 'displayRelationAs'), ["module", "module_id", "semester_name"])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnDisplay("mandatory_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->mandatoryStatuses])

            ->setColumnFilterMethod("mandatory_status", "select", $this->repository->mandatoryStatuses)
            ->setColumnFilterMethod("syllabus", "select", URL::to("/academic/course_syllabus/search_data"))
            ->setColumnFilterMethod("module", "select", URL::to("/academic/course_module/search_data/?course_id=" . $syllabus["course_id"]))
            ->setColumnFilterMethod("academic_year", "select", URL::to("/academic/academic_year/search_data"))
            ->setColumnFilterMethod("semester", "select", URL::to("/academic/academic_semester/search_data"))

            ->setColumnDisplay("exam_types", array($this->repository, 'displayListButtonAs'), ["Exam Types", URL::to("/academic/syllabus_module_exam_type/")])
            ->setColumnDBField("exam_types", $this->repository->primaryKey)

            ->setColumnSearchability("created_at", false);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = $syllabusTitle." | Syllabus Modules | Trashed";
            $this->repository->setUrl("list", "/academic/syllabus_module/syllabus/".$syllabusId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "view", "restore", "export")
                ->disableViewData("edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $tableTitle = $syllabusTitle." | Syllabus Modules";

            $this->repository->setCustomControllerUrl("/academic/syllabus_module", ["list"], false)
                ->setUrl("trashList", "/academic/syllabus_module/syllabus/trash/".$syllabusId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("view", "trashList", "trash", "export");
        }

        $this->repository->setUrl("add", "/academic/syllabus_module/create/".$syllabusId);
        $this->repository->unsetColumns("syllabus");
        $query = $query->where(["syllabus_id" => $syllabusId]);

        $query = $query->with(["syllabus", "module"]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param int $syllabusId
     * @return Factory|View
     */
    public function trash($syllabusId)
    {
        $this->trash = true;
        return $this->index($syllabusId);
    }

    /**
     * Show the form for creating a new resource.
     * @param int $syllabusId
     * @return Factory|View
     */
    public function create($syllabusId)
    {
        $syllabus = CourseSyllabus::with(["course"])->find($syllabusId);
        if(!$syllabus)
        {
            abort(404, "Course Syllabus not available");
        }

        $model = new SyllabusModule();
        $model->syllabus_id = $syllabusId;
        $model->syllabus = $syllabus;
        $model->course = $syllabus->course;

        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/".request()->path();

        $urls = [];
        $urls["listUrl"]=URL::to("/academic/syllabus_module/".$syllabusId);

        $this->repository->setPageUrls($urls);

        $mandatoryStatuses = $this->repository->mandatoryStatuses;

        return view('academic::syllabus_module.create', compact('formMode', 'formSubmitUrl', 'record', 'mandatoryStatuses'));
    }

    /**
     * Store a newly created resource in storage.
     * @param int $syllabusId
     * @return JsonResponse
     */
    public function store($syllabusId)
    {
        $syllabus = CourseSyllabus::query()->find($syllabusId);
        if(!$syllabus)
        {
            abort(404, "Course Syllabus not available");
        }

        $model = new SyllabusModule();

        $model = $this->repository->getValidatedData($model, [
            "module_id" => "required|exists:course_modules,module_id",
            "exempted_status" => "required",
            "mandatory_status" => "required",
            "module_order" => "required",
        ], [], ["module_id" => "Course Module", "exempted_status" => "Default/Exempted Status", "module_order" => "Module Order"]);

        if($this->repository->isValidData)
        {
            $model->syllabus_id = $syllabusId;
            $response = $this->repository->saveModel($model);

            if($response["notify"]["status"] == "success")
            {
                $this->repository->updateMDModes($model);
            }
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
        $model = SyllabusModule::withTrashed()->with([
            "syllabus",
            "module",
            "deliveryModes",
            "examTypes",
            "createdUser",
            "updatedUser",
            "deletedUser"])->find($id);

        if($model)
        {
            $record = $model->toArray();

            $controllerUrl = URL::to("/academic/syllabus_module/");

            $urls = [];
            $urls["addUrl"]=URL::to($controllerUrl . "/create/".$model->syllabus_id);
            $urls["listUrl"]=URL::to($controllerUrl . "/" . $model->syllabus_id);
            $urls["editUrl"]=URL::to($controllerUrl . "/edit/" . $id);
            $urls["adminUrl"]=URL::to("/admin/admin/view/");
            $urls["recordHistoryUrl"]=$this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);
            $urls["approvalHistoryUrl"]=$this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);

            $this->repository->setPageUrls($urls);

            $statusInfo = [];
            $statusInfo["exempted_status"] = $this->repository->getStatusInfo($model, "exempted_status", $this->repository->exemptedStatuses);
            $statusInfo["mandatory_status"] = $this->repository->getStatusInfo($model, "mandatory_status", $this->repository->mandatoryStatuses);

            return view('academic::syllabus_module.view', compact('record', 'statusInfo'));
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
        $model = SyllabusModule::with(["syllabus", "module", "deliveryModes"])->find($id);

        if($model)
        {
            $model->course = $model->syllabus->course;
            $record = $model->toArray();

            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/syllabus_module/create/".$model->syllabus_id);
            $urls["listUrl"]=URL::to("/academic/syllabus_module/".$model->syllabus_id);

            $this->repository->setPageUrls($urls);

            $mandatoryStatuses = $this->repository->mandatoryStatuses;

            return view('academic::syllabus_module.create', compact('formMode', 'formSubmitUrl', 'record', 'mandatoryStatuses'));
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
    public function update($id)
    {
        $model = SyllabusModule::query()->find($id);

        if($model)
        {
            $model = $this->repository->getValidatedData($model, [
                "module_id" => "required|exists:course_modules,module_id",
                "exempted_status" => "required",
                "mandatory_status" => "required",
                "module_order" => "required",
            ], [], ["module_id" => "Course Module", "exempted_status" => "Default/Exempted Status", "module_order" => "Module Order"]);

            if($this->repository->isValidData)
            {
                $response = $this->repository->saveModel($model);

                if($response["notify"]["status"] == "success")
                {
                    $this->repository->updateMDModes($model);
                }
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
        $model = SyllabusModule::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = SyllabusModule::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return mixed
     */
    public function changeStatus($id)
    {
        $model = SyllabusModule::query()->find($id);
        return $this->repository->updateStatus($model, "mandatory_status");
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new SyllabusModule();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
