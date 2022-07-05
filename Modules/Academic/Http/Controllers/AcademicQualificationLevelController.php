<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicQualificationLevel;
use Modules\Academic\Repositories\AcademicQualificationLevelRepository;

class AcademicQualificationLevelController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new AcademicQualificationLevelRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Academic Qualification Levels");

        $this->repository->initDatatable(new AcademicQualificationLevel());

        $this->repository->setColumns("id", "qualification_level", "level_status", "created_at")
            ->setColumnLabel("level_status", "Status")
            ->setColumnDisplay("level_status", array($this->repository, 'displayStatusActionAs'))
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("level_status", "select", [["id" =>"1", "name" =>"Enabled"], ["id" =>"0", "name" =>"Disabled"]])

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Academic Qualification Levels | Trashed")
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Academic Qualification Levels")
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
        $model = new AcademicQualificationLevel();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/".request()->path();

        $urls = [];
        $urls["listUrl"]=URL::to("/academic/academic_qualification_level");

        $this->repository->setPageUrls($urls);

        return view('academic::academic_qualification_level.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     */
    public function store()
    {
        $model = new AcademicQualificationLevel();

        $model = $this->repository->getValidatedData($model, [
            "qualification_level" => "required",
            "level_status" => "required|digits:1",
        ]);

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
        $model = AcademicQualificationLevel::query()->find($id);

        if($model)
        {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/academic_qualification_level/create");
            $urls["listUrl"]=URL::to("/academic/academic_qualification_level");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_qualification_level.view', compact('record'));
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
        $model = AcademicQualificationLevel::query()->find($id);

        if($model)
        {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/academic_qualification_level/create");
            $urls["listUrl"]=URL::to("/academic/academic_qualification_level");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_qualification_level.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = AcademicQualificationLevel::query()->find($id);

        if($model)
        {
            $model = $this->repository->getValidatedData($model, [
                "qualification_level" => "required",
                "level_status" => "required|digits:1",
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
        $model = AcademicQualificationLevel::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = AcademicQualificationLevel::withTrashed()->find($id);

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

            $query = AcademicQualificationLevel::query()
                ->select("id", "qualification_level")
                ->where("level_status", "=", "1")
                ->orderBy("qualification_level");

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
                $query = $query->where("qualification_level", "LIKE", "%".$searchText."%");
            }

            if($idNot != "")
            {
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
     * @param int $id
     * @return mixed
     */
    public function changeStatus($id)
    {
        $model = AcademicQualificationLevel::query()->find($id);
        return $this->repository->updateStatus($model, "level_status");
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new AcademicQualificationLevel();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
