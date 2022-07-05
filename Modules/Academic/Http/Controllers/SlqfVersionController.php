<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\SlqfStructure;
use Modules\Academic\Entities\SlqfVersion;
use Modules\Academic\Repositories\SlqfVersionRepository;

class SlqfVersionController extends Controller
{
    private SlqfVersionRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new SlqfVersionRepository();
    }

    /**
     * Display a listing of the resource.
     * @param $slqfId
     * @return Response
     */
    public function index($slqfId)
    {
        $slqf = SlqfStructure::query()->find($slqfId);

        if ($slqf) {
            $pageTitle = $slqf["slqf_name"] . " | SLQF Versions";
            $tableTitle = $slqf["slqf_name"] . " | SLQF Versions";

            $this->repository->setPageTitle($pageTitle);

            $this->repository->initDatatable(new SlqfVersion());

            $this->repository->setColumns("id", "version_name", "download", "default_status", "version_status", "approval_status", "created_at")
                ->setColumnLabel("version_name", "Name")
                ->setColumnLabel("version_status", "Status")
                ->setColumnLabel("slqf_file_name", "Download")
                ->setColumnDisplay("default_status", array($this->repository, 'displayStatusAs'), [$this->repository->defaultStatuses])
                ->setColumnDisplay("approval_status", array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])
                ->setColumnDisplay("version_status", array($this->repository, 'displayStatusActionAs'))
                ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
                ->setColumnDisplay("download", array($this->repository, 'displayListButtonAs'), ["Download", URL::to("/academic/slqf_version/download/")])
                ->setColumnFilterMethod("default_status", "select", $this->repository->defaultStatuses)
                ->setColumnFilterMethod("version_status", "select", $this->repository->statuses)
                ->setColumnFilterMethod("approval_status", "select", $this->repository->approvalStatuses)
                ->setColumnSearchability("created_at", false)
                ->setColumnSearchability("updated_at", false)

                ->setColumnDBField("download", "slqf_file_name");

            if ($this->trash) {
                $query = $this->repository->model::onlyTrashed();

                $this->repository->setTableTitle($tableTitle . " | Trashed")
                    ->enableViewData("list", "restore", "export")
                    ->disableViewData("view", "edit", "delete")
                    ->setUrl("list", $this->repository->getUrl("list") . "/" . $slqfId)
                    ->setUrl("add", $this->repository->getUrl("add") . "/" . $slqfId);
            } else {
                $query = $this->repository->model::query();

                $this->repository->setTableTitle($tableTitle)
                    ->enableViewData("trashList", "trash", "export")
                    ->setUrl("trashList", $this->repository->getUrl("trashList") . "/" . $slqfId)
                    ->setUrl("add", $this->repository->getUrl("add") . "/" . $slqfId);
            }

            $query = $query->with(["slqf"]);

            $query->where("slqf_id", "=", $slqfId);

            return $this->repository->render("academic::layouts.master")->index($query);
        } else {
            abort(404);
        }
    }

    /**
     * Display a listing of the resource.
     * @param $slqfId
     * @return Response
     */
    public function trash($slqfId)
    {
        $this->trash = true;
        return $this->index($slqfId);
    }

    /**
     * Show the form for creating a new resource.
     * @param int $slqfId
     * @return Factory|View
     */
    public function create($slqfId)
    {
        $slqf = SlqfStructure::query()->find($slqfId);

        if ($slqf) {
            $this->repository->setPageTitle("SLQF Versions | Add New");

            $model = new SlqfVersion();
            $model->slqf = $slqf;

            $record = $model;

            $formMode = "add";
            $formType = "version";
            $formSubmitUrl = request()->getPathInfo();

            $urls = [];
            $urls["listUrl"] = URL::to("/academic/slqf_version/" . $slqfId);

            $this->repository->setPageUrls($urls);

            return view('academic::slqf_structure.create', compact('formMode', 'formSubmitUrl', 'record', 'formType'));
        } else {
            abort(404);
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param SlqfStructure $slqfId
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store($slqfId): JsonResponse
    {
        $slqf = SlqfStructure::query()->find($slqfId);

        if ($slqf) {
            $model = new SlqfVersion();

            $model = $this->repository->getValidatedData($model, [
                "version_name" => "required",
                "version_date" => "required|date",
                "slqf_file_name" => "mimes:pdf",
                "default_status" => "required|digits:1",
            ], [], ["version_name" => "Version Name", "version_date" => "Date of Amendment", "slqf_file_name" => "SLQF Document"]);

            if ($this->repository->isValidData) {
                $fileName = uniqid() . "_" . $_FILES["slqf_file_name"]["name"];

                //set as 1 until approval process implements
                $model->version_status = 1;

                $model->slqf_id = $slqfId;
                $model->version = $this->repository->generateVersion();
                $model->slqf_file_name = $fileName;
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] == "success") {
                    $this->repository->uploadSlqfFile($fileName);

                    if ($model->default_status == "1") {
                        $this->repository->resetOtherVersionDefault($slqfId, $model->slqf_version_id);
                    }

                    if (request()->post("send_for_approval") == "1") {
                        $response = $this->repository->startApprovalProcess($model, 0, $response);
                    }
                }
            } else {
                $response = $model;
            }

            return $this->repository->handleResponse($response);
        } else {
            $response["notify"]["status"] = "failed";
            $response["notify"]["notify"][] = "Selected permission system does not exist.";
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Factory|View
     */
    public function edit($id)
    {
        $this->repository->setPageTitle("SLQF Versions | Edit");

        $model = SlqfVersion::with(["slqf"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $formMode = "edit";
            $formType = "version";
            $formSubmitUrl = request()->getPathInfo();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/slqf_version/create/" . $record["slqf_id"]);
            $urls["listUrl"] = URL::to("/academic/slqf_version/" . $record["slqf_id"]);

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
     */
    public function update($id): JsonResponse
    {
        $model = SlqfVersion::query()->find($id);

        if ($model) {
            $currFileName = $model->slqf_file_name;

            $model = $this->repository->getValidatedData($model, [
                "version_name" => "required",
                "version_date" => "required|date",
                "default_status" => "required|digits:1",
            ], [], ["version_name" => "Version Name", "version_date" => "Date of Amendment", "slqf_file_name" => "SLQF Document"]);

            if ($currFileName == "" || isset($_FILES["slqf_file_name"]["tmp_name"])) {
                $model = $this->repository->getValidatedData($model, [
                    "slqf_file_name" => "mimes:pdf",
                ], [], ["slqf_file_name" => "SLQF Document"]);
            }

            if ($this->repository->isValidData) {
                $fileName = "";
                if (isset($_FILES["slqf_file_name"]["tmp_name"])) {
                    $fileName = uniqid() . "_" . $_FILES["slqf_file_name"]["name"];
                    $model->slqf_file_name = $fileName;
                }

                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] == "success") {
                    if (isset($_FILES["slqf_file_name"]["tmp_name"])) {
                        $this->repository->uploadSlqfFile($fileName, $currFileName);
                    }

                    if ($model->default_status == "1") {
                        $this->repository->resetOtherVersionDefault($model->slqf_id, $model->slqf_version_id);
                    }

                    if (request()->post("send_for_approval") == "1") {
                        $response = $this->repository->startApprovalProcess($model, 0, $response);
                    }
                }
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
        $model = SlqfVersion::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = SlqfVersion::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Search records
     * @param Request $request
     * @return JsonResponse
     */
    public function searchData(Request $request)
    {
        if ($request->expectsJson()) {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $slqfId = $request->post("slqf_id");
            $limit = $request->post("limit");

            $query = SlqfVersion::query()->select("slqf_version_id", "version_name", "slqf_name")
                ->from("slqf_versions")->join("slqf_structures", "slqf_versions.slqf_id", "=", "slqf_structures.slqf_id")
                ->where("version_status", "=", "1")
                ->orderByDesc("version_name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($searchText != "") {
                $query = $query->where("version_name", "LIKE", "%" . $searchText . "%");
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("slqf_version_id", $idNot);
            }

            if ($slqfId != "") {
                $query = $query->where("slqf_versions.slqf_id", $slqfId);
            }

            $results = $query->get()->toArray();

            $data = [];
            if ($results) {
                foreach ($results as $result) {
                    $result["name"] = $result["slqf_name"] . " - " . $result["name"];

                    $data[] = $result;
                }
            }

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }

    /**
     * Move the record to trash
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function download($id)
    {
        $model = SlqfVersion::query()->find($id);

        if ($model) {
            return $this->repository->downloadSlqfFile($model->slqf_file_name, $model->version_name);
        } else {
            $notify = array();
            $notify["status"] = "failed";
            $notify["notify"][] = "Requested file does not exist.";

            $dataResponse["notify"] = $notify;
        }

        return $this->repository->handleResponse($dataResponse);
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return mixed
     */
    public function changeStatus($id)
    {
        $model = SlqfVersion::query()->find($id);
        return $this->repository->updateStatus($model, "version_status");
    }

    public function verification($id)
    {
        $model = SlqfVersion::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function verificationSubmit($id)
    {
        $model = SlqfVersion::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function approval($id)
    {
        $model = SlqfVersion::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function approvalSubmit($id)
    {
        $model = SlqfVersion::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function approvalHistory($modelHash, $id)
    {
        $model = new SlqfVersion();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new SlqfVersion();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
