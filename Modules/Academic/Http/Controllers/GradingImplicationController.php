<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\GradingImplication;
use Modules\Academic\Repositories\GradingImplicationRepository;

class GradingImplicationController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new GradingImplicationRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Grading Implications");

        $this->repository->initDatatable(new GradingImplication());

        $this->repository->setColumns("id", "implication_name", "grade", "min_marks", "max_marks", "points", "imp_status", "created_at")
            ->setColumnLabel("implication_name", "Grading Implication")
            ->setColumnLabel("imp_status", "Status")
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnDisplay("imp_status", array($this->repository, 'displayStatusActionAs'))

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Grading Implications | Trashed")
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Grading Implications")
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
        $model = new GradingImplication();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/".request()->path();

        $urls = [];
        $urls["listUrl"]=URL::to("/academic/grading_implication");

        $this->repository->setPageUrls($urls);

        return view('academic::grading_implication.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     */
    public function store()
    {
        $model = new GradingImplication();

        $model = $this->repository->getValidatedData($model, [
            "implication_name" => "required",
            "grade" => "required",
            "min_marks" => "required",
            "max_marks" => "required",
            "points" => "required",
            "imp_status" => "required|digits:1",
        ], [], ["implication_name" => "Grading Implication", "imp_status" => "Semester Status"]);

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
        $model = GradingImplication::find($id);

        if($model)
        {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/grading_implication/create");
            $urls["listUrl"]=URL::to("/academic/grading_implication");

            $this->repository->setPageUrls($urls);

            return view('academic::grading_implication.view', compact('record'));
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
        $model = GradingImplication::find($id);

        if($model)
        {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/grading_implication/create");
            $urls["listUrl"]=URL::to("/academic/grading_implication");

            $this->repository->setPageUrls($urls);

            return view('academic::grading_implication.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = GradingImplication::find($id);

        if($model)
        {
            $model = $this->repository->getValidatedData($model, [
                "implication_name" => "required",
                "grade" => "required",
                "min_marks" => "required",
                "max_marks" => "required",
                "points" => "required",
                "imp_status" => "required|digits:1",
            ], [], ["implication_name" => "Grading Implication", "imp_status" => "Semester Status"]);

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
        $model = GradingImplication::find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = GradingImplication::withTrashed()->find($id);

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

            $query = GradingImplication::query()
                ->select("id", "implication_name")
                ->orderBy("implication_name");

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
                $query = $query->where("implication_name", "LIKE", "%".$searchText."%");
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
        $model = GradingImplication::query()->find($id);
        return $this->repository->updateStatus($model, "imp_status");
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new GradingImplication();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
