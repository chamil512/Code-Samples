<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\CourseCategory;
use Modules\Academic\Repositories\CourseCategoryRepository;
use Modules\Accounting\Entities\SegmentsE;


class CourseCategoryController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new CourseCategoryRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Course Categories");

        $this->repository->initDatatable(new CourseCategory());

        $this->repository->setColumns("id", "category_name", "courses", "category_status", "created_at")
            ->setColumnLabel("category_status", "Status")
            ->setColumnDisplay("courses", array($this->repository, 'displayListButtonAs'), ["Courses", URL::to("/academic/course/category/")])
            ->setColumnDisplay("category_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("category_name")
            ->setColumnFilterMethod("category_status", "select", $this->repository->statuses)

            ->setColumnDBField("courses", $this->repository->primaryKey)
            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Course Categories | Trashed")
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Course Categories")
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
        $model = new CourseCategory();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/".request()->path();

        $urls = [];
        $urls["listUrl"]=URL::to("/academic/course_category");

        $this->repository->setPageUrls($urls);
        $segments = SegmentsE::getActiveAllSubSegmentDepartment(null);

        return view('academic::course_category.create', compact('formMode', 'formSubmitUrl', 'record','segments'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     */
    public function store()
    {
        $model = new CourseCategory();

        $model = $this->repository->getValidatedData($model, [
            "category_name" => "required",
            "category_status" => "required|digits:1",
        ]);

        if($this->repository->isValidData)
        {
            //set category_status as 0 when inserting the record
            $model->category_status = 1;

            $response = $this->repository->saveModel($model);
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
        $model = CourseCategory::query()->find($id);

        if($model)
        {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/course_category/create");
            $urls["listUrl"]=URL::to("/academic/course_category");

            $this->repository->setPageUrls($urls);

            return view('academic::course_category.view', compact('record'));
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
        $model = CourseCategory::query()->find($id);

        //dd(get_class($model));

        if($model)
        {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/course_category/create");
            $urls["listUrl"]=URL::to("/academic/course_category");

            $this->repository->setPageUrls($urls);

            return view('academic::course_category.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = CourseCategory::query()->find($id);

        if($model)
        {
            $model = $this->repository->getValidatedData($model, [
                "category_name" => "required",
                "category_status" => "required|digits:1",
            ]);

            if($this->repository->isValidData)
            {
                $response = $this->repository->saveModel($model);
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
        $model = CourseCategory::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = CourseCategory::withTrashed()->find($id);

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
            $limit = $request->post("limit");

            $query = CourseCategory::query()
                ->select("course_category_id", "category_name")
                ->where("category_status", "=", "1")
                ->orderBy("category_name");

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
                $query = $query->where("category_name", "LIKE", "%".$searchText."%");
            }

            if($idNot != "")
            {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("course_category_id", $idNot);
            }

            $data = $query->get();

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
        $model = CourseCategory::query()->find($id);
        return $this->repository->updateStatus($model, "category_status");
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new CourseCategory();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
