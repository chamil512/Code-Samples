<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicMeetingCommittee;
use Modules\Academic\Entities\AcademicMeetingCommitteeMember;
use Modules\Academic\Repositories\AcademicMeetingCommitteeMemberDocumentRepository;
use Modules\Academic\Repositories\AcademicMeetingCommitteeMemberPositionRepository;
use Modules\Academic\Repositories\AcademicMeetingCommitteeMemberRepository;
use Modules\Admin\Entities\Admin;

class AcademicMeetingCommitteeMemberController extends Controller
{
    private AcademicMeetingCommitteeMemberRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new AcademicMeetingCommitteeMemberRepository();
    }

    /**
     * Display a listing of the resource.
     * @param int $committeeId
     * @return Factory|View
     */
    public function index($committeeId)
    {
        $cc = AcademicMeetingCommittee::query()->find($committeeId);

        $ccTitle = "";
        if ($cc) {
            $ccTitle = $cc["name"];
        } else {
            abort(404, "Academic Meeting not available");
        }

        $pageTitle = $ccTitle . " | Committee Members";

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new AcademicMeetingCommitteeMember());

        $statusParams = [["id" => "1", "name" => "Default", "label" => "success"], ["id" => "0", "name" => "Non-Default", "label" => "info"]];

        $this->repository->setColumns("id", "member_name", "committee_positions", "documents", "member_status", "created_at")
            ->setColumnLabel("appointed_date", "Appointed On/Period")
            ->setColumnLabel("member_name", "Committee Member")
            ->setColumnLabel("member_status", "Status")
            ->setColumnLabel("committee_positions", "Committee Position(s)")

            ->setColumnDBField("committee_positions", "id")
            ->setColumnFKeyField("committee_positions", "academic_meeting_committee_position_id")
            ->setColumnRelation("committee_positions", "committeePositions", "position_name")

            ->setColumnDisplay("member_name", array($this->repository, 'displayRelationAs'),
                ["admin", "admin_id", "name", URL::to("/admin/admin/view/")])
            ->setColumnDisplay("documents", array($this->repository, 'displayListButtonAs'), ["Documents", URL::to("/academic/academic_meeting_committee_member_document/")])
            ->setColumnDisplay("committee_positions", array($this->repository, 'displayRelationManyAs'), ["committeePositions", "committeePosition", "id", "name"])
            ->setColumnDisplay("default_status", array($this->repository, 'displayStatusAs'), [$statusParams])
            ->setColumnDisplay("member_status", array($this->repository, 'displayStatusActionAs'))
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("member_name", "select", URL::to("/admin/admin/search_data"))
            ->setColumnFilterMethod("committee_positions", "select", URL::to("/academic/academic_meeting_committee_position/search_data"))
            ->setColumnFilterMethod("member_status", "select", [["id" => "1", "name" => "Enabled"], ["id" => "0", "name" => "Disabled"]])

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("documents", false)
            ->setColumnSearchability("updated_at", false)

            ->setColumnDBField("documents", "id")
            ->setColumnDBField("member_name", "admin_id")

            ->setColumnFKeyField("member_name", "admin_id")
            ->setColumnRelation("member_name", "admin", "name");

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();
            $tableTitle = $ccTitle . " | Committee Members | Trashed";

            $this->repository->setUrl("list", "/academic/academic_meeting_committee_member/" . $committeeId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("edit", "delete");
        } else {
            $query = $this->repository->model::query();
            $tableTitle = $ccTitle . " | Committee Members";

            $this->repository->setCustomControllerUrl("/academic/academic_meeting_committee_member", ["list"], false)
                ->setUrl("trashList", "/academic/academic_meeting_committee_member/trash/" . $committeeId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export");
        }

        $this->repository->setUrl("add", "/academic/academic_meeting_committee_member/create/" . $committeeId);
        $query = $query->where(["academic_meeting_committee_id" => $committeeId]);

        $query = $query->with(["admin", "committeePositions", "committeePositions.committeePosition"]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param int $committeeId
     * @return Factory|View
     */
    public function trash($committeeId)
    {
        $this->trash = true;
        return $this->index($committeeId);
    }

    /**
     * Show the form for creating a new resource.
     * @param $committeeId
     * @return Factory|View
     */
    public function create($committeeId)
    {
        $committee = AcademicMeetingCommittee::query()->find($committeeId);
        if (!$committee) {
            abort(404, "Academic Meeting not available");
        }

        $model = new AcademicMeetingCommitteeMember();
        $model->committee = $committee;
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = URL::to("/" . request()->path());

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/academic_meeting_committee_member/" . $committeeId);

        $this->repository->setPageUrls($urls);

        return view('academic::academic_meeting_committee_member.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @param $committeeId
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store($committeeId): JsonResponse
    {
        $committee = AcademicMeetingCommittee::query()->find($committeeId);
        if (!$committee) {
            abort(404, "Academic Meeting not available");
        }

        $model = new AcademicMeetingCommitteeMember();
        $model = $this->repository->getValidatedData($model, [
            "admin_id" => [
                'required',
                'exists:admins,admin_id',
                Rule::unique('academic_meeting_committee_members')->where(function ($query) use ($committeeId) {

                    $query->where('academic_meeting_committee_id', $committeeId);
                }),
            ],
            "appointed_date" => "required",
            "member_status" => "required",
        ], [], ["admin_id" => "Committee Member"]);

        if ($this->repository->isValidData) {
            $model->academic_meeting_committee_id = $committeeId;
            $response = $this->repository->saveModel($model);

            if ($response["notify"]["status"] == "success") {

                $docRepo = new AcademicMeetingCommitteeMemberDocumentRepository();
                $docRepo->update($model);

                $aMCMPRepo = new AcademicMeetingCommitteeMemberPositionRepository();
                $aMCMPRepo->update($model);
            }
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
        $model = AcademicMeetingCommitteeMember::with([
            "committee",
            "admin",
            "documents",
            "committeePositions",
            "committeePositions.committeePosition"
        ])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = URL::to("/" . request()->path());

            $positions = [];
            if (count($record["committee_positions"]) > 0) {

                foreach ($record["committee_positions"] as $cp) {
                    $positions[] = $cp["committee_position"];
                }
            }
            $record["positions"] = $positions;

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/academic_meeting_committee_member/create/" . $model->academic_meeting_committee_id);
            $urls["listUrl"] = URL::to("/academic/academic_meeting_committee_member/" . $model->academic_meeting_committee_id);
            $urls["downloadUrl"] = URL::to("/academic/academic_meeting_committee_member_document/download/");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_meeting_committee_member.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = AcademicMeetingCommitteeMember::query()->find($id);

        if ($model) {
            $committeeId = $model->academic_meeting_committee_id;
            $fields = [
                "admin_id" => [
                    'required',
                    'exists:admins,admin_id',
                    Rule::unique('academic_meeting_committee_members')->where(function ($query) use ($id, $committeeId) {

                        $query->where('id', "!=", $id);
                        $query->where('academic_meeting_committee_id', $committeeId);
                    }),
                ],
                "appointed_date" => "required",
                "member_status" => "required",
            ];

            $model = $this->repository->getValidatedData($model, $fields, [], ["admin_id" => "Committee Member"]);

            if ($this->repository->isValidData) {
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] == "success") {

                    $docRepo = new AcademicMeetingCommitteeMemberDocumentRepository();
                    $docRepo->update($model);

                    $aMCMPRepo = new AcademicMeetingCommitteeMemberPositionRepository();
                    $aMCMPRepo->update($model);

                    $response["data"]["documents"] = $model->documents()->get()->toArray();
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
        $model = AcademicMeetingCommitteeMember::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = AcademicMeetingCommitteeMember::withTrashed()->find($id);

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
            $committeeId = $request->post("committee_id");
            $limit = $request->post("limit");

            $memberIds = $this->repository->getMeetingMemberIds($committeeId);

            $query = Admin::query()
                ->select("admin_id", "name")
                ->whereIn("admin_id", $memberIds)
                ->where("status", "=", "1")
                ->orderBy("name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($searchText != "") {
                $query->where(function ($query) use ($searchText) {

                    $query->where("name", "LIKE", "%" . $searchText . "%");
                });
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("admin_id", $idNot);
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
        $model = AcademicMeetingCommitteeMember::query()->find($id);
        return $this->repository->updateStatus($model, "member_status");
    }

    /**
     * @param $id
     * @return mixed
     */
    public function downloadAppointmentLetter($id)
    {
        $model = AcademicMeetingCommitteeMember::withTrashed()->find($id);

        if ($model) {

            return $this->repository->downloadAppointmentLetter($model->appointment_letter);
        } else {
            abort(404);
        }
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $options = [];
        $options["suffix"] = "Committee Member";

        $model = new AcademicMeetingCommitteeMember();
        return $this->repository->recordHistory($model, $modelHash, $id, $options);
    }
}
