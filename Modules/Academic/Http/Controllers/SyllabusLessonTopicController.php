<?php

namespace Modules\Academic\Http\Controllers;

use App\Helpers\Helper;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\SyllabusLessonTopic;
use Modules\Academic\Entities\SyllabusLessonPlan;
use Modules\Academic\Entities\SyllabusModule;
use Modules\Academic\Repositories\SyllabusLessonTopicRepository;

class SyllabusLessonTopicController extends Controller
{
    private SyllabusLessonTopicRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new SyllabusLessonTopicRepository();
    }

    /**
     * @param $planId
     * @param $syllabusModuleId
     * @return mixed
     */
    public function index($planId, $syllabusModuleId)
    {
        $plan = SyllabusLessonPlan::query()->find($planId);
        $moduleId = null;

        $planTitle = "";
        if ($plan) {
            $planTitle = $plan["name"];

            $sm = SyllabusModule::with(["module"])->find($syllabusModuleId);

            if ($sm && $sm["syllabus_id"] === $plan->syllabus_id) {

                $moduleId = $sm["module_id"];
                $planTitle .= " | " . $sm["module"]["name"] . " Module";
            } else {
                abort(404, "Lesson Plan not available");
            }
        } else {
            abort(404, "Lesson Plan not available");
        }

        $pageTitle = $planTitle . " | Topics";

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new SyllabusLessonTopic());

        $this->repository->setColumns("id", "lesson_order", "name", "delivery_mode", "lecturer", "hours", "created_at")
            ->setColumnLabel("name", "Topic")
            ->setColumnDBField("lecturer", "lecturer_id")
            ->setColumnFKeyField("lecturer", "lecturer_id")
            ->setColumnRelation("lecturer", "lecturer", "name_with_init")
            ->setColumnDBField("delivery_mode", "delivery_mode_id")
            ->setColumnFKeyField("delivery_mode", "delivery_mode_id")
            ->setColumnRelation("delivery_mode", "delivery_mode", "name_with_init")
            ->setColumnDisplay("delivery_mode", array($this->repository, 'displayRelationAs'), ["delivery_mode", "delivery_mode_id", "name"])
            ->setColumnDisplay("lecturer", array($this->repository, 'displayRelationAs'), ["lecturer", "lecturer_id", "name", URL::to("/academic/lecturer/view/")])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnFilterMethod("lecturer", "select", URL::to("/academic/lecturer/search_data"))
            ->setColumnFilterMethod("delivery_mode", "select", URL::to("/academic/module_delivery_mode/search_data"))
            ->setColumnSearchability("created_at", false);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = $planTitle . " | Topics | Trashed";
            $this->repository->setUrl("list", "/academic/syllabus_lesson_topic/" . $planId . "/" . $moduleId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("add", "view", "edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = $planTitle . " | Topics";
            $this->repository->setCustomControllerUrl("/academic/syllabus_lesson_topic", ["list"], false)
                ->setUrl("trashList", "/academic/syllabus_lesson_topic/trash/" . $planId . "/" . $moduleId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export")
                ->disableViewData("add");
        }

        $query->where(["syllabus_lesson_plan_id" => $planId, "module_id" => $moduleId]);
        $query->with(["lecturer", "deliveryMode"]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * @param $planId
     * @param $moduleId
     * @return Factory|View
     */
    public function trash($planId, $moduleId)
    {
        $this->trash = true;
        return $this->index($planId, $moduleId);
    }

    /**
     * @param $planId
     * @param $syllabusModuleId
     * @return Application|Factory|View
     */
    public function edit($planId, $syllabusModuleId)
    {
        $plan = SyllabusLessonPlan::query()->find($planId);
        $moduleId = null;

        $planTitle = "";
        if ($plan) {
            $planTitle = $plan["name"];

            $sm = SyllabusModule::with(["module"])->find($syllabusModuleId);

            if ($sm && $sm["syllabus_id"] === $plan->syllabus_id) {

                $moduleId = $sm["module_id"];
                $planTitle .= " | " . $sm["module"]["name"] . " Module";
            } else {
                abort(404, "Lesson Plan not available");
            }
        } else {
            abort(404, "Lesson Plan not available");
        }

        $pageTitle = $planTitle . " | Edit Topics";
        $this->repository->setPageTitle($pageTitle);

        $formMode = "add";
        $formSubmitUrl = URL::to("/" . request()->path());

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/syllabus_lesson_topic/" . $planId . "/" . $moduleId);

        $this->repository->setPageUrls($urls);

        $records = $this->repository->getRecords($plan, $moduleId);

        $lecturerFetchUrl = URL::to("/academic/lecturer/search_data/");

        return view('academic::syllabus_lesson_topic.create', compact('formMode', 'formSubmitUrl', 'records', 'moduleId', 'lecturerFetchUrl'));
    }

    /**
     * Store a newly created resource in storage.
     * @param $planId
     * @param $syllabusModuleId
     * @return JsonResponse
     */
    public function update($planId, $syllabusModuleId): JsonResponse
    {
        $plan = SyllabusLessonPlan::query()->find($planId);
        $moduleId = null;

        if ($plan) {
            $sm = SyllabusModule::with(["module"])->find($syllabusModuleId);

            if ($sm && $sm["syllabus_id"] === $plan->syllabus_id) {

                $moduleId = $sm["module_id"];
            } else {
                abort(404, "Lesson Plan not available");
            }
        } else {
            abort(404, "Lesson Plan not available");
        }

        $response = $this->repository->update($planId, $moduleId);

        if ($response["notify"]["status"] === "success") {

            $response["data"] = $this->repository->getRecords($plan, $moduleId);
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function searchData(Request $request): JsonResponse
    {
        if ($request->expectsJson()) {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $planId = $request->post("plan_id");
            $moduleId = $request->post("module_id");
            $deliveryModeId = $request->post("delivery_mode_id");
            $withHours = $request->post("with_hours");
            $limit = $request->post("limit");

            $query = SyllabusLessonTopic::query()
                ->select("id", "name", "hours")
                ->orderBy("name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($planId !== null) {
                if (is_array($planId) && count($planId) > 0) {

                    $query = $query->whereIn("syllabus_lesson_plan_id", $planId);
                } else {
                    $query = $query->where("syllabus_lesson_plan_id", $planId);
                }
            }

            if ($moduleId !== null) {
                if (is_array($moduleId) && count($moduleId) > 0) {

                    $query = $query->whereIn("module_id", $moduleId);
                } else {
                    $query = $query->where("module_id", $moduleId);
                }
            }

            if ($deliveryModeId !== null) {
                if (is_array($deliveryModeId) && count($deliveryModeId) > 0) {

                    $query = $query->whereIn("delivery_mode_id", $deliveryModeId);
                } else {
                    $query = $query->where("delivery_mode_id", $deliveryModeId);
                }
            }

            if ($searchText != "") {
                $query = $query->where("name", "LIKE", "%" . $searchText . "%");
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("id", $idNot);
            }

            $results = $query->get()->toArray();

            $data = [];
            if ($results) {

                foreach ($results as $result) {

                    if ($withHours !== "Y") {

                        $result["name"] = $result["name_with_hours"];
                    }

                    $data[] = $result;
                }
            }

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $options = [];
        $options["title"] = "Lesson Topic";

        $model = new SyllabusLessonTopic();
        return $this->repository->recordHistory($model, $modelHash, $id, $options);
    }
}
