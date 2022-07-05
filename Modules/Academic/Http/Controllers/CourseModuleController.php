<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\CourseModule;
use Modules\Academic\Entities\Course;
use Modules\Academic\Repositories\CourseModuleRepository;
use Modules\Academic\Repositories\SimilarCourseModuleRepository;

class CourseModuleController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new CourseModuleRepository();
    }

    /**
     * Display a listing of the resource.
     * @param int $courseId
     * @return Factory|View
     */
    public function index($courseId)
    {
        $course = Course::query()->find($courseId);

        $ccTitle = "";
        if($course)
        {
            $ccTitle = $course["course_name"];
        }
        else
        {
            abort(404, "Course not available");
        }

        $pageTitle = $ccTitle." | Course Modules";

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new CourseModule());

        $this->repository->setColumns("id", "module_name", "module_code", "academic_year", "semester", "module_status", "created_at")
            ->setColumnLabel("module_code", "Code")
            ->setColumnLabel("module_name", "Course Module")
            ->setColumnLabel("academic_year", "Academic Year")
            ->setColumnLabel("module_status", "Status")

            ->setColumnDBField("academic_year", "academic_year_id")
            ->setColumnFKeyField("academic_year", "academic_year_id")
            ->setColumnRelation("academic_year", "academicYear", "year_name")

            ->setColumnDBField("semester", "semester_id")
            ->setColumnFKeyField("semester", "semester_id")
            ->setColumnRelation("semester", "semester", "semester_name")

            ->setColumnDisplay("academic_year", array($this->repository, 'displayRelationAs'), ["academic_year", "academic_year_id", "year_name"])
            ->setColumnDisplay("semester", array($this->repository, 'displayRelationAs'), ["semester", "semester_id", "semester_name"])
            ->setColumnDisplay("module_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("module_name")
            ->setColumnFilterMethod("module_status", "select", $this->repository->statuses)
            ->setColumnFilterMethod("academic_year", "select", URL::to("/academic/academic_year/search_data"))
            ->setColumnFilterMethod("semester", "select", URL::to("/academic/academic_semester/search_data"))

            ->setColumnSearchability("created_at", false);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = $ccTitle." | Course Modules | Trashed";

            $this->repository->setUrl("list", "/academic/course_module/".$courseId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();
            $tableTitle = $ccTitle." | Course Modules";

            $this->repository->setCustomControllerUrl("/academic/course_module", ["list"], false)
                ->setUrl("trashList", "/academic/course_module/trash/".$courseId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export");
        }

        $this->repository->setUrl("add", "/academic/course_module/create/".$courseId);
        $query = $query->where(["course_id" => $courseId]);
        $query = $query->with(["academicYear", "semester"]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param int $courseId
     * @return Factory|View
     */
    public function trash($courseId)
    {
        $this->trash = true;
        return $this->index($courseId);
    }

    /**
     * Show the form for creating a new resource.
     * @param int $courseId
     * @return Factory|View
     */
    public function create($courseId)
    {
        $course = Course::query()->find($courseId);
        if(!$course)
        {
            abort(404, "Course not available");
        }

        $model = new CourseModule();
        $model->course = $course;
        $model->same_modules = [];
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/".request()->path();

        $urls = [];
        $urls["listUrl"]=URL::to("/academic/course_module/".$courseId);

        $this->repository->setPageUrls($urls);

        return view('academic::course_module.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @param int $courseId
     * @return JsonResponse
     */
    public function store($courseId)
    {
        $course = Course::query()->find($courseId);
        if(!$course)
        {
            abort(404, "Course not available");
        }

        $model = new CourseModule();

        $model = $this->repository->getValidatedData($model, [
            "academic_year_id" => "required|exists:academic_years,academic_year_id",
            "semester_id" => "required|exists:academic_semesters,semester_id",
            "module_name" => "required",
            "module_code" => "required|min:2",
            "module_color_code" => "required|min:4",
            "module_order" => "required",
        ], [], ["course_id" => "Course", "academic_year_id" => "Academic Year", "semester_id" => "Academic Semester", "module_name" => "Module name", "module_code" => "Module Code", "module_color_code" => "Module Color Code", "module_order" => "Module Order"]);

        if($this->repository->isValidData)
        {
            $model->course_id = $courseId;

            //set course_status as 0 when inserting the record
            //$model->module_status = 0;
            $model->module_status = 1; //Until approval process is implemented status will be saved as 1

            $response = $this->repository->saveModel($model);

            if ($response["notify"]["status"] === "success") {

                $sMRepo = new SimilarCourseModuleRepository();
                $sMRepo->update($model);
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
        $model = CourseModule::find($id);

        if($model)
        {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/course_module/create/".$model->course_id);
            $urls["listUrl"]=URL::to("/academic/course_module/".$model->course_id);

            $this->repository->setPageUrls($urls);

            return view('academic::course_module.view', compact('record'));
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
        $model = CourseModule::with(["course", "academicYear", "semester", "similarModules"])->find($id);

        if($model)
        {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/course_module/create/".$model->course_id);
            $urls["listUrl"]=URL::to("/academic/course_module/".$model->course_id);

            $this->repository->setPageUrls($urls);

            $record["same_modules"] = $this->repository->getSimilarModules($model);

            return view('academic::course_module.create', compact('formMode', 'formSubmitUrl', 'record'));
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
     */
    public function update($id)
    {
        $model = CourseModule::find($id);

        if($model)
        {
            $model = $this->repository->getValidatedData($model, [
                "academic_year_id" => "required|exists:academic_years,academic_year_id",
                "semester_id" => "required|exists:academic_semesters,semester_id",
                "module_name" => "required",
                "module_code" => "required|min:2",
                "module_color_code" => "required|min:4",
                "module_order" => "required",
            ], [], ["course_id" => "Course", "academic_year_id" => "Academic Year", "semester_id" => "Academic Semester", "module_name" => "Module name", "module_code" => "Module Code", "module_color_code" => "Module Color Code", "module_order" => "Module Order"]);

            if($this->repository->isValidData)
            {
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] === "success") {

                    $sMRepo = new SimilarCourseModuleRepository();
                    $sMRepo->update($model);
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
        $model = CourseModule::find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = CourseModule::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Search records
     * @param Request $request
     * @return JsonResponse
     */
    public function searchData(Request $request)
    {
        if($request->expectsJson())
        {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $courseId = $request->post("course_id");
            $scrutinyBoardId = $request->post("scrutiny_board_id");
            $withCourse = $request->post("with_course");
            $limit = $request->post("limit");

            if ($courseId === null) {
                $courseId = $request->get("course_id");
            }

            $query = CourseModule::query();
            if ($withCourse) {

                $query->with(["course"]);
            }

            $query->select("module_id", "module_name", "module_code", "academic_year_id", "semester_id", "course_id")
                ->where("module_status", "=", "1")
                ->orderBy("module_name");

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
                $query->where(function ($query) use($searchText){

                    $query->where("module_name", "LIKE", "%".$searchText."%")->orWhere("module_code", "LIKE", "%".$searchText."%");
                });
            }

            if($courseId !== null)
            {
                if (is_array($courseId) && count($courseId) > 0) {

                    $query = $query->whereIn("course_id", $courseId);
                } else {
                    $query = $query->where("course_id", $courseId);
                }
            }

            if($scrutinyBoardId !== null) {

                $query->whereHas("scrutinyBoards", function ($query) use($scrutinyBoardId) {

                    if (is_array($scrutinyBoardId) && count($scrutinyBoardId) > 0) {

                        $query->whereIn("scrutiny_board_id", $scrutinyBoardId);
                    } else {

                        $query->where("scrutiny_board_id", $scrutinyBoardId);
                    }
                });
            }

            if($idNot != "")
            {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("module_id", $idNot);
            }

            $data = [];
            if ($withCourse) {

                $records = $query->get()->toArray();

                if (count($records) > 0) {

                    foreach ($records as $module) {

                        $courseName = "";
                        if (isset($module["course"]["name"])) {

                            $courseName = $module["course"]["name"];
                        }

                        $id = $module["id"];
                        $name = $module["name"] . " [" . $courseName . "]";
                        $data[] = ["id" => $id, "name" => $name];
                    }
                }
            } else {

                $data = $query->get();
            }

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return mixed
     */
    public function changeStatus($id)
    {
        $model = CourseModule::query()->find($id);
        return $this->repository->updateStatus($model, "module_status");
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new CourseModule();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
