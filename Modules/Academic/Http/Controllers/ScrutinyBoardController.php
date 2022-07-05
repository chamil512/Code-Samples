<?php

namespace Modules\Academic\Http\Controllers;

use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Academic\Entities\AcademicCalendar;
use Modules\Academic\Entities\CourseSyllabus;
use Modules\Academic\Entities\ScrutinyBoard;
use Modules\Academic\Exports\ExcelExport;
use Modules\Academic\Repositories\ScrutinyBoardRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScrutinyBoardController extends Controller
{
    private ScrutinyBoardRepository $repository;
    private bool $trash = false;
    private bool $academic = false;

    public function __construct()
    {
        $this->repository = new ScrutinyBoardRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        if ($this->academic) {
            $pageTitle = "Academic Examination Assessment Structures";
        } else {
            $pageTitle = "Master Examination Assessment Structures";
        }

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new ScrutinyBoard());

        $this->repository->setColumns("id", "board_name", "course", "syllabus", "status", "approval_status")
            ->setColumnLabel("board_name", "Structure Name")
            ->setColumnLabel("status", "Status")

            ->setColumnDBField("course", "course_id")
            ->setColumnFKeyField("course", "course_id")
            ->setColumnRelation("course", "course", "course_name")

            ->setColumnDBField("syllabus", "syllabus_id")
            ->setColumnFKeyField("syllabus", "syllabus_id")
            ->setColumnRelation("syllabus", "syllabus", "syllabus_name")

            ->setColumnDisplay("course", array($this->repository, 'displayRelationAs'),
                ["course", "id", "name", URL::to("/academic/course/view/")])
            ->setColumnDisplay("syllabus", array($this->repository, 'displayRelationAs'),
                ["syllabus", "syllabus_id", "syllabus_name", URL::to("/academic/course_syllabus/view/")])
            ->setColumnDisplay("status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses, "", "", true])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnDisplay("approval_status", array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])
            ->setColumnFilterMethod("course", "select", [
                "options" => URL::to("/academic/course/search_data"),
                "basedColumns" => [
                    [
                        "column" => "department",
                        "param" => "dept_id",
                    ]
                ],
            ])
            ->setColumnFilterMethod("syllabus", "select", [
                "options" => URL::to("/academic/course_syllabus/search_data"),
                "basedColumns" => [
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ]
                ],
            ])
            ->setColumnFilterMethod($this->repository->statusField, "select", $this->repository->statuses)
            ->setColumnFilterMethod($this->repository->approvalField, "select", $this->repository->approvalStatuses)
            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        $this->repository->setCustomFilters("department")
            ->setColumnDBField("department", "course_id", true)
            ->setColumnFKeyField("department", "course_id", true)
            ->setColumnRelation("department", "course", "course_name", true)
            ->setColumnCoRelation("department", "department", "dept_name", "dept_id", "dept_id", true)
            ->setColumnFilterMethod("department", "select", URL::to("/academic/department/search_data"), true);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = $pageTitle . " | Trashed";

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");

            if ($this->academic) {
                $this->repository->setUrl("list", "/academic/scrutiny_board/academic/");
            }
        } else {
            $query = $this->repository->model::query();

            $tableTitle = $pageTitle;

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export", "view")
                ->setRowActionBeforeButton(URL::to("/academic/scrutiny_board/duplicate/"), "Duplicate", "", "fa fa-copy");

            if ($this->academic) {
                $this->repository->setUrl("trashList", "/academic/scrutiny_board/academic_trash/");
            }
        }

        if ($this->academic) {
            $this->repository->unsetColumns("approval_status");
            $this->repository->setUrl("create", "/academic/scrutiny_board/create");
            $this->repository->setUrl("edit", "/academic/scrutiny_board/edit/");
            $this->repository->setUrl("view", "/academic/scrutiny_board/view/");
            $this->repository->setUrl("trash", "/academic/scrutiny_board/delete/");
            $this->repository->setUrl("restore", "/academic/scrutiny_board/restore/");

            $query->where("type", 2);
        } else {
            $query->where("type", 1);
        }

        $query = $query->with(["course", "syllabus"]);

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

    public function academic()
    {
        $this->academic = true;
        return $this->index();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function academicTrash()
    {
        $this->trash = true;
        $this->academic = true;
        return $this->index();
    }

    /**
     * Show the form for creating a new resource.
     * @return Factory|View
     */
    public function create()
    {
        $model = new ScrutinyBoard();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();
        $formSubmitUrl = URL::to($formSubmitUrl);

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/scrutiny_board");

        $this->repository->setPageUrls($urls);

        $dataFetchUrl = URL::to("/academic/scrutiny_board/get_data");

        return view('academic::scrutiny_board.create', compact('formMode', 'formSubmitUrl', 'record', 'dataFetchUrl'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(): JsonResponse
    {
        $model = new ScrutinyBoard();
        $model = $this->repository->getValidatedData($model, [
            "board_name" => "required",
            "course_id" => "required|exists:courses,course_id",
            "syllabus_id" => "required|exists:course_syllabi,syllabus_id",
        ], [], [
            "board_name" => "Examination Assessment Structure Name",
            "course_id" => "Course",
            "syllabus_id" => "Syllabus"
        ]);

        if ($this->repository->isValidData) {

            DB::beginTransaction();
            try {
                $model->type = 1;
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] === "success") {

                    $update = $this->repository->updateData($model);

                    if ($update["notify"]["status"] === "failed") {

                        $success = false;
                        $response["notify"] = array_merge($update["notify"], $response["notify"]);
                    } else {

                        $success = true;
                        if (request()->post("send_for_approval") == "1") {

                            $model->load(["course"]);
                            $response = $this->repository->startApprovalProcess($model, $this->repository->approvalDefaultStatus, $response);
                            $response["notify"]["status"] = "success";
                        }
                    }
                } else {

                    $success = false;

                    $response = [];
                    $response["notify"]["status"] = "failed";
                    $response["notify"]["notify"][] = "Examination Assessment Structure details saving was failed.";
                }
            } catch (Exception $ex) {

                $success = false;

                $response = [];
                $response["notify"]["status"] = "failed";
                $response["notify"]["notify"][] = "Examination Assessment Structure details saving was failed.";
            }

            if ($success) {

                DB::commit();
            } else {

                DB::rollBack();
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
        $model = ScrutinyBoard::with(["course", "syllabus"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/scrutiny_board/create");
            $urls["listUrl"] = URL::to("/academic/scrutiny_board");
            $urls["viewMasterUrl"] = URL::to("/academic/scrutiny_board/view/" . $model->master_scrutiny_board_id);
            $urls["exportUrl"] = URL::to("/academic/scrutiny_board/export/") . "/" . $id;

            $this->repository->setPageUrls($urls);

            $type = "view";
            $data = $this->repository->getPreparedDataForTable($model->syllabus, null, $model, false);
            $data["record"] = $record;

            return view('academic::scrutiny_board.view', compact('record', 'data', 'type'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return BinaryFileResponse
     */
    public function export($id): BinaryFileResponse
    {
        $model = ScrutinyBoard::with(["course", "syllabus"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $data = $this->repository->getPreparedDataForTable($model->syllabus, null, $model, false);
            $data["record"] = $record;

            $export = new ExcelExport();
            $export->data = $data;
            $export->view = "academic::scrutiny_board.export";

            return Excel::download($export, "Assessment Structure of Degree Programme.xlsx");
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
        $model = ScrutinyBoard::with(["course", "syllabus"])->find($id);

        if ($model) {

            if ($model->type === 2 || $model->{$this->repository->approvalField} !== 1) {

                $record = $model->toArray();
                $formMode = "edit";
                $formSubmitUrl = "/" . request()->path();
                $formSubmitUrl = URL::to($formSubmitUrl);

                $urls = [];
                $urls["addUrl"] = URL::to("/academic/scrutiny_board/create");
                $urls["listUrl"] = URL::to("/academic/scrutiny_board");
                $urls["viewMasterUrl"] = URL::to("/academic/scrutiny_board/view/" . $model->master_scrutiny_board_id);

                $this->repository->setPageUrls($urls);

                $dataFetchUrl = URL::to("/academic/scrutiny_board/get_data");

                return view('academic::scrutiny_board.create', compact('formMode', 'formSubmitUrl', 'record', 'dataFetchUrl'));
            } else {

                $notify = array();
                $notify["status"] = "failed";
                $notify["notify"][] = $model->name . " is not allowed to edit";
                $notify["notify"][] = "It has been already given the final approval for this Master Examination Assessment Structure.";

                $response["notify"] = $notify;

                return $this->repository->handleResponse($response);
            }
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
        $model = ScrutinyBoard::query()->find($id);

        if ($model) {

            if ($model->type === 2 || $model->{$this->repository->approvalField} !== 1) {

                $model = $this->repository->getValidatedData($model, [
                    "board_name" => "required",
                    "course_id" => "required|exists:courses,course_id",
                    "syllabus_id" => "required|exists:course_syllabi,syllabus_id",
                ], [], [
                    "board_name" => "Examination Assessment Structure Name",
                    "course_id" => "Course",
                    "syllabus_id" => "Syllabus"
                ]);

                if ($this->repository->isValidData) {

                    $response = $this->repository->saveModel($model);

                    if ($response["notify"]["status"] === "success") {

                        $update = $this->repository->updateData($model);

                        if ($update["notify"]["status"] === "failed") {

                            $response["notify"] = array_merge($update["notify"], $response["notify"]);
                        } else {

                            if (request()->post("send_for_approval") == "1") {

                                $model->load(["course"]);
                                $response = $this->repository->startApprovalProcess($model, $this->repository->approvalDefaultStatus, $response);
                                $response["notify"]["status"] = "success";
                            }

                            if ($response["notify"]["status"] === "success") {

                                $response["data"] = $this->repository->getPreparedData($model->syllabus, null, false, $model);
                            }
                        }
                    }
                } else {
                    $response = $model;
                }
            } else {

                $notify = array();
                $notify["status"] = "failed";
                $notify["notify"][] = $model->name . " is not allowed to edit";
                $notify["notify"][] = "It has been already given the final approval for this Master Examination Assessment Structure.";

                $response["notify"] = $notify;
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
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Factory|View
     */
    public function duplicate($id)
    {
        $model = ScrutinyBoard::query()->find($id);

        if ($model) {

            $record = $model->toArray();
            $formMode = "add";
            $formSubmitUrl = "/" . request()->path();
            $formSubmitUrl = URL::to($formSubmitUrl);

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/scrutiny_board/create");
            $urls["listUrl"] = URL::to("/academic/scrutiny_board");

            $this->repository->setPageUrls($urls);

            return view('academic::scrutiny_board.duplicate', compact('formMode', 'formSubmitUrl', 'record'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Update the specified resource in storage.
     * @param int $id
     * @return JsonResponse
     */
    public function duplicateSubmit($id): JsonResponse
    {
        $model = ScrutinyBoard::query()->find($id);

        if ($model) {

            $boardName = request()->post("board_name");

            if ($boardName !== null) {

                DB::beginTransaction();
                try {

                    $replica = $this->repository->replicate($model);
                    $replica->board_name = $boardName;

                    if ($model->type === 1) {

                        $replica->{$this->repository->approvalField} = null;
                        $replica->{$this->repository->statusField} = 0;
                    }

                    $replica->save();

                    $success = true;
                } catch (Exception $ex) {

                    $success = false;

                    $response = [];
                    $response["notify"]["status"] = "failed";
                    $response["notify"]["notify"][] = "Examination Assessment Structure details saving was failed.";
                }

                if ($success) {

                    DB::commit();
                } else {

                    DB::rollBack();
                }
            } else {

                $notify = array();
                $notify["status"] = "failed";
                $notify["notify"][] = "Details saving was failed. Examination Assessment Structure Name Required.";

                $response["notify"] = $notify;
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
        $model = ScrutinyBoard::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = ScrutinyBoard::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getData(Request $request): JsonResponse
    {
        if ($request->expectsJson()) {

            $id = $request->post("id");
            $academicCalendarId = $request->post("academic_calendar_id");
            $syllabusId = $request->post("syllabus_id");
            $withPeople = $request->post("with_people");
            $withQuestions = $request->post("with_questions");

            $sBModelValid = true;
            $model = null;
            if ($id !== null) {

                $model = ScrutinyBoard::query()->find($id);

                if (!$model) {

                    $sBModelValid = false;
                }
            }

            $data = [];
            if ($sBModelValid) {

                $academicCalendar = null;
                if ($academicCalendarId) {

                    $academicCalendar = AcademicCalendar::query()->find($academicCalendarId);
                }

                $syllabus = CourseSyllabus::query()->find($syllabusId);

                if ($syllabus) {

                    if ($withPeople === "Y") {

                        $withPeople = true;
                    } else {
                        $withPeople = false;
                    }

                    if ($withQuestions === "Y") {

                        $withQuestions = true;
                    } else {
                        $withQuestions = false;
                    }

                    $this->repository->withQuestions = $withQuestions;
                    $data = $this->repository->getPreparedData($syllabus, $academicCalendar, $withPeople, $model);

                    $notify = [];
                    $notify["status"] = "success";
                } else {

                    $notify = [];
                    $notify["status"] = "failed";
                    if (!$academicCalendar) {

                        $notify["notify"][] = "Requested with invalid academic calendar.";

                    }

                    if (!$syllabus) {

                        $notify["notify"][] = "Requested with invalid syllabus.";
                    }
                }
            } else {

                $notify = [];
                $notify["status"] = "failed";
                $notify["notify"][] = "Requested with an invalid scrutiny board record.";
            }

            $response = [];
            $response["notify"] = $notify;
            $response["data"] = $data;

            return response()->json($response, 201);
        }
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
            $type = $request->post("type");

            $query = ScrutinyBoard::query()
                ->select(["id", "board_name"])
                ->where("status", "=", "1")
                ->orderBy("board_name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($searchText !== null) {
                $query = $query->where("board_name", "LIKE", "%" . $searchText . "%");
            }

            if ($type !== null) {
                $query = $query->where("type", "LIKE", $type);
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
        $model = ScrutinyBoard::query()->find($id);
        return $this->repository->updateStatus($model, $this->repository->statusField, "", "remarks");
    }

    public function verification($id)
    {
        $model = ScrutinyBoard::with(["course"])->find($id);

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
        $model = ScrutinyBoard::with(["course"])->find($id);

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
        $model = ScrutinyBoard::with(["course"])->find($id);

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
        $model = ScrutinyBoard::with(["course"])->find($id);

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
        $model = new ScrutinyBoard();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new ScrutinyBoard();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
