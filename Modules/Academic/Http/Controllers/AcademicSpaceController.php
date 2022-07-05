<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicSpace;
use Modules\Academic\Repositories\AcademicSpaceRepository;

class AcademicSpaceController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new AcademicSpaceRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Academic Spaces");

        $this->repository->initDatatable(new AcademicSpace());

        $this->repository->setColumns("id", "space", "created_at")
            ->setColumnDisplay("space", array($this->repository, 'displayRelationAs'), ["space", "id", "name"])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false)

            ->setColumnDBField("space", "space_id")
            ->setColumnFKeyField("space", "space_id")
            ->setColumnRelation("space", "space", "space_id");

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Academic Spaces | Trashed")
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Academic Spaces")
                ->enableViewData("trashList", "trash", "export");
        }

        $query = $query->with(["space"]);

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
        $model = new AcademicSpace();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/".request()->path();

        $urls = [];
        $urls["listUrl"]=URL::to("/academic/academic_space");

        $this->repository->setPageUrls($urls);

        return view('academic::academic_space.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     */
    public function store()
    {
        $model = new AcademicSpace();

        $model = $this->repository->getValidatedData($model, [
            "space_id" => "required|exists:spaces_assign,id|unique:Modules\Academic\Entities\AcademicSpace,space_id",
        ], [], ["space_id" => "Space"]);

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
        $model = AcademicSpace::find($id);

        if($model)
        {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/academic_space/create");
            $urls["listUrl"]=URL::to("/academic/academic_space");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_space.view', compact('record'));
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
        $model = AcademicSpace::with(["space"])->find($id);

        if($model)
        {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/academic_space/create");
            $urls["listUrl"]=URL::to("/academic/academic_space");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_space.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = AcademicSpace::find($id);

        if($model)
        {
            $model = $this->repository->getValidatedData($model, [
                "space_id" => "required|exists:spaces_assign,id|unique:Modules\Academic\Entities\AcademicSpace,space_id,".$id,
            ], [], ["space_id" => "Space"]);

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
        $model = AcademicSpace::find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = AcademicSpace::withTrashed()->find($id);

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

            $academicSpaces = AcademicSpace::query()->select("space_id")->get()->keyBy("space_id")->toArray();
            $academicSpaceIds = array_keys($academicSpaces);

            $query = DB::table("spaces_assign", "space")
                ->select("space.id", DB::raw("CONCAT(space_name.name, ' [', space_type.type_name, ']', ' [', space.std_count, ' Max]') AS name"), "space.std_count AS capacity")
                ->join("space_categoryname AS space_name", "space.cn_id", "=", "space_name.id")
                ->join("space_categorytypes AS space_type", "space.type_id", "=", "space_type.id")
                ->whereIn("space.id", $academicSpaceIds)
                ->whereNull("space.deleted_at")
                ->whereNull("space_name.deleted_at")
                ->whereNull("space_type.deleted_at");

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
                $query = $query->where(DB::raw("CONCAT(space_name.name, ' [', space_type.type_name, ']')"), "LIKE", "%".$searchText."%");
            }

            if($idNot != "")
            {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("space.id", $idNot);
            }

            $data = $query->get();

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }

    /**
     * Search records
     * @param Request $request
     * @return JsonResponse
     */
    public function searchSpaces(Request $request)
    {
        if($request->expectsJson())
        {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $limit = $request->post("limit");

            $query = DB::table("spaces_assign", "space")
                ->select("space.id", DB::raw("CONCAT(space_name.name, ' [', space_type.type_name, ']') AS name"), "space.std_count AS capacity")
                ->join("space_categoryname AS space_name", "space.cn_id", "=", "space_name.id")
                ->join("space_categorytypes AS space_type", "space.type_id", "=", "space_type.id")
                ->whereNull("space.deleted_at")
                ->whereNull("space_name.deleted_at")
                ->whereNull("space_type.deleted_at");

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
                $query = $query->where(DB::raw("CONCAT(space_name.name, ' [', space_type.type_name, ']')"), "LIKE", "%".$searchText."%");
            }

            if($idNot != "")
            {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("space.id", $idNot);
            }

            $data = $query->get();

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
        $options["suffix"] = "Academic Space";

        $model = new AcademicSpace();
        return $this->repository->recordHistory($model, $modelHash, $id, $options);
    }
}
