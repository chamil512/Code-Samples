<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicMeetingDocument;
use Modules\Academic\Entities\AcademicMeetingSchedule;
use Modules\Academic\Repositories\AcademicMeetingDocumentRepository;

class AcademicMeetingDocumentController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new AcademicMeetingDocumentRepository();
    }

    /**
     * Display a listing of the resource.
     * @param mixed $scheduleId
     * @return Factory|View
     */
    public function index($scheduleId)
    {
        $scheduleTitle = "";
        $cc = AcademicMeetingSchedule::query()->find($scheduleId);

        if ($cc) {
            $scheduleTitle = $cc["name"];
        } else {
            abort(404, "Meeting Schedule not available");
        }

        $pageTitle = "Academic Meeting Documents";
        if ($scheduleTitle != "") {
            $pageTitle = $scheduleTitle . " | " . $pageTitle;
        }

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new AcademicMeetingDocument());

        $this->repository->setColumns("id", "committee", "faculty", "department", "submit_type", "purpose_type", "approval_status", "download", "created_at")
            ->setColumnLabel("submit_type", "Submission Type")
            ->setColumnLabel("purpose_type", "Purpose")
            ->setColumnLabel("approval_status", "Approval")
            ->setColumnDisplay("approval_status", array($this->repository, 'displayStatusRelationAs'), [$this->repository->approvalOptions,
                "approver", "admin_id", "name", URL::to("/admin/admin/view/")])
            ->setColumnDisplay("committee", array($this->repository, 'displayRelationAs'), ["committee", "id", "name"])
            ->setColumnDisplay("faculty", array($this->repository, 'displayRelationAs'), ["faculty", "faculty_id", "name"])
            ->setColumnDisplay("department", array($this->repository, 'displayRelationAs'), ["department", "dept_id", "name"])
            ->setColumnDisplay("submit_type", array($this->repository, 'displayStatusAs'), [$this->repository->submitTypeOptions])
            ->setColumnDisplay("purpose_type", array($this->repository, 'displayStatusAs'), [$this->repository->purposeTypeOptions])
            ->setColumnDisplay("download", array($this->repository, 'displayListButtonAs'), ["Download", URL::to("/academic/academic_meeting_document/download/")])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("submit_type", "select", $this->repository->submitTypeOptions)
            ->setColumnFilterMethod("purpose_type", "select", $this->repository->purposeTypeOptions)
            ->setColumnFilterMethod("approval_status", "select", $this->repository->approvalOptions)
            ->setColumnFilterMethod("committee", "select", URL::to("/academic/academic_meeting_committee/search_data"))
            ->setColumnFilterMethod("faculty", "select", URL::to("/academic/faculty/search_data"))
            ->setColumnFilterMethod("department", "select", URL::to("/academic/department/search_data"))
            ->setColumnSearchability("created_at", false)

            ->setColumnDBField("download", "id")

            ->setColumnDBField("committee", "id")
            ->setColumnFKeyField("committee", "academic_meeting_committee_id")
            ->setColumnRelation("committee", "committee", "committee_name")

            ->setColumnDBField("faculty", "faculty_id")
            ->setColumnFKeyField("faculty", "faculty_id")
            ->setColumnRelation("faculty", "faculty", "faculty_name")

            ->setColumnDBField("department", "dept_id")
            ->setColumnFKeyField("department", "dept_id")
            ->setColumnRelation("department", "dept_id", "dept_name");

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = "Academic Meeting Documents | Trashed";
            if ($scheduleId) {
                if ($scheduleTitle != "") {
                    $tableTitle = $scheduleTitle . " | " . $tableTitle;

                    $this->repository->setUrl("list", "/academic/academic_meeting_document/" . $scheduleId);
                }
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = "Academic Meeting Documents";
            if ($scheduleId) {
                if ($scheduleTitle != "") {
                    $tableTitle = $scheduleTitle . " | " . $tableTitle;

                    $this->repository->setUrl("trashList", "/academic/academic_meeting_document/trash/" . $scheduleId);
                }
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export");
        }

        $this->repository->setUrl("add", "/academic/academic_meeting_document/create/" . $scheduleId);
        $query = $query->with(["committee", "faculty", "department", "agendaItemHeading", "agendaItemSubHeading", "approver"]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param $scheduleId
     * @return Factory|View
     */
    public function trash($scheduleId)
    {
        $this->trash = true;
        return $this->index($scheduleId);
    }

    /**
     * Show the form for creating a new resource.
     * @param $scheduleId
     * @return Factory|View
     */
    public function create($scheduleId)
    {
        $schedule = AcademicMeetingSchedule::with(["academicMeeting"])->find($scheduleId);

        if ($schedule) {
            $model = new AcademicMeetingDocument();

            $academicMeeting = $schedule->academicMeeting;
            $agenda = $academicMeeting->agenda;
            $model->meeting_schedule = $schedule;

            $record = $model;

            $formMode = "add";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            if ($scheduleId) {
                $urls["listUrl"] = URL::to("/academic/academic_meeting_document/" . $scheduleId);
            } else {
                $urls["listUrl"] = URL::to("/academic/academic_meeting_document");
            }

            $this->repository->setPageUrls($urls);

            return view('academic::academic_meeting_document.create', compact('formMode', 'formSubmitUrl', 'record', 'agenda'));
        } else {
            abort(404, "Meeting Schedule not available");
        }
    }

    /**
     * Show the form for creating a new resource.
     * @param $scheduleId
     * @return Factory|View
     */
    public function submit($scheduleId)
    {
        $schedule = AcademicMeetingSchedule::with(["academicMeeting"])->find($scheduleId);

        if ($schedule) {
            $model = new AcademicMeetingDocument();

            $academicMeeting = $schedule->academicMeeting;
            $agenda = $academicMeeting->agenda;
            $model->meeting_schedule = $schedule;

            $record = $model;

            $formMode = "add";
            $formSubmitUrl = "/academic/academic_meeting_document/create/" . $scheduleId;

            $urls = [];
            if ($scheduleId) {
                $urls["listUrl"] = URL::to("/academic/academic_meeting_document/" . $scheduleId);
            } else {
                $urls["listUrl"] = URL::to("/academic/academic_meeting_document");
            }

            $this->repository->setPageUrls($urls);

            return view('academic::academic_meeting_document.submit', compact('formMode', 'formSubmitUrl', 'record', 'agenda'));
        } else {
            abort(404, "Meeting Schedule not available");
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param $scheduleId
     * @return JsonResponse
     */
    public function store($scheduleId)
    {
        $model = new AcademicMeetingDocument();

        $approverType = "Approved person";
        if (request()->post("approval_status") === "2") {

            $approverType = "Rejected person";
        }

        $fields = [
            "submit_type" => "required",
            "academic_meeting_committee_id" => [Rule::requiredIf(function () { return request()->post("submit_type") == "1";})],
            "faculty_id" => [Rule::requiredIf(function () { return request()->post("submit_type") == "2";})],
            "dept_id" => [Rule::requiredIf(function () { return request()->post("submit_type") == "2";})],
            "purpose_type" => "required",
            "agenda_item_heading_id" => "required",
            "agenda_item_sub_heading_id" => "required",
            "approval_status" => "required",
            "approval_by" => [Rule::requiredIf(function () { return request()->post("purpose_type") == "1" && request()->post("approval_status") != "0";})],
        ];

        if($model->file_name == "") {

            $fields["file_name"] = "required|mimes:doc,pdf,docx";
        } else if (isset($_FILES["file_name"]["tmp_name"])) {

            $fields["file_name"] = "mimes:doc,pdf,docx";
        }

        $model = $this->repository->getValidatedData($model, $fields, [], [
            "submit_type" => "Submission Type",
            "academic_meeting_committee_id" => "Committee",
            "faculty_id" => "Faculty",
            "dept_id" => "Department",
            "purpose_type" => "Purpose",
            "agenda_item_heading_id" => "Agenda Item Heading",
            "agenda_item_sub_heading_id" => "Agenda Item Sub Heading",
            "file_name" => "Meeting Document",
            "approval_by" => $approverType,
        ]);

        if ($this->repository->isValidData) {

            if ($model->submit_type == "1") {

                $model->faculty_id = 0;
                $model->dept_id = 0;

            } else {
                $model->academic_meeting_committee_id = 0;
            }

            if ($model->purpose_type == "2") {

                $model->approval_status = NULL;
                $model->approval_by = NULL;
            } else {

                if ($model->approval_status == "0") {
                    $model->approval_by = NULL;
                }
            }

            //upload document
            $uploadDoc = $this->repository->uploadDocument();

            if($uploadDoc) {
                $model->academic_meeting_schedule_id = $scheduleId;
                $model->file_name = $uploadDoc;
                $response = $this->repository->saveModel($model);
            } else {
                $response = [];
                $response["notify"]["status"]="failed";
                $response["notify"]["notify"][]="Document Saving was failed.";
                $response["notify"]["notify"][]="Document Uploading Was Failed.";
                $response["notify"]["notify"][]="Try Uploading Document Again.";
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
        $model = AcademicMeetingDocument::with(["meetingSchedule", "committee", "faculty", "department", "agendaItemHeading", "agendaItemSubHeading", "approver"])->find($id);

        if ($model) {

            $schedule = $model->meetingSchedule;

            $academicMeeting = $schedule->academicMeeting;
            $agenda = $academicMeeting->agenda;

            $model->meeting_schedule = $schedule;

            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/academic_meeting_document/create/" . $model->academic_meeting_schedule_id);
            $urls["listUrl"] = URL::to("/academic/academic_meeting_document/" . $model->academic_meeting_schedule_id);

            $this->repository->setPageUrls($urls);

            return view('academic::academic_meeting_document.create', compact('formMode', 'formSubmitUrl', 'record', 'agenda'));
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
        $model = AcademicMeetingDocument::query()->find($id);

        if ($model) {

            $approverType = "Approved person";
            if (request()->post("approval_status") === "2") {

                $approverType = "Rejected person";
            }

            $fields = [
                "submit_type" => "required",
                "academic_meeting_committee_id" => [Rule::requiredIf(function () { return request()->post("submit_type") == "1";})],
                "faculty_id" => [Rule::requiredIf(function () { return request()->post("submit_type") == "2";})],
                "dept_id" => [Rule::requiredIf(function () { return request()->post("submit_type") == "2";})],
                "purpose_type" => "required",
                "agenda_item_heading_id" => "required",
                "agenda_item_sub_heading_id" => "required",
                "approval_status" => "required",
                "approval_by" => [Rule::requiredIf(function () { return request()->post("purpose_type") == "1" && request()->post("approval_status") != "0";})],
            ];

            if($model->file_name == "") {

                $fields["file_name"] = "required|mimes:doc,pdf,docx";
            } else if (isset($_FILES["file_name"]["tmp_name"])) {

                $fields["file_name"] = "mimes:doc,pdf,docx";
            }

            $model = $this->repository->getValidatedData($model, $fields, [], [
                "submit_type" => "Submission Type",
                "academic_meeting_committee_id" => "Committee",
                "faculty_id" => "Faculty",
                "dept_id" => "Department",
                "purpose_type" => "Purpose",
                "agenda_item_heading_id" => "Agenda Item Heading",
                "agenda_item_sub_heading_id" => "Agenda Item Sub Heading",
                "file_name" => "Meeting Document",
                "approval_by" => $approverType,
            ]);

            if ($this->repository->isValidData) {

                if ($model->submit_type == "1") {

                    $model->faculty_id = 0;
                    $model->dept_id = 0;

                } else {
                    $model->academic_meeting_committee_id = 0;
                }

                if ($model->purpose_type == "2") {

                    $model->approval_status = NULL;
                    $model->approval_by = NULL;
                } else {

                    if ($model->approval_status == "0") {
                        $model->approval_by = NULL;
                    }
                }

                if(isset($_FILES["file_name"])) {
                    $uploadDoc = $this->repository->uploadDocument($model->file_name);

                    if($uploadDoc) {
                        $model->file_name = $uploadDoc;
                        $response = $this->repository->saveModel($model);
                    } else {
                        $response = [];
                        $response["notify"]["status"]="failed";
                        $response["notify"]["notify"][]="Document Saving was failed.";
                        $response["notify"]["notify"][]="Document Uploading Was Failed.";
                        $response["notify"]["notify"][]="Try Uploading Document Again.";
                    }
                } else {
                    $response = $this->repository->saveModel($model);
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
        $model = AcademicMeetingDocument::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = AcademicMeetingDocument::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return void
     */
    public function download($id)
    {
        $model = AcademicMeetingDocument::withTrashed()->find($id);

        if($model) {

            return $this->repository->downloadDocument($model->file_name);
        } else {
            abort(404);
        }
    }

    /**
     * Display a listing of the resource.
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $options = [];
        $options["title"] = "Academic Meeting Document";

        $model = new AcademicMeetingDocument();
        return $this->repository->recordHistory($model, $modelHash, $id, $options);
    }
}
