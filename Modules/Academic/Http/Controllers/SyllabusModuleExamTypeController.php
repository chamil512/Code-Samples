<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\SyllabusModule;
use Modules\Academic\Entities\SyllabusModuleExamType;
use Modules\Academic\Repositories\SyllabusModuleExamTypeRepository;

class SyllabusModuleExamTypeController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new SyllabusModuleExamTypeRepository();
    }

    /**
     * Display a listing of the resource.
     * @param int $syllabusModuleId
     * @return Factory|View
     */
    public function index($syllabusModuleId)
    {
        $syllabusModule = SyllabusModule::query()->find($syllabusModuleId);

        $module = [];
        $syllabus = [];
        if ($syllabusModule) {
            $module = $syllabusModule->module->toArray();
            $syllabus = $syllabusModule->syllabus->toArray();

            if (!$module) {
                abort(404, "Module not available");
            }

            if (!$syllabus) {
                abort(404, "Syllabus not available");
            }
        } else {
            abort(404, "Syllabus Module not available");
        }

        $pageTitle = $syllabus["name"] . " | " . $module["name"] . " | Exam Types";

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new SyllabusModuleExamType());

        $this->repository->setColumns("id", "exam_type", "exam_category", "marks_percentage", "created_at")
            ->setColumnLabel("marks_percentage", "Marks Percentage (%)")
            ->setColumnDisplay("exam_type", array($this->repository, 'displayRelationAs'), ["exam_type", "exam_type_id", "exam_type"])
            ->setColumnDisplay("exam_category", array($this->repository, 'displayRelationAs'), ["exam_category", "exam_category_id", "exam_category"])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnFilterMethod("exam_type", "select", URL::to("/exam/exam_type/search_data"))
            ->setColumnFilterMethod("exam_category", "select", URL::to("/exam/exam_category/search_data"))
            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false)
            ->setColumnDBField("exam_type", "exam_type_id")
            ->setColumnFKeyField("exam_type", "exam_type_id")
            ->setColumnRelation("exam_type", "examType", "exam_type")
            ->setColumnDBField("exam_category", "exam_category_id")
            ->setColumnFKeyField("exam_category", "exam_category_id")
            ->setColumnRelation("exam_category", "examCategory", "exam_category");

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = $pageTitle . " | Trashed";
            $this->repository->setUrl("list", "/academic/syllabus_module_exam_type/" . $syllabusModuleId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $this->repository->setCustomControllerUrl("/academic/syllabus_module_exam_type", ["list"], false)
                ->setUrl("trashList", "/academic/syllabus_module_exam_type/trash/" . $syllabusModuleId);

            $this->repository->setTableTitle($pageTitle)
                ->enableViewData("trashList", "trash", "export");
        }

        $this->repository->setUrl("add", "/academic/syllabus_module_exam_type/create/" . $syllabusModuleId);

        $query = $query->where(["syllabus_module_id" => $syllabusModuleId]);
        $query = $query->with(["deliveryMode", "examType", "examCategory"]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param int $syllabusModuleId
     * @return Factory|View
     */
    public function trash($syllabusModuleId)
    {
        $this->trash = true;
        return $this->index($syllabusModuleId);
    }

    /**
     * Show the form for creating a new resource.
     * @param mixed $syllabusModuleId
     * @return Factory|View
     */
    public function create($syllabusModuleId)
    {
        $syllabusModule = SyllabusModule::query()->find($syllabusModuleId);

        $module = [];
        $syllabus = [];
        if ($syllabusModule) {
            $module = $syllabusModule->module->toArray();
            $syllabus = $syllabusModule->syllabus->toArray();

            if (!$module) {
                abort(404, "Module not available");
            }

            if (!$syllabus) {
                abort(404, "Syllabus not available");
            }
        } else {
            abort(404, "Syllabus Module not available");
        }
        $pageTitle = $syllabus["name"] . " | " . $module["name"] . " | Exam Types | Add New";

        $this->repository->setPageTitle($pageTitle);

        $model = new SyllabusModuleExamType();
        $model->syllabus = $syllabus;
        $model->module = $module;

        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/syllabus_module_exam_type/" . $syllabusModuleId);

        $this->repository->setPageUrls($urls);

        return view('academic::syllabus_module_exam_type.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @param $syllabusModuleId
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store($syllabusModuleId): JsonResponse
    {
        $syllabusModule = SyllabusModule::query()->find($syllabusModuleId);

        if ($syllabusModule) {
            $module = $syllabusModule->module->toArray();
            $syllabus = $syllabusModule->syllabus->toArray();

            if (!$module) {
                abort(404, "Module not available");
            }

            if (!$syllabus) {
                abort(404, "Syllabus not available");
            }
        } else {
            abort(404, "Syllabus Module not available");
        }

        $model = new SyllabusModuleExamType();

        $examCategoryIds = request()->post("exam_category");

        if (is_array($examCategoryIds) && count($examCategoryIds) > 0) {

            $model = $this->repository->getValidatedData($model, [
                "exam_type_id" => "required|exists:exam_types,exam_type_id",
                "exam_category_id" => "required|exists:exam_categories,exam_category_id",
                "marks_percentage" => "required|integer",
            ], [], ["exam_type_id" => "Exam Type", "exam_category_id" => "Exam Category"]);

            if ($this->repository->isValidData) {

                $currCategoryIds = $this->repository->getExamTypeExamCategories($syllabusModule, $model->exam_type_id);

                $foundNew = false;
                foreach ($examCategoryIds as $examCategoryId) {

                    if (!in_array($examCategoryId, $currCategoryIds)) {

                        $foundNew = true;

                        $model = new SyllabusModuleExamType();
                        $model->exam_type_id = request()->post("exam_type_id");
                        $model->exam_category_id = $examCategoryId;
                        $model->syllabus_module_id = $syllabusModuleId;

                        $this->repository->saveModel($model);
                    }
                }

                $notify = [];
                if ($foundNew) {

                    $notify["status"] = "success";
                    $notify["notify"][] = "Successfully saved the details";

                } else {
                    $notify["status"] = "failed";
                    $notify["notify"][] = "Selected exam type and categories already exist for this syllabus and module";

                }

                $response["notify"] = $notify;
            } else {
                $response = $model;
            }
        } else {

            $notify = [];
            $notify["status"] = "failed";
            $notify["notify"][] = "Please select at least one exam category before saving";

            $response["notify"] = $notify;
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
        $model = SyllabusModuleExamType::with(["deliveryMode", "examType", "examCategory"])->find($id);

        if ($model) {
            $syllabusModuleId = $model->syllabus_module_id;

            $syllabusModule = SyllabusModule::query()->find($syllabusModuleId);

            $module = [];
            $syllabus = [];
            if ($syllabusModule) {
                $module = $syllabusModule->module->toArray();
                $syllabus = $syllabusModule->syllabus->toArray();

                if (!$module) {
                    abort(404, "Module not available");
                }

                if (!$syllabus) {
                    abort(404, "Syllabus not available");
                }
            } else {
                abort(404, "Syllabus Module not available");
            }
            $pageTitle = $syllabus["name"] . " | " . $module["name"] . " | Exam Types | Edit";

            $model->syllabus = $syllabus;
            $model->module = $module;

            $this->repository->setPageTitle($pageTitle);

            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/syllabus_module_exam_type/create/" . $syllabusModuleId);
            $urls["listUrl"] = URL::to("/academic/syllabus_module_exam_type/syllabusModuleId/" . $syllabusModuleId);

            $this->repository->setPageUrls($urls);

            return view('academic::syllabus_module_exam_type.edit', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = SyllabusModuleExamType::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "exam_type_id" => "required|exists:exam_types,exam_type_id",
                "exam_category_id" => "required|exists:exam_categories,exam_category_id",
                "marks_percentage" => "required|integer",
            ], [], ["exam_type_id" => "Exam Type", "exam_category_id" => "Exam Category"]);

            if ($this->repository->isValidData) {
                //check if this record already exists
                $exist = SyllabusModuleExamType::query()
                    ->where("syllabus_module_id", $model->syllabus_module_id)
                    ->where("exam_type_id", $model->exam_type_id)
                    ->where("exam_category_id", $model->exam_category_id)
                    ->whereNotIn("smet_id", [$id])
                    ->get()
                    ->toArray();

                if (!$exist) {
                    $response = $this->repository->saveModel($model);
                } else {
                    $notify = [];
                    $notify["status"] = "failed";
                    $notify["notify"][] = "This exam type and category already exist for this syllabus and module";

                    $response["notify"] = $notify;
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
     * @param $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = SyllabusModuleExamType::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = SyllabusModuleExamType::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new SyllabusModuleExamType();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
