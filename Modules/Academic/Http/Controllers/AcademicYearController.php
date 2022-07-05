<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicYear;
use Modules\Academic\Repositories\BatchAvailabilityDateRepository;

class AcademicYearController extends Controller
{
    private BatchAvailabilityDateRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new BatchAvailabilityDateRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Academic Years");

        $this->repository->initDatatable(new AcademicYear());

        $this->repository->setColumns("id", "year_name", "year_no", "aca_year_status", "created_at")
            ->setColumnLabel("year_name", "Year")
            ->setColumnLabel("aca_year_status", "Status")
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnDisplay("aca_year_status", array($this->repository, 'displayStatusActionAs'))

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Academic Years | Trashed")
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Academic Years")
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
        $model = new AcademicYear();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/".request()->path();

        $urls = [];
        $urls["listUrl"]=URL::to("/academic/academic_year");

        $this->repository->setPageUrls($urls);

        return view('academic::academic_year.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store()
    {
        $model = new AcademicYear();

        $model = $this->repository->getValidatedData($model, [
            "year_name" => "required",
            "year_no" => "required",
            "aca_year_status" => "required|digits:1",
        ], [], ["year_name" => "Year Name", "aca_year_status" => "Year Status"]);

        if($this->repository->isValidData)
        {
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
        $model = AcademicYear::query()->find($id);

        if($model)
        {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/academic_year/create");
            $urls["listUrl"]=URL::to("/academic/academic_year");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_year.view', compact('record'));
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
        $model = AcademicYear::query()->find($id);

        if($model)
        {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/academic_year/create");
            $urls["listUrl"]=URL::to("/academic/academic_year");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_year.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = AcademicYear::query()->find($id);

        if($model)
        {
            $model = $this->repository->getValidatedData($model, [
                "year_name" => "required",
                "year_no" => "required",
                "aca_year_status" => "required|digits:1",
            ], [], ["year_name" => "Year Name", "aca_year_status" => "Year Status"]);

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
        $model = AcademicYear::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = AcademicYear::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Search records
     * @param Request $request
     * @return JsonResponse
     */
    public function searchData(Request $request): JsonResponse
    {
        if($request->expectsJson())
        {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $limit = $request->post("limit");
            $courseId = $request->post("course_id");

            $query = AcademicYear::query()
                ->select("academic_year_id", "year_name")
                ->orderBy("year_no");

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
                $query->where("year_name", "LIKE", "%".$searchText."%");
            }

            if($courseId !== null)
            {
                if (is_array($courseId) && count($courseId) > 0) {

                    $courseIds = $courseId;
                } else {

                    $courseIds = [];
                    $courseIds[] = $courseId;
                }

                $query->whereHas("courseModules", function ($query) use($courseIds) {

                    $query->whereIn("course_id", $courseIds);
                });
            }

            if($idNot != "")
            {
                $idNot = json_decode($idNot, true);
                $query->whereNotIn("academic_year_id", $idNot);
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
        $model = AcademicYear::query()->find($id);
        return $this->repository->updateStatus($model, "aca_year_status");
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new AcademicYear();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
