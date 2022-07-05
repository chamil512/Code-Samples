<?php

namespace Modules\Academic\Http\Controllers;

use App\Repositories\BaseRepository;
use App\Repositories\SystemApprovalRepository;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\Course;
use Modules\Academic\Entities\CourseCategory;
use Modules\Academic\Repositories\CourseDocumentRepository;
use Modules\Academic\Repositories\CourseRepository;
use Modules\Admin\Repositories\AdminActivityRepository;
use Modules\Accounting\Entities\SegmentsE;

class CourseController extends Controller
{
    private CourseRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new CourseRepository();
    }

    /**
     * Display a listing of the resource.
     * @param int $courseCategoryId
     * @return Factory|View
     */
    public function index($courseCategoryId = 0)
    {
        $ccTitle = "";
        if ($courseCategoryId) {
            $cc = CourseCategory::query()->find($courseCategoryId);

            if ($cc) {
                $ccTitle = $cc["category_name"];
            } else {
                abort(404, "Course category not available");
            }
        }

        $pageTitle = "Courses";
        if ($ccTitle != "") {
            $pageTitle = $ccTitle . " | " . $pageTitle;
        }

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new Course());

        $this->repository->setColumns("id", "course_name", "course_code", "department", "course_type", "course_category", "course_modules", "course_syllabuses", "course_status", "approval_status", "created_at")
            ->setColumnLabel("course_code", "Code")
            ->setColumnLabel("course_name", "Course")
            ->setColumnLabel("course_category", "Category")
            ->setColumnLabel("course_syllabuses", "Syllabuses")
            ->setColumnLabel("course_status", "Status")

            ->setColumnDBField("department", "dept_id")
            ->setColumnFKeyField("department", "dept_id")
            ->setColumnRelation("department", "department", "dept_name")

            ->setColumnDBField("course_type", "course_type_id")
            ->setColumnFKeyField("course_type", "course_type_id")
            ->setColumnRelation("course_type", "courseType", "course_type")

            ->setColumnDBField("course_category", "course_category_id")
            ->setColumnFKeyField("course_category", "course_category_id")
            ->setColumnRelation("course_category", "courseCategory", "category_name")

            ->setColumnDBField("course_modules", "course_id")
            ->setColumnDBField("course_syllabuses", "course_id")

            ->setColumnDisplay("department", array($this->repository, 'displayRelationAs'), ["department", "dept_id", "dept_name"])
            ->setColumnDisplay("course_type", array($this->repository, 'displayRelationAs'), ["course_type", "id", "name"])
            ->setColumnDisplay("course_category", array($this->repository, 'displayRelationAs'), ["course_category", "course_category_id", "category_name", URL::to("/academic/course/category/")])
            ->setColumnDisplay("course_modules", array($this->repository, 'displayListButtonAs'), ["Course Modules", URL::to("/academic/course_module/")])
            ->setColumnDisplay("course_syllabuses", array($this->repository, 'displayListButtonAs'), ["Course Syllabuses", URL::to("/academic/course_syllabus/")])
            ->setColumnDisplay("course_status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses, "", "", true])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnDisplay("approval_status", array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])

            ->setColumnFilterMethod("department", "select", [
                "options" => URL::to("/academic/department/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "faculty",
                        "param" => "faculty_id",
                    ]
                ],
            ])
            ->setColumnFilterMethod("course_type", "select", URL::to("/academic/course_type/search_data"))
            ->setColumnFilterMethod("course_category", "select", URL::to("/academic/course_category/search_data"))
            ->setColumnFilterMethod("course_status", "select", $this->repository->statuses)

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false)

            ->setColumnDBField("course_modules", $this->repository->primaryKey)
            ->setColumnDBField("course_syllabuses", $this->repository->primaryKey);


        $this->repository->setCustomFilters("faculty")
            ->setColumnDBField("faculty", "dept_id", true)
            ->setColumnFKeyField("faculty", "dept_id", true)
            ->setColumnRelation("faculty", "department", "dept_name", true)
            ->setColumnCoRelation("faculty", "faculty", "faculty_name", "faculty_id", "faculty_id", true)
            ->setColumnFilterMethod("faculty", "select", URL::to("/academic/faculty/search_data"), true);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = "Courses | Trashed";
            if ($ccTitle != "") {
                $tableTitle = $ccTitle . " | " . $tableTitle;

                $this->repository->setUrl("list", "/academic/course/category/" . $courseCategoryId);
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "view", "restore", "export")
                ->disableViewData("edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = "Courses";
            if ($ccTitle != "") {
                $tableTitle = $ccTitle . " | " . $tableTitle;

                $this->repository->setUrl("trashList", "/academic/course/category/trash/" . $courseCategoryId);
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("view", "trashList", "trash", "export");
        }

        if ($courseCategoryId) {
            $this->repository->unsetColumns("course_category");
            $query = $query->where(["course_category_id" => $courseCategoryId]);
        }

        $query = $query->with(["department", "courseCategory", "courseType"]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param $courseCategoryId
     * @return Factory|View
     */
    public function trash($courseCategoryId = 0)
    {
        $this->trash = true;
        return $this->index($courseCategoryId);
    }

    /**
     * Show the form for creating a new resource.
     * @return Factory|View
     */
    public function create()
    {
        $model = new Course();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/course");

        $this->repository->setPageUrls($urls);

        $segments = SegmentsE::getActiveAllSubSegmentDepartment(null);

        return view('academic::course.create', compact('formMode', 'formSubmitUrl', 'record', 'segments'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(): JsonResponse
    {
        $model = new Course();

        $model = $this->repository->getValidatedData($model, [
            "dept_id" => "required|exists:departments,dept_id",
            "slqf_id" => "required|exists:slqf_structures,slqf_id",
            "course_category_id" => "required|exists:course_categories,course_category_id",
            "course_type_id" => "required|exists:course_types,id",
            "course_name" => "required",
            "transcript_name" => "required",
            "abbreviation" => "required",
            "course_du_years" => "required",
            "course_du_months" => "required",
            "course_du_dates" => "required",
            "supplementary_status" => "required",
            "course_du_years_ex" => [Rule::requiredIf(function () {
                return request()->post("supplementary_status") == "1";
            })],
            "course_du_months_ex" => [Rule::requiredIf(function () {
                return request()->post("supplementary_status") == "1";
            })],
            "course_du_dates_ex" => [Rule::requiredIf(function () {
                return request()->post("supplementary_status") == "1";
            })],
        ], [], ["dept_id" => "Department", "slqf_id" => "SLQF Structure", "course_category_id" => "Course Category", "course_name" => "Course name", "course_du_years" => "Course Duration Years", "course_du_months" => "Course Duration Months", "course_du_dates" => "Course Duration Days"]);

        if ($this->repository->isValidData) {
            $model->course_code = $this->repository->generateCourseCode($model->dept_id);

            $response = $this->repository->saveModel($model);

            if ($response["notify"]["status"] == "success") {

                $cDRepo = new CourseDocumentRepository();
                $cDRepo->update($model);

                if (request()->post("send_for_approval") == "1") {
                    DB::beginTransaction();
                    $model->{$this->repository->approvalField} = 0;
                    $model->save();

                    $update = $this->repository->triggerApprovalProcess($model);

                    if ($update["notify"]["status"] === "success") {

                        DB::commit();
                    } else {
                        DB::rollBack();

                        if (is_array($update["notify"]) && count($update["notify"]) > 0) {

                            foreach ($update["notify"] as $message) {

                                $response["notify"]["notify"][] = $message;
                            }
                        }
                    }
                }
            }
        } else {
            $response = $model;
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Show the specified resource.
     * @param $id
     * @return Factory|View
     */
    public function show($id)
    {
        $model = Course::with([
            "department",
            "slqf",
            "courseCategory",
            "courseType",
            "createdUser",
            "updatedUser",
            "deletedUser"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $controllerUrl = URL::to("/academic/course/");

            $urls = [];
            $urls["addUrl"] = URL::to($controllerUrl . "/create");
            $urls["editUrl"] = URL::to($controllerUrl . "/edit/" . $id);
            $urls["listUrl"] = URL::to($controllerUrl);
            $urls["adminUrl"] = URL::to("/admin/admin/view/");
            $urls["docUrl"] = URL::to("/academic/course_document/" . $id);
            $urls["recordHistoryUrl"] = $this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);
            $urls["approvalHistoryUrl"] = $this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);

            $this->repository->setPageUrls($urls);

            $statusInfo = [];
            $statusInfo["status"] = $this->repository->getStatusInfo($model);
            $statusInfo["approval_status"] = $this->repository->getStatusInfo($model, "approval_status", $this->repository->approvalStatuses);

            return view('academic::course.view', compact('record', 'statusInfo'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Show the form for editing the specified resource.
     * @param $id
     * @return Factory|View
     */
    public function edit($id)
    {
        $model = Course::with(["department", "slqf", "courseCategory", "courseType", "documents"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/course/create");
            $urls["listUrl"] = URL::to("/academic/course");
            $urls["downloadUrl"] = URL::to("/academic/course_document/download") . "/";

            $this->repository->setPageUrls($urls);

            return view('academic::course.create', compact('formMode', 'formSubmitUrl', 'record'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Update the specified resource in storage.
     * @param $id
     * @return JsonResponse
     * @throws ValidationException
     */
    public function update($id): JsonResponse
    {
        $model = Course::query()->find($id);

        if ($model) {
            $currModel = $model;
            $model = $this->repository->getValidatedData($model, [
                "dept_id" => "required|exists:departments,dept_id",
                "slqf_id" => "required|exists:slqf_structures,slqf_id",
                "course_category_id" => "required|exists:course_categories,course_category_id",
                "course_type_id" => "required|exists:course_types,id",
                "course_name" => "required",
                "transcript_name" => "required",
                "abbreviation" => "required",
                "course_du_years" => "required",
                "course_du_months" => "required",
                "course_du_dates" => "required",
                "supplementary_status" => "required",
                "course_du_years_ex" => [Rule::requiredIf(function () {
                    return request()->post("supplementary_status") == "1";
                })],
                "course_du_months_ex" => [Rule::requiredIf(function () {
                    return request()->post("supplementary_status") == "1";
                })],
                "course_du_dates_ex" => [Rule::requiredIf(function () {
                    return request()->post("supplementary_status") == "1";
                })],
            ], [], ["dept_id" => "Department", "slqf_id" => "SLQF Structure", "course_category_id" => "Course Category", "course_name" => "Course name", "course_du_years" => "Course Duration Years", "course_du_months" => "Course Duration Months", "course_du_dates" => "Course Duration Days"]);

            if ($this->repository->isValidData) {
                if ($currModel->dept_id != $model->dept_id) {
                    $model->dept_code = $this->repository->generateCourseCode($model->dept_id);
                }
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] === "success") {

                    $cDRepo = new CourseDocumentRepository();
                    $cDRepo->update($model);

                    $response["data"]["documents"] = $model->documents()->get()->toArray();

                    if (request()->post("send_for_approval") == "1") {

                        DB::beginTransaction();
                        $model->{$this->repository->approvalField} = 0;
                        $model->save();

                        $update = $this->repository->triggerApprovalProcess($model);

                        if ($update["notify"]["status"] === "success") {

                            DB::commit();

                        } else {
                            DB::rollBack();

                            if (is_array($update["notify"]) && count($update["notify"]) > 0) {

                                foreach ($update["notify"] as $message) {

                                    $response["notify"]["notify"][] = $message;
                                }
                            }
                        }
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
     * @param $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = Course::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = Course::withTrashed()->find($id);

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
            $deptId = $request->post("dept_id");
            $courseCategoryId = $request->post("course_category_id");
            $courseTypeId = $request->post("course_type_id");
            $lecturerId = $request->post("lecturer_id");
            $limit = $request->post("limit");

            $query = Course::query()
                ->select("course_id", "course_name", "course_code", "slqf_id")
                ->where("course_status", "=", "1")
                ->orderBy("course_name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($deptId != "") {
                if (is_array($deptId) && count($deptId) > 0) {

                    $query = $query->whereIn("dept_id", $deptId);
                } else {
                    $query = $query->where("dept_id", $deptId);
                }
            }

            if ($courseCategoryId != "") {
                $query = $query->where("course_category_id", $courseCategoryId);
            }

            if ($courseTypeId != "") {
                $query = $query->where("course_type_id", $courseTypeId);
            }

            if ($lecturerId !== null) {
                $query->whereHas("courseLecturers", function ($query) use ($lecturerId) {

                    $query->where("lecturer_id", $lecturerId);
                });
            }

            if ($searchText != "") {
                $query = $query->where("course_name", "LIKE", "%" . $searchText . "%");
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("course_id", $idNot);
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
        $model = Course::query()->find($id);
        return $this->repository->updateStatus($model, $this->repository->statusField, "", "remarks");
    }

    public function verification($id)
    {
        $model = Course::query()->find($id);

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
        $model = Course::query()->find($id);

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
        $model = Course::query()->find($id);

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
        $model = Course::query()->find($id);

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
        $model = new Course();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new Course();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
