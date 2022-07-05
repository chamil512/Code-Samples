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
use Modules\Academic\Entities\CourseSyllabus;
use Modules\Academic\Entities\Course;
use Modules\Academic\Repositories\CourseSyllabusRepository;

class CourseSyllabusController extends Controller
{
    private CourseSyllabusRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new CourseSyllabusRepository();
    }

    /**
     * Display a listing of the resource.
     * @param int $courseId
     * @return Factory|View
     */
    public function index($courseId)
    {
        $cc = Course::query()->find($courseId);

        $ccTitle = "";
        if ($cc) {
            $ccTitle = $cc["course_name"];
        } else {
            abort(404, "Course not available");
        }

        $pageTitle = $ccTitle . " | Course Syllabuses";

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new CourseSyllabus());

        $this->repository->setColumns("id", "syllabus_name", "slqf_version", "entry_criteria", "syllabus_modules", "default_status", "syllabus_status", "approval_status", "created_at")
            ->setColumnLabel("syllabus_name", "Course Syllabus")
            ->setColumnLabel("syllabus_status", "Status")

            ->setColumnDBField("slqf_version", "slqf_version_id")
            ->setColumnFKeyField("slqf_version", "slqf_version_id")
            ->setColumnRelation("slqf_version", "slqfVersion", "version_name")

            ->setColumnDisplay("course", array($this->repository, 'displayRelationAs'), ["course", "course_id", "course_name", URL::to("/academic/course/view/")])
            ->setColumnDisplay("slqf_version", array($this->repository, 'displayRelationAs'), ["slqf_version", "slqf_version_id", "name"])
            ->setColumnDisplay("default_status", array($this->repository, 'displayStatusAs'), [$this->repository->defaultStatuses])
            ->setColumnDisplay("syllabus_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses, "", "", true])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnDisplay("approval_status", array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])
            ->setColumnDisplay("entry_criteria", array($this->repository, 'displayListButtonAs'), ["Entry Criteria", URL::to("/academic/syllabus_entry_criteria/")])
            ->setColumnDBField("entry_criteria", $this->repository->primaryKey)
            ->setColumnDisplay("syllabus_modules", array($this->repository, 'displayListButtonAs'), ["Syllabus Modules", URL::to("/academic/syllabus_module/")])
            ->setColumnDBField("syllabus_modules", $this->repository->primaryKey)

            ->setColumnFilterMethod("syllabus_name")
            ->setColumnFilterMethod("default_status", "select", $this->repository->defaultStatuses)
            ->setColumnFilterMethod("syllabus_status", "select", $this->repository->statuses)

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false)

            ->setColumnDBField("syllabus_modules", "syllabus_id");

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();
            $tableTitle = $ccTitle . " | Course Syllabuses | Trashed";

            $this->repository->setUrl("list", "/academic/course_syllabus/" . $courseId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("edit", "delete");
        } else {
            $query = $this->repository->model::query();
            $tableTitle = $ccTitle . " | Course Syllabuses";

            $this->repository->setCustomControllerUrl("/academic/course_syllabus", ["list"], false)
                ->setUrl("trashList", "/academic/course_syllabus/trash/" . $courseId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("view", "trashList", "trash", "export")
                ->setRowActionBeforeButton(URL::to("/academic/course_syllabus/duplicate/"), "Duplicate", "", "fa fa-copy");

        }

        $this->repository->setUrl("add", "/academic/course_syllabus/create/" . $courseId);
        $query = $query->where(["course_id" => $courseId]);

        $query = $query->with(["slqfVersion"]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param int $courseId
     * @return Factory|View
     */
    public function trash($courseId)
    {
        $this->trash = true;
        return $this->index($courseId);
    }

    /**
     * Show the form for creating a new resource.
     * @param $courseId
     * @return Factory|View
     */
    public function create($courseId)
    {
        $course = Course::query()->find($courseId);
        if (!$course) {
            abort(404, "Course not available");
        }

        $model = new CourseSyllabus();
        $model->course = $course;
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/course_syllabus/" . $courseId);

        $this->repository->setPageUrls($urls);

        $gradings = $this->repository->getGradingPoints($model);

        return view('academic::course_syllabus.create', compact('formMode', 'formSubmitUrl', 'record', 'gradings'));
    }

    /**
     * Store a newly created resource in storage.
     * @param $courseId
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store($courseId): JsonResponse
    {
        $course = Course::query()->find($courseId);
        if (!$course) {
            abort(404, "Course not available");
        }

        $model = new CourseSyllabus();
        $model = $this->repository->getValidatedData($model, [
            "slqf_version_id" => "required|exists:slqf_versions,slqf_version_id",
            "syllabus_name" => "required",
            "default_status" => "required",
        ], [], ["slqf_version_id" => "Slqf Version"]);

        if ($this->repository->isValidData) {
            $model->course_id = $courseId;

            //set course_status as 0 when inserting the record
            $model->syllabus_status = 0;

            $response = $this->repository->saveModel($model);

            if ($response["notify"]["status"] == "success") {
                $this->repository->updateGradingPoints($model);

                if ($model->default_status == "1") {
                    $this->repository->resetOtherVersionDefault($model->course_id, $model->syllabus_id);
                }

                if (request()->post("send_for_approval") == "1") {

                    $response = $this->repository->startApprovalProcess($model, 0, $response);
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
        $model = CourseSyllabus::withTrashed()->with([
            "based",
            "course",
            "slqfVersion",
            "createdUser",
            "updatedUser",
            "deletedUser"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $controllerUrl = URL::to("/academic/course_syllabus/");

            $urls = [];
            $urls["addUrl"] = $controllerUrl . "/create/" . $model->course_id;
            $urls["listUrl"] = $controllerUrl . "/" . $model->course_id;
            $urls["editUrl"] = $controllerUrl . "/edit/" . $model->id;
            $urls["adminUrl"] = URL::to("/admin/admin/view/");
            $urls["courseViewUrl"] = URL::to("/academic/course/view/");
            $urls["recordHistoryUrl"] = $this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);
            $urls["approvalHistoryUrl"] = $this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);

            $this->repository->setPageUrls($urls);

            $statusInfo = [];
            $statusInfo["status"] = $this->repository->getStatusInfo($model, $this->repository->statusField, $this->repository->statuses);
            $statusInfo["approval_status"] = $this->repository->getStatusInfo($model, $this->repository->approvalField, $this->repository->approvalStatuses);
            $statusInfo["default_status"] = $this->repository->getStatusInfo($model, "default_status", $this->repository->defaultStatuses);

            $curriculum = $this->repository->getCurriculum($model->syllabusModules->toArray());

            $gradings = $this->repository->getGradingPoints($model, true);

            return view('academic::course_syllabus.view', compact('record', 'statusInfo', 'gradings', 'curriculum'));
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
        $model = CourseSyllabus::with(["course", "slqfVersion"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/course_syllabus/create/" . $model->course_id);
            $urls["listUrl"] = URL::to("/academic/course_syllabus/" . $model->course_id);

            $this->repository->setPageUrls($urls);

            $gradings = $this->repository->getGradingPoints($model);

            return view('academic::course_syllabus.create', compact('formMode', 'formSubmitUrl', 'record', 'gradings'));
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
        $model = CourseSyllabus::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "slqf_version_id" => "required|exists:slqf_versions,slqf_version_id",
                "syllabus_name" => "required",
                "default_status" => "required",
            ], [], ["slqf_version_id" => "Slqf Version"]);

            if ($this->repository->isValidData) {
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] == "success") {
                    $this->repository->updateGradingPoints($model);

                    if ($model->default_status == "1") {
                        $this->repository->resetOtherVersionDefault($model->course_id, $model->syllabus_id);
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
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Factory|View
     */
    public function duplicate($id)
    {
        $model = CourseSyllabus::query()->find($id);

        if ($model) {

            $record = $model->toArray();
            $formMode = "add";
            $formSubmitUrl = "/" . request()->path();
            $formSubmitUrl = URL::to($formSubmitUrl);

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/course_syllabus/create/" . $model->course_id);
            $urls["listUrl"] = URL::to("/academic/course_syllabus/" . $model->course_id);

            $this->repository->setPageUrls($urls);

            return view('academic::course_syllabus.duplicate', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = CourseSyllabus::query()->find($id);

        if ($model) {

            $syllabusName = request()->post("syllabus_name");

            if ($syllabusName !== null) {

                DB::beginTransaction();
                try {

                    $replica = $this->repository->duplicate($model);
                    $replica->syllabus_name = $syllabusName;
                    $replica->type = 2;
                    $replica->default_status = 0;
                    $replica->remarks = "";
                    $replica->{$this->repository->statusField} = 0;
                    $replica->{$this->repository->approvalField} = null;

                    $replica->save();

                    $success = true;

                    $notify = array();
                    $notify["status"] = "success";
                    $notify["notify"][] = "Successfully saved the details";

                    $response["notify"] = $notify;
                } catch (Exception $ex) {

                    $success = false;

                    $response = [];
                    $response["notify"]["error"] = $ex->getMessage();
                    $response["notify"]["line"] = $ex->getLine();
                    $response["notify"]["status"] = "failed";
                    $response["notify"]["notify"][] = "Course Syllabus details saving was failed.";
                }

                if ($success) {

                    DB::commit();
                } else {

                    DB::rollBack();
                }
            } else {

                $notify = array();
                $notify["status"] = "failed";
                $notify["notify"][] = "Details saving was failed. Course Syllabus Name Required.";

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
        $model = CourseSyllabus::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = CourseSyllabus::withTrashed()->find($id);

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
            $courseId = $request->post("course_id");
            $limit = $request->post("limit");

            $query = CourseSyllabus::query()
                ->select("syllabus_id", "syllabus_name", "course_id")
                ->where("syllabus_status", "=", "1")
                ->orderBy("syllabus_name");

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

                    $query->where("syllabus_name", "LIKE", "%" . $searchText . "%");
                });
            }

            if ($courseId != "") {
                if (is_array($courseId) && count($courseId) > 0) {

                    $query = $query->whereIn("course_id", $courseId);
                } else {
                    $query = $query->where("course_id", $courseId);
                }
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("syllabus_id", $idNot);
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
        $model = CourseSyllabus::query()->find($id);
        return $this->repository->updateStatus($model, $this->repository->statusField, "", "remarks");
    }

    public function verification($id)
    {
        $model = CourseSyllabus::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @throws ValidationException
     */
    public function verificationSubmit($id)
    {
        $model = CourseSyllabus::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalSAR($id)
    {
        $model = CourseSyllabus::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "pre_approval_sar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @throws ValidationException
     */
    public function preApprovalSARSubmit($id)
    {
        $model = CourseSyllabus::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "pre_approval_sar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalRegistrar($id)
    {
        $model = CourseSyllabus::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "pre_approval_registrar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @throws ValidationException
     */
    public function preApprovalRegistrarSubmit($id)
    {
        $model = CourseSyllabus::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "pre_approval_registrar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalVC($id)
    {
        $model = CourseSyllabus::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "pre_approval_vc");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @throws ValidationException
     */
    public function preApprovalVCSubmit($id)
    {
        $model = CourseSyllabus::query()->find($id);

        if ($model) {
            return $this->repository->processApprovalSubmission($model, "pre_approval_vc");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function approval($id)
    {
        $model = CourseSyllabus::query()->find($id);

        if ($model) {
            return $this->repository->renderApprovalView($model, "approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @throws ValidationException
     */
    public function approvalSubmit($id)
    {
        $model = CourseSyllabus::query()->find($id);

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
        $model = new CourseSyllabus();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new CourseSyllabus();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
