<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicCarderPosition;
use Modules\Academic\Repositories\AcademicCarderPositionRepository;

class AcademicCarderPositionController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new AcademicCarderPositionRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Academic Carder Positions");

        $this->repository->initDatatable(new AcademicCarderPosition());

        $this->repository->setColumns("id", "position_name", "position_status", "created_at")
            ->setColumnLabel("position_name", "Position")
            ->setColumnLabel("position_status", "Status")
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnDisplay("position_status", array($this->repository, 'displayStatusActionAs'))

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Academic Carder Positions | Trashed")
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Academic Carder Positions")
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
        $record = new AcademicCarderPosition();

        $formMode = "add";
        $formSubmitUrl = "/".request()->path();

        $urls = [];
        $urls["listUrl"]=URL::to("/academic/academic_carder_position");

        $this->repository->setPageUrls($urls);

        return view('academic::academic_carder_position.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     */
    public function store()
    {
        $model = new AcademicCarderPosition();

        $model = $this->repository->getValidatedData($model, [
            "position_name" => "required",
            "position_status" => "required",
        ], [], ["position_name" => "Position Name", "position_status" => "Position Status"]);

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
        $model = AcademicCarderPosition::find($id);

        if($model)
        {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/academic_carder_position/create");
            $urls["listUrl"]=URL::to("/academic/academic_carder_position");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_carder_position.view', compact('record'));
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
        $model = AcademicCarderPosition::find($id);

        if($model)
        {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/academic_carder_position/create");
            $urls["listUrl"]=URL::to("/academic/academic_carder_position");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_carder_position.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = AcademicCarderPosition::find($id);

        if($model)
        {
            $model = $this->repository->getValidatedData($model, [
                "position_name" => "required",
                "position_status" => "required",
            ], [], ["position_name" => "Position Name", "position_status" => "Position Status"]);

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
        $model = AcademicCarderPosition::find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = AcademicCarderPosition::withTrashed()->find($id);

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

            $query = AcademicCarderPosition::query()
                ->select("id", "position_name")
                ->orderBy("position_name");

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
                $query = $query->where("position_name", "LIKE", "%".$searchText."%");
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
        $model = AcademicCarderPosition::query()->find($id);
        return $this->repository->updateStatus($model, "position_status");
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new AcademicCarderPosition();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
