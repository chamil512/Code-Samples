<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicMeeting;
use Modules\Academic\Repositories\AcademicMeetingRepository;

class AcademicMeetingController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new AcademicMeetingRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $pageTitle = "Academic Meetings";
        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new AcademicMeeting());

        $this->repository->setColumns("id", "meeting_name", "committee", "agenda", "meeting_status", "created_at")
            ->setColumnLabel("meeting_name", "Academic Meeting")
            ->setColumnLabel("meeting_status", "Status")

            ->setColumnDisplay("agenda", array($this->repository, 'displayRelationAs'), ["agenda", "academic_meeting_agenda_id", "name"])
            ->setColumnDisplay("committee", array($this->repository, 'displayRelationAs'),
                ["committee", "id", "name", URL::to("/academic/academic_meeting_committee_member/")])
            ->setColumnDisplay("meeting_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("meeting_name")
            ->setColumnFilterMethod("meeting_status", "select", $this->repository->statuses)
            ->setColumnFilterMethod("agenda", "select", URL::to("/academic/academic_meeting_agenda/search_data"))
            ->setColumnFilterMethod("committee", "select", URL::to("/academic/academic_meeting_committee/search_data"))

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false)

            ->setColumnDBField("agenda", "id")
            ->setColumnFKeyField("agenda", "academic_meeting_agenda_id")
            ->setColumnRelation("agenda", "committee", "agenda_name")

            ->setColumnDBField("committee", "id")
            ->setColumnFKeyField("committee", "academic_meeting_committee_id")
            ->setColumnRelation("committee", "committee", "committee_name");

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = "Academic Meetings | Trashed";

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = "Academic Meetings";

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export");
        }

        $query = $query->with(["agenda", "committee"]);

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
        $model = new AcademicMeeting();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/academic_meeting");

        $this->repository->setPageUrls($urls);

        return view('academic::academic_meeting.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     */
    public function store()
    {
        $model = new AcademicMeeting();

        $model = $this->repository->getValidatedData($model, [
            "academic_meeting_agenda_id" => "required|exists:academic_meeting_agendas,id",
            "academic_meeting_committee_id" => "required|exists:academic_meeting_committees,id",
            "meeting_name" => "required",
            "meeting_status" => "required"
        ], [], ["academic_meeting_agenda_id" => "Agenda", "academic_meeting_committee_id" => "Committee", "meeting_name" => "Academic Meeting name"]);

        if ($this->repository->isValidData) {
            $response = $this->repository->saveModel($model);
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
        $model = AcademicMeeting::query()->find($id);

        if ($model) {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/academic_meeting/create");
            $urls["listUrl"] = URL::to("/academic/academic_meeting");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_meeting.view', compact('record'));
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
        $model = AcademicMeeting::with(["agenda"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/academic_meeting/create");
            $urls["listUrl"] = URL::to("/academic/academic_meeting");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_meeting.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = AcademicMeeting::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "academic_meeting_agenda_id" => "required|exists:academic_meeting_agendas,id",
                "academic_meeting_committee_id" => "required|exists:academic_meeting_committees,id",
                "meeting_name" => "required",
                "meeting_status" => "required"
            ], [], ["academic_meeting_agenda_id" => "Agenda", "academic_meeting_committee_id" => "Committee", "meeting_name" => "Academic Meeting name", "meeting_status" => "Meeting Status"]);

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
        $model = AcademicMeeting::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = AcademicMeeting::withTrashed()->find($id);

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
            $agendaId = $request->post("academic_meeting_agenda_id");
            $limit = $request->post("limit");

            $query = AcademicMeeting::query()
                ->select("id", "meeting_name", "academic_meeting_committee_id AS committee_id")
                ->where("meeting_status", "=", "1")
                ->orderBy("meeting_name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($agendaId != "") {
                $query = $query->where("academic_meeting_agenda_id", $agendaId);
            }

            if ($searchText != "") {
                $query = $query->where("meeting_name", "LIKE", "%" . $searchText . "%");
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
        $model = AcademicMeeting::query()->find($id);
        return $this->repository->updateStatus($model, "meeting_status");
    }

    /**
     * Display a listing of the resource.
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new AcademicMeeting();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
