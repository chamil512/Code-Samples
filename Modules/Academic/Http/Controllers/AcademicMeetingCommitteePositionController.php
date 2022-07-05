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
use Modules\Academic\Entities\AcademicMeetingCommitteePosition;
use Modules\Academic\Repositories\AcademicMeetingCommitteePositionRepository;

class AcademicMeetingCommitteePositionController extends Controller
{
    private AcademicMeetingCommitteePositionRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new AcademicMeetingCommitteePositionRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $pageTitle = "Academic Meeting Committee Positions";
        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new AcademicMeetingCommitteePosition());

        $this->repository->setColumns("id", "position_name", "status", "created_at")
            ->setColumnLabel("position_name", "Academic Meeting Committee Position")
            ->setColumnLabel("status", "Status")

            ->setColumnDisplay("status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("position_name")
            ->setColumnFilterMethod("status", "select", $this->repository->statuses)

            ->setColumnSearchability("created_at", false);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = "Academic Meeting Committee Positions | Trashed";

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = "Academic Meeting Committee Positions";

            $this->repository->setTableTitle($tableTitle)
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
        $model = new AcademicMeetingCommitteePosition();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = URL::to("/" . request()->path());

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/academic_meeting_committee_position");

        $this->repository->setPageUrls($urls);

        return view('academic::academic_meeting_committee_position.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(): JsonResponse
    {
        $model = new AcademicMeetingCommitteePosition();

        $model = $this->repository->getValidatedData($model, [
            "position_name" => "required",
            "status" => "required"
        ], [], ["position_name" => "Academic Meeting Committee Position name"]);

        if ($this->repository->isValidData) {
            $response = $this->repository->saveModel($model);
        } else {
            $response = $model;
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
        $model = AcademicMeetingCommitteePosition::query()->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = URL::to("/" . request()->path());

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/academic_meeting_committee_position/create");
            $urls["listUrl"] = URL::to("/academic/academic_meeting_committee_position");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_meeting_committee_position.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = AcademicMeetingCommitteePosition::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "position_name" => "required",
                "status" => "required"
            ], [], ["position_name" => "Academic Meeting Committee Position name"]);

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
        $model = AcademicMeetingCommitteePosition::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = AcademicMeetingCommitteePosition::withTrashed()->find($id);

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

            $query = AcademicMeetingCommitteePosition::query()
                ->select(["id", "position_name"])
                ->where($this->repository->statusField, 1)
                ->orderBy("position_name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($searchText != "") {
                $query = $query->where("position_name", "LIKE", "%" . $searchText . "%");
            }

            if ($idNot != "") {
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
        $model = AcademicMeetingCommitteePosition::query()->find($id);
        return $this->repository->updateStatus($model, $this->repository->statusField);
    }

    /**
     * Display a listing of the resource.
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new AcademicMeetingCommitteePosition();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
