<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\Lecturer;
use Modules\Academic\Entities\LecturerCourse;
use Modules\Academic\Repositories\LecturerCourseModuleRepository;
use Modules\Academic\Repositories\LecturerCourseRepository;

class LecturerCourseController extends Controller
{
    private LecturerCourseRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new LecturerCourseRepository();
    }

    /**
     * Display a listing of the resource.
     * @param int $lecturerId
     * @return Factory|View
     */
    public function index($lecturerId)
    {
        $lecturer = Lecturer::query()->find($lecturerId);

        $lecTitle = "";
        if($lecturer)
        {
            $lecTitle = $lecturer["name_with_init"];
        }
        else
        {
            abort(404, "Lecturer not available");
        }
        $pageTitle = $lecTitle." | Lecturer Courses";

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new LecturerCourse());

        $this->repository->setColumns("id", "course", "modules", "created_at")
            ->setColumnDBField("course", "course_id")
            ->setColumnFKeyField("course", "course_id")
            ->setColumnRelation("course", "course", "course_name")

            ->setColumnDBField("modules", "lecturer_course_id")
            ->setColumnFKeyField("modules", "module_id")
            ->setColumnRelation("modules", "modules", "module_name")

            ->setColumnDisplay("course", array($this->repository, 'displayRelationAs'), ["course", "course_id", "course_name", URL::to("/academic/course/view/")])
            ->setColumnDisplay("modules", array($this->repository, 'displayRelationManyAs'), ["modules", "course_module", "module_id", "name_year_semester"])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("course", "select", URL::to("/academic/course/search_data"))
            ->setColumnFilterMethod("modules", "select", URL::to("/academic/course_module/search_data"))

            ->setColumnSearchability("modules", false)
            ->setColumnSearchability("created_at", false);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = $lecTitle." | Lecturer Courses | Trashed";
            $this->repository->setUrl("list", "/academic/lecturer_course/".$lecturerId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $tableTitle = $lecTitle." | Lecturer Courses";
            $this->repository->setCustomControllerUrl("/academic/lecturer_course", ["list"], false)
                ->setUrl("trashList", "/academic/lecturer_course/trash/".$lecturerId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export");
        }

        $this->repository->setUrl("add", "/academic/lecturer_course/create/".$lecturerId);

        $query = $query->where(["lecturer_id" => $lecturerId]);
        $query = $query->with(["course", "modules", "modules.courseModule"]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param int $lecturerId
     * @return Factory|View
     */
    public function trash($lecturerId)
    {
        $this->trash = true;
        return $this->index($lecturerId);
    }

    /**
     * Show the form for creating a new resource.
     * @param mixed $lecturerId
     * @return Factory|View
     */
    public function create($lecturerId)
    {
        $lecturer = Lecturer::query()->find($lecturerId);

        if(!$lecturer)
        {
            abort(404, "Lecturer not available");
        }

        $model = new LecturerCourse();
        $model->lecturer = $lecturer;

        $lecturerCourses = $model->lecturer->courses()->select("course_id")->get()->keyBy("course_id")->toArray();
        $currentCourseIds = array_keys($lecturerCourses);

        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/".request()->path();

        $urls = [];
        $urls["listUrl"]=URL::to("/academic/lecturer_course/".$lecturerId);

        $this->repository->setPageUrls($urls);

        return view('academic::lecturer_course.create', compact('formMode', 'formSubmitUrl', 'record', 'currentCourseIds'));
    }

    /**
     * Store a newly created resource in storage.
     * @param $lecturerId
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store($lecturerId): JsonResponse
    {
        $lecturer = Lecturer::query()->find($lecturerId);

        if(!$lecturer)
        {
            abort(404, "Lecturer not available");
        }

        $model = new LecturerCourse();

        $model = $this->repository->getValidatedData($model, [
            "course_id" => "required|exists:courses,course_id",
        ], [], ["course_id" => "Course"]);

        if($this->repository->isValidData)
        {
            $model->lecturer_id = $lecturerId;

            $response = $this->repository->saveModel($model);

            if($response["notify"]["status"] == "success")
            {
                $lCMRepo = new LecturerCourseModuleRepository();
                $lCMRepo->update($model);
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
        $model = LecturerCourse::query()->find($id);

        if($model)
        {
            $lecturerId = $model->lecturer_id;
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/lecturer_course/create/".$lecturerId);
            $urls["listUrl"]=URL::to("/academic/lecturer_course/".$lecturerId);

            $this->repository->setPageUrls($urls);

            return view('academic::lecturer_course.view', compact('record'));
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
        $model = LecturerCourse::with(["modules", "lecturer", "course"])->find($id);

        if($model)
        {
            $lecturerId = $model->lecturer_id;

            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $modules = [];
            if(count($record["modules"])>0)
            {
                foreach ($record["modules"] as $courseModule)
                {
                    $modules[]=$courseModule["course_module"];
                }
                $record["modules"]=$modules;
            }

            //get current course ids without this
            $lecturerCourses = $model->lecturer->courses()->select("course_id")->whereNotIn("course_id", [$model->course_id])->get()->keyBy("course_id")->toArray();
            $currentCourseIds = array_keys($lecturerCourses);

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/lecturer_course/create/".$lecturerId);
            $urls["listUrl"]=URL::to("/academic/lecturer_course/".$lecturerId);

            $this->repository->setPageUrls($urls);

            return view('academic::lecturer_course.create', compact('formMode', 'formSubmitUrl', 'record', 'currentCourseIds'));
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
        $model = LecturerCourse::query()->find($id);

        if($model)
        {
            $model = $this->repository->getValidatedData($model, [
                "lecturer_id" => "required|exists:people,id",
                "course_id" => "required|exists:courses,course_id",
            ], [], ["lecturer_id" => "Lecturer", "course_id" => "Course"]);

            if($this->repository->isValidData)
            {
                $response = $this->repository->saveModel($model);

                if($response["notify"]["status"] == "success")
                {
                    $lCMRepo = new LecturerCourseModuleRepository();
                    $lCMRepo->update($model);
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
        $model = LecturerCourse::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = LecturerCourse::withTrashed()->find($id);

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
        $options["title"] = "Lecturer Course";

        $model = new LecturerCourse();
        return $this->repository->recordHistory($model, $modelHash, $id, $options);
    }
}
