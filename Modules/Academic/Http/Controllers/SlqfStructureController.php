<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\SlqfStructure;
use Modules\Academic\Repositories\SlqfStructureRepository;
use Modules\Academic\Repositories\SlqfVersionRepository;

class SlqfStructureController extends Controller
{
    private SlqfStructureRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new SlqfStructureRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Slqf Structures");

        $this->repository->initDatatable(new SlqfStructure());

        $this->repository->setColumns("id", "slqf_name", "slqf_code", "versions", "slqf_status", "created_at")
            ->setColumnLabel("slqf_name", "SLQF Structure")
            ->setColumnLabel("slqf_code", "Code")
            ->setColumnLabel("slqf_status", "Status")
            ->setColumnDisplay("versions", array($this->repository, 'display_versions_as'))
            ->setColumnDisplay("slqf_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses, "", "", true])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnFilterMethod("slqf_name")
            ->setColumnFilterMethod("slqf_status", "select", $this->repository->statuses)
            ->setColumnDBField("versions", $this->repository->primaryKey)
            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Slqf Structures | Trashed")
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Slqf Structures")
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
        $model = new SlqfStructure();
        $record = $model;

        $formMode = "add";
        $formType = "slqf";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/slqf_structure");

        $this->repository->setPageUrls($urls);

        return view('academic::slqf_structure.create', compact('formMode', 'formSubmitUrl', 'record', 'formType'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store()
    {
        $model = new SlqfStructure();

        $model = $this->repository->getValidatedData($model, [
            "slqf_name" => "required",
            "remarks" => "",
        ]);

        if ($this->repository->isValidData) {
            $model->slqf_code = $this->repository->generateSlqfCode();

            $response = $this->repository->saveModel($model);

            if ($response["notify"]["status"] == "success") {
                $slqf_id = $model->slqf_id;

                //add slqf structure
                $slqfVersionRepo = new SlqfVersionRepository();
                $addVersion = $slqfVersionRepo->addSlqfVersion($slqf_id);

                if ($addVersion) {
                    if (request()->post("send_for_approval") == "1") {
                        $response = $slqfVersionRepo->startApprovalProcess($addVersion, 0, $response);
                    }
                } else {
                    $response["notify"]["status"] = "warning";
                    $response["notify"]["notify"][] = "SLQF Structure Added successfully.";
                    $response["notify"]["notify"][] = "SLQF Version Details Saving Was Failed.";
                    $response["notify"]["notify"][] = "Try Adding SLQF Version Details To Added SLQF Structure.";
                }
            }
        } else {
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
        $model = SlqfStructure::query()->find($id);

        if ($model) {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/slqf_structure/create");
            $urls["listUrl"] = URL::to("/academic/slqf_structure");

            $this->repository->setPageUrls($urls);

            return view('academic::slqf_structure.view', compact('record'));
        } else {
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
        $model = SlqfStructure::query()->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formType = "slqf";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/slqf_structure/create");
            $urls["listUrl"] = URL::to("/academic/slqf_structure");

            $this->repository->setPageUrls($urls);

            return view('academic::slqf_structure.create', compact('formMode', 'formSubmitUrl', 'record', 'formType'));
        } else {
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
        $model = SlqfStructure::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "slqf_name" => "required",
                "remarks" => "",
            ]);

            if ($this->repository->isValidData) {
                $response = $this->repository->saveModel($model);
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
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = SlqfStructure::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = SlqfStructure::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Search records
     * @param Request $request
     * @return JsonResponse
     */
    public function searchData(Request $request): JsonResponse
    {
        if ($request->expectsJson()) {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $limit = $request->post("limit");

            $query = SlqfStructure::query()
                ->select("slqf_id", "slqf_name", "slqf_code")
                ->where("slqf_status", "=", "1")
                ->orderBy("slqf_name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($searchText != "") {
                $query = $query->where("slqf_name", "LIKE", "%" . $searchText . "%");
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("slqf_id", $idNot);
            }

            $data = $query->get();

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return JsonResponse
     */
    public function changeStatus($id): JsonResponse
    {
        $model = SlqfStructure::query()->find($id);
        return $this->repository->updateStatus($model, $this->repository->statusField, "", "remarks");
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new SlqfStructure();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
