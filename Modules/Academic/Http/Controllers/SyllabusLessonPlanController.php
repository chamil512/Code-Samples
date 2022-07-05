<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\SyllabusLessonPlan;
use Modules\Academic\Entities\CourseSyllabus;
use Modules\Academic\Entities\SyllabusModule;
use Modules\Academic\Repositories\SyllabusLessonPlanRepository;

class SyllabusLessonPlanController extends Controller
{
    private SyllabusLessonPlanRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new SyllabusLessonPlanRepository();
    }

    /**
     * Display a listing of the resource.
     * @param mixed $syllabusId
     * @return Factory|View
     */
    public function index($syllabusId = false)
    {
        $baseTitle = "";
        if ($syllabusId) {
            $cc = CourseSyllabus::query()->find($syllabusId);

            if ($cc) {
                $baseTitle = $cc["name"];
            } else {
                abort(404, "Syllabus not available");
            }
        }

        $pageTitle = "Syllabus Lesson Plans";
        if ($baseTitle != "") {
            $pageTitle = $baseTitle . " | " . $pageTitle;
        }

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new SyllabusLessonPlan());

        $this->repository->setColumns("id", "name", "syllabus", "batch", $this->repository->statusField, $this->repository->approvalField, "created_at")
            ->setColumnLabel($this->repository->statusField, "Status")

            ->setColumnDBField("syllabus", "syllabus_id")
            ->setColumnFKeyField("syllabus", "syllabus_id")
            ->setColumnRelation("syllabus", "syllabus", "syllabus_name")

            ->setColumnDBField("batch", "batch_id")
            ->setColumnFKeyField("batch", "batch_id")
            ->setColumnRelation("batch", "batch", "batch_name")

            ->setColumnDisplay("syllabus", array($this->repository, 'displayRelationAs'), ["syllabus", "syllabus_id", "syllabus_name"])
            ->setColumnDisplay("batch", array($this->repository, 'displayRelationAs'), ["batch", "batch_id", "batch_name"])
            ->setColumnDisplay($this->repository->statusField, array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses, "/academic/syllabus_lesson_plan/change_status/", "", true])
            ->setColumnDisplay($this->repository->approvalField, array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod($this->repository->statusField, "select", $this->repository->statuses)
            ->setColumnFilterMethod("syllabus", "select", URL::to("/academic/course_syllabus/search_data"))
            ->setColumnFilterMethod("batch", "select", URL::to("/academic/batch/search_data"))

            ->setColumnSearchability("created_at", false);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = "Syllabus Lesson Plans | Trashed";
            if ($syllabusId) {
                if ($baseTitle != "") {
                    $tableTitle = $baseTitle . " | " . $tableTitle;

                    $this->repository->setUrl("list", "/academic/syllabus_lesson_plan/" . $syllabusId);
                }
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "view", "restore", "export")
                ->disableViewData("edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = "Syllabus Lesson Plans";
            if ($syllabusId) {
                if ($baseTitle != "") {
                    $tableTitle = $baseTitle . " | " . $tableTitle;

                    $this->repository->setUrl("trashList", "/academic/syllabus_lesson_plan/trash/" . $syllabusId);
                }
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("view", "trashList", "trash", "export")
                ->setRowActionBeforeButton(URL::to("/academic/syllabus_lesson_plan/duplicate/"), "Duplicate", "", "fa fa-copy");;
        }

        if ($syllabusId) {
            $this->repository->setUrl("add", "/academic/syllabus_lesson_plan/create/" . $syllabusId);

            $this->repository->unsetColumns("syllabus");
            $query = $query->where(["syllabus_id" => $syllabusId]);
        } else {
            $query = $query->with(["syllabus", "batch"]);
        }

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param $syllabusId
     * @return Factory|View
     */
    public function trash($syllabusId = false)
    {
        $this->trash = true;
        return $this->index($syllabusId);
    }

    /**
     * Show the form for creating a new resource.
     * @param $syllabusId
     * @return Factory|View
     */
    public function create($syllabusId = false)
    {
        $model = new SyllabusLessonPlan();

        if ($syllabusId) {
            $model->syllabus_id = $syllabusId;
            $model->syllabus()->get();
        }
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = URL::to("/" . request()->path());

        $urls = [];
        if ($syllabusId) {
            $urls["listUrl"] = URL::to("/academic/syllabus_lesson_plan/" . $syllabusId);
        } else {
            $urls["listUrl"] = URL::to("/academic/syllabus_lesson_plan");
        }

        $this->repository->setPageUrls($urls);

        return view('academic::syllabus_lesson_plan.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(): JsonResponse
    {
        $model = new SyllabusLessonPlan();

        $model = $this->repository->getValidatedData($model, [
            "syllabus_id" => "required|exists:course_syllabi,syllabus_id",
            "batch_id" => "required|exists:batches,batch_id",
            "name" => "required",
        ], [], ["syllabus_id" => "Syllabus", "name" => "Syllabus Lesson Plan name", "batch_id" => "Batch"]);

        if ($this->repository->isValidData) {

            $response = $this->repository->saveModel($model);
        } else {
            $response = $model;
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Display a listing of the resource.
     * @param $id
     * @return Factory|View
     */
    public function show($id)
    {
        $plan = SyllabusLessonPlan::query()->find($id);
        $syllabus = null;

        $planTitle = "";
        if ($plan) {
            $planTitle = $plan["name"];

            $syllabus = CourseSyllabus::query()->find($plan->syllabus_id);
        } else {
            abort(404, "Syllabus Lesson Plan not available");
        }

        $pageTitle = $planTitle . " | Modules";

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new SyllabusModule());

        $this->repository->setColumns("id", "syllabus", "module", "academic_year", "semester", "created_at")
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

            ->setColumnFilterMethod("syllabus", "select", URL::to("/academic/course_syllabus/search_data"))
            ->setColumnFilterMethod("module", "select", URL::to("/academic/course_module/search_data/?course_id=" . $syllabus["course_id"]))
            ->setColumnFilterMethod("academic_year", "select", URL::to("/academic/academic_year/search_data"))
            ->setColumnFilterMethod("semester", "select", URL::to("/academic/academic_semester/search_data"))

            ->setColumnSearchability("created_at", false);

        $query = $this->repository->model::query();

        $tableTitle = $pageTitle;

        $this->repository->setUrl("edit", "/academic/syllabus_lesson_topic/edit/" . $id . "/")
            ->setUrl("view", "/academic/syllabus_lesson_topic/" . $id . "/");

        $this->repository->setTableTitle($tableTitle)
            ->enableViewData("view", "edit", "export")
            ->disableViewData("add", "list", "delete", "restore");

        $this->repository->unsetColumns("syllabus");
        $query = $query->where(["syllabus_id" => $plan->syllabus_id]);

        $query = $query->with(["syllabus", "module"]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Show the form for editing the specified resource.
     * @param $id
     * @return Application|Factory|View|void
     */
    public function edit($id)
    {
        $model = SyllabusLessonPlan::with(["syllabus", "batch"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = URL::to("/" . request()->path());

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/syllabus_lesson_plan/create");
            $urls["listUrl"] = URL::to("/academic/syllabus_lesson_plan");

            $this->repository->setPageUrls($urls);

            return view('academic::syllabus_lesson_plan.create', compact('formMode', 'formSubmitUrl', 'record'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Update the specified resource in storage.
     * @param $id
     * @return JsonResponse
     * @throws ValidationException
     */
    public function update($id): JsonResponse
    {
        $model = SyllabusLessonPlan::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "syllabus_id" => "required|exists:course_syllabi,syllabus_id",
                "batch_id" => "required|exists:batches,batch_id",
                "name" => "required",
            ], [], ["syllabus_id" => "Syllabus", "name" => "Syllabus Lesson Plan name", "batch_id" => "Batch"]);

            if ($this->repository->isValidData) {

                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] === "success") {

                    if (request()->post("send_for_approval") == "1") {

                        DB::beginTransaction();
                        $model->{$this->repository->approvalField} = 0;
                        $model->save();

                        $update = $this->repository->triggerApprovalProcess($model);

                        if ($update["notify"]["status"] === "success") {

                            DB::commit();

                        } else {
                            DB::rollBack();

                            if (is_array($update["notify"]) && count($update["notify"]) > 0) {

                                foreach ($update["notify"] as $message) {

                                    $response["notify"]["notify"][] = $message;
                                }
                            }
                        }
                    }
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
     * Show the form for editing the specified resource.
     * @param $id
     * @return Application|Factory|View|void
     */
    public function duplicate($id)
    {
        $model = SyllabusLessonPlan::with(["syllabus"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $record["name"] = "";
            $record["batch_id"] = "";

            $formMode = "add";
            $formSubmitUrl = URL::to("/" . request()->path());

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/syllabus_lesson_plan/create");
            $urls["listUrl"] = URL::to("/academic/syllabus_lesson_plan");

            $this->repository->setPageUrls($urls);

            return view('academic::syllabus_lesson_plan.duplicate', compact('formMode', 'formSubmitUrl', 'record'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Update the specified resource in storage.
     * @param $id
     * @return JsonResponse
     * @throws ValidationException
     */
    public function duplicateSubmit($id): JsonResponse
    {
        $oldModel = SyllabusLessonPlan::query()->find($id);

        if ($oldModel) {
            $model = new SyllabusLessonPlan();

            $model = $this->repository->getValidatedData($model, [
                "syllabus_id" => "required|exists:course_syllabi,syllabus_id",
                "batch_id" => "required|exists:batches,batch_id",
                "name" => "required",
            ], [], ["syllabus_id" => "Syllabus", "name" => "Syllabus Lesson Plan name", "batch_id" => "Batch"]);

            if ($this->repository->isValidData) {

                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] === "success") {

                    $this->repository->replicate($oldModel, $model);
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
        $model = SyllabusLessonPlan::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = SyllabusLessonPlan::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Search records
     * @param Request $request
     * @return JsonResponse|void
     */
    public function searchData(Request $request)
    {
        if ($request->expectsJson()) {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $syllabusId = $request->post("syllabus_id");
            $limit = $request->post("limit");

            $query = SyllabusLessonPlan::query()
                ->select("id", "name")
                ->where($this->repository->statusField, "=", "1")
                ->orderBy("name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($syllabusId != "") {
                if (is_array($syllabusId) && count($syllabusId) > 0) {

                    $query = $query->whereIn("syllabus_id", $syllabusId);
                } else {
                    $query = $query->where("syllabus_id", $syllabusId);
                }
            }

            if ($searchText != "") {
                $query = $query->where("name", "LIKE", "%" . $searchText . "%");
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
     * @param $id
     * @return JsonResponse|RedirectResponse|null
     */
    public function changeStatus($id)
    {
        $model = SyllabusLessonPlan::query()->find($id);
        return $this->repository->updateStatus($model, $this->repository->statusField, "", "remarks");
    }

    public function verification($id)
    {
        $model = SyllabusLessonPlan::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @throws ValidationException
     */
    public function verificationSubmit($id)
    {
        $model = SyllabusLessonPlan::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function approval($id)
    {
        $model = SyllabusLessonPlan::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @throws ValidationException
     */
    public function approvalSubmit($id)
    {
        $model = SyllabusLessonPlan::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function approvalHistory($modelHash, $id)
    {
        $model = new SyllabusLessonPlan();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new SyllabusLessonPlan();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
