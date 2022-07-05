<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicMeetingCommittee;
use Modules\Academic\Repositories\AcademicMeetingCommitteeRepository;

class AcademicMeetingCommitteeController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new AcademicMeetingCommitteeRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $pageTitle = "Academic Meeting Committees";
        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new AcademicMeetingCommittee());

        $this->repository->setColumns("id", "committee_name", "committee_members", "committee_status", "created_at")
            ->setColumnLabel("committee_name", "Committee")
            ->setColumnLabel("committee_status", "Status")

            ->setColumnDisplay("committee_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnDisplay("committee_members", array($this->repository, 'displayListButtonAs'), ["Committee Members", URL::to("/academic/academic_meeting_committee_member/")])
            ->setColumnDBField("committee_members", $this->repository->primaryKey)

            ->setColumnFilterMethod("committee_name")
            ->setColumnFilterMethod("committee_status", "select", $this->repository->statuses)

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = "Academic Meeting Committees | Trashed";

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = "Academic Meeting Committees";

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
        $model = new AcademicMeetingCommittee();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/academic_meeting_committee");

        $this->repository->setPageUrls($urls);

        return view('academic::academic_meeting_committee.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     */
    public function store()
    {
        $model = new AcademicMeetingCommittee();

        $model = $this->repository->getValidatedData($model, [
            "committee_name" => "required",
            "committee_status" => "required"
        ], [], ["committee_name" => "Academic Meeting Committee name"]);

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
        $model = AcademicMeetingCommittee::with(["agenda"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/academic_meeting_committee/create");
            $urls["listUrl"] = URL::to("/academic/academic_meeting_committee");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_meeting_committee.create', compact('formMode', 'formSubmitUrl', 'record'));
        } else {
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
        $model = AcademicMeetingCommittee::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "committee_name" => "required",
                "committee_status" => "required"
            ], [], ["committee_name" => "Academic Meeting Committee name", "committee_status" => "Committee Status"]);

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
        $model = AcademicMeetingCommittee::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = AcademicMeetingCommittee::withTrashed()->find($id);

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
            $limit = $request->post("limit");

            $query = AcademicMeetingCommittee::query()
                ->select("id", "committee_name")
                ->where("committee_status", "=", "1")
                ->orderBy("committee_name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($searchText != "") {
                $query = $query->where("committee_name", "LIKE", "%" . $searchText . "%");
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
        $model = AcademicMeetingCommittee::query()->find($id);
        return $this->repository->updateStatus($model, "committee_status");
    }

    /**
     * Display a listing of the resource.
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new AcademicMeetingCommittee();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
