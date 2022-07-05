<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\Lecturer;
use Modules\Academic\Repositories\LecturerAvailabilityHourRepository;
use Modules\Academic\Repositories\LecturerRepository;
use Modules\Academic\Repositories\PersonBankingInformationRepository;
use Modules\Academic\Repositories\PersonContactInformationRepository;
use Modules\Academic\Repositories\PersonDocumentRepository;

class LecturerController extends Controller
{
    private LecturerRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new LecturerRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Lecturers");

        $this->repository->initDatatable(new Lecturer());

        $this->repository->setColumns("id", "staff_type", "faculty", "department", "name", "nic_no", "contact_info", "courses", "status", "approval_status", "created_at")
            ->setColumnLabel("status", "Status")

            ->setColumnDBField("faculty", "faculty_id")
            ->setColumnFKeyField("faculty", "faculty_id")
            ->setColumnRelation("faculty", "faculty", "faculty_name")

            ->setColumnDBField("department", "dept_id")
            ->setColumnFKeyField("department", "dept_id")
            ->setColumnRelation("department", "department", "dept_name")

            ->setColumnDisplay("faculty", array($this->repository, 'displayRelationAs'), ["faculty", "faculty_id", "name"])
            ->setColumnDisplay("department", array($this->repository, 'displayRelationAs'), ["department", "dept_id", "name"])
            ->setColumnDisplay("staff_type", array($this->repository, 'displayStatusAs'), [$this->repository->staffTypes])
            ->setColumnDisplay("contact_info", array($this->repository, 'displayContactInfoAs'))
            ->setColumnDisplay("courses", array($this->repository, 'displayListButtonAs'), ["Courses", URL::to("/academic/lecturer_course/")])
            ->setColumnDisplay("status", array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses, "", "", true])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnDisplay("approval_status", array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])

            ->setColumnFilterMethod("faculty", "select", URL::to("/academic/faculty/search_data"))
            ->setColumnFilterMethod("department", "select", [
                "options" => URL::to("/academic/department/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "faculty",
                        "param" => "faculty_id",
                    ]
                ],
            ])

            ->setColumnFilterMethod("staff_type", "select", $this->repository->staffTypes)
            ->setColumnFilterMethod("status", "select", $this->repository->statuses)

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false)
            ->setColumnSearchability("updated_at", false)
            ->setColumnOrderability("contact_info", false)
            ->setColumnDBField("name", "name_with_init")
            ->setColumnDBField("contact_info", "CONCAT(contact_no, ' ', email)")
            ->setColumnDBField("courses", $this->repository->primaryKey);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Lecturers | Trashed")
                ->enableViewData("list", "view", "restore", "export")
                ->disableViewData("edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Lecturers")
                ->enableViewData("view", "trashList", "view", "trash", "export");
        }

        $query->with(["faculty", "department"]);

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
        $model = new Lecturer();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = URL::to("/" . request()->path());

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/lecturer");

        $this->repository->setPageUrls($urls);

        return view('academic::lecturer.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(): JsonResponse
    {
        $model = new Lecturer();

        $model = $this->repository->getValidatedData($model, [
            // "staff_type" => "required|digits:1",
            "faculty_id" => "required|exists:faculties,faculty_id",
            "dept_id" => "required|exists:departments,dept_id",
            "academic_carder_position_id" => "required|exists:academic_carder_positions,id",
            "title_id" => "required|exists:honorific_titles,title_id",
            "given_name" => "required",
            "surname" => "required",
            "name_in_full" => "required",
            "name_with_init" => "required",
            "date_of_birth" => "required|date",
            "nic_no" => [Rule::requiredIf(function () {
                return request()->post("passport_no") == "";
            })],
            "passport_no" => [Rule::requiredIf(function () {
                return request()->post("nic_no") == "";
            })],
            "perm_address" => "required",
            "perm_work_address" => "",
            "contact_no" => "required|digits_between:8,15",
            "email" => "required",
            "qualification_id" => "required|exists:academic_qualifications,qualification_id",
            "qualification_level_id" => "",
            "university_id" => "required|exists:universities,university_id",
            "qualified_year" => "required|digits:4",
        ], [], ["staff_type" => "Staff Type", "faculty_id" => "Faculty", "dept_id" => "Department",
            "academic_carder_position_id" => "Academic Carder Position", "title_id" => "Title",
            "name_with_init" => "Name with initials", "perm_address" => "Permanent address",
            "qualification_id" => "Highest Qualification", "university_id" => "Qualified University",
            "qualified_year" => "Qualified Year"]);
        if ($this->repository->isValidData) {
            $model->status = "0";
            $model->staff_type = "2";
            $response = $this->repository->saveModel($model);
            if ($response["notify"]["status"] == "success") {
                $cIRepo = new PersonContactInformationRepository();
                $cIRepo->update($model);

                $docRepo = new PersonDocumentRepository();
                $docRepo->upload_dir = "public/lecturer_documents/";
                $docRepo->update($model);

                $bIRepo = new PersonBankingInformationRepository();
                $bIRepo->update($model);

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
     * @param int $id
     * @return Factory|View
     */
    public function show($id)
    {
        $model = Lecturer::withTrashed()->with([
            "faculty",
            "department",
            "carderPosition",
            "contactInfo",
            "bankingInfo",
            "documents",
            "courses",
            "qualification",
            "qualificationLevel",
            "university",
            "title",
            "createdUser",
            "updatedUser",
            "deletedUser"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $controllerUrl = URL::to("/academic/lecturer/");

            $urls = [];
            $urls["addUrl"] = URL::to($controllerUrl . "/create");
            $urls["editUrl"] = URL::to($controllerUrl . "/edit/" . $id);
            $urls["listUrl"] = URL::to($controllerUrl);
            $urls["adminUrl"] = URL::to("/admin/admin/view/");
            $urls["coursesUrl"] = URL::to("/academic/lecturer_course/" . $id);
            $urls["atUrl"] = URL::to("/academic/lecturer_availability_term/" . $id);
            $urls["paymentPlanUrl"] = URL::to("/academic/lecturer_payment_plan/" . $id);
            $urls["rosterUrl"] = URL::to("/academic/lecturer_roster/" . $id);
            $urls["timetableUrl"] = URL::to("/academic/lecturer/timetable/" . $id);
            $urls["recordHistoryUrl"] = $this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);
            $urls["approvalHistoryUrl"] = $this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);
            $urls["downloadUrl"] = URL::to("/academic/lecturer/download_document/" . $id);

            $this->repository->setPageUrls($urls);

            $statusInfo = [];
            $statusInfo["status"] = $this->repository->getStatusInfo($model);
            $statusInfo["approval_status"] = $this->repository->getStatusInfo($model, "approval_status", $this->repository->approvalStatuses);
            $statusInfo["staff_type"] = $this->repository->getStatusInfo($model, "staff_type", $this->repository->staffTypes);

            return view('academic::lecturer.view', compact('record', 'statusInfo'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Factory|View
     */
    public function timetable($id)
    {
        $model = Lecturer::withTrashed()->find($id);

        if ($model) {
            $record = $model->toArray();

            return view('academic::lecturer.lecturer_timetable', compact('record'));
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Show the specified resource.
     * @return Factory|View
     */
    public function ownTimetable()
    {
        $lecturerId = auth("admin")->user()->lecturer_id;

        if ($lecturerId) {

            return $this->timetable($lecturerId);
        } else {
            abort(403, "You need to be a lecturer to view your timetable.");
        }
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Factory|View
     */
    public function edit($id)
    {
        $model = Lecturer::with(["faculty", "department", "carderPosition", "contactInfo", "documents", "bankingInfo",
            "qualification", "qualificationLevel", "university", "title"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = URL::to("/" . request()->path());

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/lecturer/create");
            $urls["listUrl"] = URL::to("/academic/lecturer");
            $urls["downloadUrl"] = URL::to("/academic/lecturer/download_document/" . $id) . "/";

            $this->repository->setPageUrls($urls);

            return view('academic::lecturer.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = Lecturer::query()->find($id);

        if ($model) {

            $model = $this->repository->getValidatedData($model, [
                // "staff_type" => "required|digits:1",
                "faculty_id" => "required|exists:faculties,faculty_id",
                "dept_id" => "required|exists:departments,dept_id",
                "academic_carder_position_id" => "required|exists:academic_carder_positions,id",
                "title_id" => "required|exists:honorific_titles,title_id",
                "given_name" => "required",
                "surname" => "required",
                "name_in_full" => "required",
                "name_with_init" => "required",
                "date_of_birth" => "required|date",
                "nic_no" => [Rule::requiredIf(function () {
                    return request()->post("passport_no") == "";
                })],
                "passport_no" => [Rule::requiredIf(function () {
                    return request()->post("nic_no") == "";
                })],
                "perm_address" => "required",
                "perm_work_address" => "",
                "contact_no" => "required|digits_between:8,15",
                "email" => "required",
                "qualification_id" => "required|exists:academic_qualifications,qualification_id",
                "qualification_level_id" => "",
                "university_id" => "required|exists:universities,university_id",
                "qualified_year" => "required|digits:4",
            ], [], ["staff_type" => "Staff Type", "faculty_id" => "Faculty", "dept_id" => "Department",
                "academic_carder_position_id" => "Academic Carder Position", "title_id" => "Title",
                "name_with_init" => "Name with initials", "perm_address" => "Permanent address",
                "qualification_id" => "Highest Qualification", "university_id" => "Qualified University"]);
            if ($this->repository->isValidData) {
                $model->staff_type = "2";
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] == "success") {
                    $cIRepo = new PersonContactInformationRepository();
                    $cIRepo->update($model);

                    $docRepo = new PersonDocumentRepository();
                    $docRepo->upload_dir = "public/lecturer_documents/";
                    $docRepo->update($model);

                    $bIRepo = new PersonBankingInformationRepository();
                    $bIRepo->update($model);

                    $response["data"]["contactInfo"] = $model->contactInfo()->get()->toArray();
                    $response["data"]["documents"] = $model->documents()->get()->toArray();
                    $response["data"]["bankingInfo"] = $model->bankingInfo()->get()->toArray();

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
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = Lecturer::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = Lecturer::withTrashed()->find($id);

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
            $facultyId = $request->post("faculty_id");
            $deptId = $request->post("dept_id");
            $moduleId = $request->post("module_id");
            $staffType = $request->post("staff_type");

            $query = Lecturer::query()
                ->select("id", "given_name", "surname", "title_id")
                ->orderBy("given_name");

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
                    $query->where(function ($query) use ($searchText) {
                        $query->where("name_with_init", "LIKE", "%" . $searchText . "%")
                            ->orWhere("name_in_full", "LIKE", "%" . $searchText . "%")
                            ->orWhere("given_name", "LIKE", "%" . $searchText . "%")
                            ->orWhere("surname", "LIKE", "%" . $searchText . "%");
                    });
                });
            }

            if ($staffType) {
                $query->where("staff_type", $staffType);
            }

            if ($facultyId) {
                if (is_array($facultyId) && count($facultyId) > 0) {

                    $query->whereIn("faculty_id", $facultyId);
                } else {
                    $query->where("faculty_id", $facultyId);
                }
            }

            if ($deptId) {
                if (is_array($deptId) && count($deptId) > 0) {

                    $query->whereIn("dept_id", $deptId);
                } else {
                    $query->where("dept_id", $deptId);
                }
            }

            if ($moduleId) {
                $query->whereHas("modules", function ($query) use($moduleId) {

                    if (is_array($moduleId) && count($moduleId) > 0) {

                        $query->whereIn("module_id", $moduleId);
                    } else {
                        $query->where("module_id", $moduleId);
                    }
                });
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query->whereNotIn("id", $idNot);
            }

            $data = $query->get();

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }

    /**
     * Download lecturer's document
     * @param $id
     * @param $documentId
     * @return mixed
     */
    public function downloadDocument($id, $documentId)
    {
        $model = Lecturer::query()->find($id);

        if ($model) {
            $docRepo = new PersonDocumentRepository();
            $docRepo->upload_dir = "public/lecturer_documents/";
            return $docRepo->triggerDownloadDocument($model, $documentId);
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return JsonResponse
     */
    public function changeStatus($id): JsonResponse
    {
        $model = Lecturer::query()->find($id);
        return $this->repository->updateStatus($model, $this->repository->statusField, "", "remarks");
    }

    public function verification($id)
    {
        $model = Lecturer::query()->find($id);

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
        $model = Lecturer::query()->find($id);

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
        $model = Lecturer::query()->find($id);

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
        $model = Lecturer::query()->find($id);

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
        $model = Lecturer::query()->find($id);

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
        $model = Lecturer::query()->find($id);

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
        $model = Lecturer::query()->find($id);

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
        $model = Lecturer::query()->find($id);

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
        $model = Lecturer::query()->find($id);

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
        $model = Lecturer::query()->find($id);

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
        $model = new Lecturer();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new Lecturer();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
