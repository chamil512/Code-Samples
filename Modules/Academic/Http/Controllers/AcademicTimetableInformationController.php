<?php

namespace Modules\Academic\Http\Controllers;

use App\Helpers\Helper;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicTimetable;
use Modules\Academic\Entities\AcademicTimetableInformation;
use Modules\Academic\Entities\CourseModule;
use Modules\Academic\Repositories\AcademicTimetableInformationRepository;
use Modules\Academic\Repositories\AcademicTimetableRepository;
use Modules\Academic\Repositories\CourseModuleRepository;

class AcademicTimetableInformationController extends Controller
{
    private AcademicTimetableInformationRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new AcademicTimetableInformationRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $pageTitle = "";

        $type = request()->route()->getAction()['type'];

        if ($type === 2) {

            $pageTitle = "Cancel Requests | Academic Timetable Slots";
        } elseif ($type === 3) {

            $pageTitle = "Reschedule Requests | Academic Timetable Slots";
        } elseif ($type === 4) {

            $pageTitle = "Revision Requests | Academic Timetable Slots";
        } elseif ($type === 5) {

            $pageTitle = "Relief Requests | Academic Timetable Slots";
        }

        $this->repository->setPageTitle($pageTitle);

        $this->repository->setApprovalSteps($type);
        $this->repository->setApprovalUrls($type);
        $this->repository->setApprovalStatuses($type);

        $this->repository->initDatatable(new AcademicTimetableInformation());

        $this->repository->setColumns("id", "course", "batch", "academic_year", "semester", "tt_date", "start_time", "end_time", "hours", "module",
            "delivery_mode", "academic_timetable_id", $this->repository->statusField, $this->repository->approvalField, "cancelled", "rescheduled", "slot_type_remarks")
            ->setColumnLabel("tt_date", "Date")
            ->setColumnLabel("academic_year", "Year")
            ->setColumnLabel("start_time", "Start&nbsp;Time")
            ->setColumnLabel("end_time", "End&nbsp;Time")
            ->setColumnLabel("delivery_mode", "Mode")
            ->setColumnLabel("academic_timetable_id", "Timetable")
            ->setColumnLabel("cancelled", "Rescheduling/Rescheduled")
            ->setColumnLabel("rescheduled", "Cancelled Slot")
            ->setColumnLabel("slot_type_remarks", "Remarks")

            ->setColumnLabel($this->repository->statusField, "Status")
            ->setColumnLabel($this->repository->approvalField, "Approval")

            ->setColumnDBField("module", "module_id")
            ->setColumnFKeyField("module", "module_id")
            ->setColumnRelation("module", "module", "module_name")

            ->setColumnDBField("delivery_mode", "delivery_mode_id")
            ->setColumnFKeyField("delivery_mode", "delivery_mode_id")
            ->setColumnRelation("delivery_mode", "deliveryMode", "mode_name")

            ->setColumnDBField("course", "academic_timetable_information_id")
            ->setColumnFKeyField("course", "academic_timetable_id")
            ->setColumnRelation("course", "timetable", "timetable_name")
            ->setColumnCoRelation("course", "course", "course_name", "course_id")

            ->setColumnDBField("batch", "academic_timetable_information_id")
            ->setColumnFKeyField("batch", "academic_timetable_id")
            ->setColumnRelation("batch", "timetable", "timetable_name")
            ->setColumnCoRelation("batch", "batch", "batch_name", "batch_id")

            ->setColumnDBField("academic_year", "academic_timetable_information_id")
            ->setColumnFKeyField("academic_year", "academic_timetable_id")
            ->setColumnRelation("academic_year", "timetable", "timetable_name")
            ->setColumnCoRelation("academic_year", "academicYear", "year_name", "academic_year_id")

            ->setColumnDBField("semester", "academic_timetable_information_id")
            ->setColumnFKeyField("semester", "academic_timetable_id")
            ->setColumnRelation("semester", "timetable", "timetable_name")
            ->setColumnCoRelation("semester", "semester", "semester_name", "semester_id")

            ->setColumnDBField("cancelled", "cancelled_slot_id")
            ->setColumnFKeyField("cancelled", "academic_timetable_information_id")
            ->setColumnRelation("cancelled", "rescheduling", "tt_date")

            ->setColumnDBField("rescheduled", "academic_timetable_information_id")
            ->setColumnFKeyField("rescheduled", "cancelled_slot_id")
            ->setColumnRelation("rescheduled", "reschedulingCancelled", "tt_date")

            ->setColumnDisplay("cancelled", array($this->repository, 'displayRelationAs'), ["cancelled", "id", "name"])
            ->setColumnDisplay("rescheduled", array($this->repository, 'displayRelationAs'), ["rescheduled", "id", "name"])

            ->setColumnDisplay("course", array($this->repository, 'displayCoRelationAs'), ["course", "id", "name"])
            ->setColumnDisplay("batch", array($this->repository, 'displayCoRelationAs'), ["batch", "id", "name"])
            ->setColumnDisplay("academic_year", array($this->repository, 'displayCoRelationAs'), ["academic_year", "id", "name"])
            ->setColumnDisplay("semester", array($this->repository, 'displayCoRelationAs'), ["semester", "id", "name"])
            ->setColumnDisplay("module", array($this->repository, 'displayRelationAs'), ["module", "module_id", "name"])
            ->setColumnDisplay("delivery_mode", array($this->repository, 'displayRelationAs'), ["delivery_mode", "delivery_mode_id", "name"])
            ->setColumnDisplay($this->repository->statusField, array($this->repository, 'displayStatusAs'), [$this->repository->statuses])
            ->setColumnDisplay($this->repository->approvalField, array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnDisplay("academic_timetable_id", array($this->repository, 'displayListButtonAs'),
                ["Timetable", URL::to("/academic/academic_timetable/view/"), "academic_timetable_id"])

            ->setColumnFilterMethod("tt_date", "date_between")

            ->setColumnFilterMethod("course", "select", [
                "options" => URL::to("/academic/course/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "department",
                        "param" => "dept_id",
                    ]
                ],
            ])

            ->setColumnFilterMethod("batch", "select", [
                "options" => URL::to("/academic/batch/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ]
                ],
            ])

            ->setColumnFilterMethod("module", "select", [
                "options" => URL::to("/academic/course_module/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ]
                ],
            ])

            ->setColumnFilterMethod("academic_timetable_id", "select", [
                "options" => URL::to("/academic/academic_timetable/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "faculty",
                        "param" => "faculty_id",
                    ],
                    [
                        "column" => "department",
                        "param" => "dept_id",
                    ],
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ],
                    [
                        "column" => "batch",
                        "param" => "batch_id",
                    ],
                    [
                        "column" => "academic_year",
                        "param" => "academic_year_id",
                    ],
                    [
                        "column" => "semester",
                        "param" => "semester_id",
                    ],
                ],
            ])

            ->setColumnFilterMethod("academic_year", "select", [
                "options" => URL::to("/academic/academic_year/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ]
                ],
            ])

            ->setColumnFilterMethod("semester", "select", [
                "options" => URL::to("/academic/academic_semester/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "course",
                        "param" => "course_id",
                    ]
                ],
            ])

            ->setColumnFilterMethod("delivery_mode", "select", URL::to("/academic/module_delivery_mode/search_data"))
            ->setColumnFilterMethod($this->repository->statusField, "select", $this->repository->statuses)
            ->setColumnFilterMethod($this->repository->approvalField, "select", $this->repository->approvalStatuses)

            ->setColumnSearchability("created_at", false);

        $this->repository->setCustomFilters("lecturer", "faculty", "department")
            ->setColumnDBField("lecturer", "academic_timetable_information_id", true)
            ->setColumnFKeyField("lecturer", "lecturer_id", true)
            ->setColumnRelation("lecturer", "ttInfoLecturers", "lecturer_id", true)
            ->setColumnCoRelation("lecturer", "lecturer", "name_with_init",
                "lecturer_id", "id", true)
            ->setColumnFilterMethod("lecturer", "select", URL::to("/academic/lecturer/search_data"), true)
            ->setCustomFilterPosition("lecturer", "after")

            ->setColumnDBField("faculty", "academic_timetable_id", true)
            ->setColumnFKeyField("faculty", "academic_timetable_id", true)
            ->setColumnRelation("faculty", "timetable", "timetable_name", true)
            ->setColumnCoRelation("faculty", "faculty", "faculty_name",
                "faculty_id", "faculty_id", true)
            ->setColumnFilterMethod("faculty", "select", URL::to("/academic/faculty/search_data"), true)

            ->setColumnDBField("department", "academic_timetable_id", true)
            ->setColumnFKeyField("department", "academic_timetable_id", true)
            ->setColumnRelation("department", "timetable", "timetable_name", true)
            ->setColumnCoRelation("department", "department", "dept_name",
                "dept_id", "dept_id", true)
            ->setColumnFilterMethod("department", "select", [
                "options" => URL::to("/academic/department/search_data"),
                "basedColumns" =>[
                    [
                        "column" => "faculty",
                        "param" => "faculty_id",
                    ]
                ],
            ], true);

        /*if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();
            $tableTitle = $pageTitle . " | Trashed";

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "view", "restore", "export")
                ->disableViewData("edit", "delete");
        } else {*/
            $query = $this->repository->model::query();
            $tableTitle = $pageTitle;

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("view", "export")
                ->disableViewData("add", "trashList", "trash", "edit");
        //}

        $relations = ["timetable.course", "timetable.batch", "timetable.academicYear", "timetable.semester", "module", "deliveryMode", "ttInfoLecturers"];
        if ($type === 2) {

            $relations[] = "rescheduling";

            $this->repository->unsetColumns("rescheduled");
        } elseif ($type === 3) {

            $relations[] = "reschedulingCancelled";

            $this->repository->unsetColumns("cancelled");
        } else {

            $this->repository->unsetColumns("rescheduled", "cancelled");
        }

        $query->with($relations);
        $query->where("slot_type", $type);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    /*public function trash()
    {
        $this->trash = true;
        return $this->index();
    }*/

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function createCancelRequest(): JsonResponse
    {
        $timetableId = request()->post("academic_timetable_id");
        $timetable = AcademicTimetable::query()->find($timetableId);

        $response = $this->repository->validateTimetable($timetable);

        if ($response["notify"]["status"] === "success") {

            $id = request()->post("id");
            $model = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
                "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"])->find($id);

            if ($model) {

                if ($model->slot_status === 1) {

                    $model = $this->repository->getValidatedData($model, [
                        "slot_type_remarks" => "required",
                    ], [], ["slot_type_remarks" => "Cancellation Remarks"]);

                    if ($this->repository->isValidData) {

                        $info = request()->post("rescheduled_slot_info");
                        $info = json_decode($info, true);

                        if (empty($info["date"]) || empty($info["start_time"]) || empty($info["end_time"])) {

                            $info = [];
                        }

                        $model->slot_type = 2; //cancelled status
                        $model->rescheduled_slot_info = $info;

                        $this->repository->setApprovalSteps($model->slot_type);
                        $this->repository->setApprovalUrls($model->slot_type);
                        $this->repository->setApprovalStatuses($model->slot_type);

                        $response = $this->repository->startApprovalProcess($model);

                        if ($response["notify"]["status"] === "success") {

                            $response["notify"]["notify"] = [];
                            $response["notify"]["notify"][] = "Cancellation request submitted successfully";
                        } else {
                            $response["notify"]["notify"][] = "Cancellation request submission failed";
                        }
                    } else {
                        $response = $model;
                    }
                } elseif ($model->slot_type === 2) {

                    $response["notify"]["status"] = "failed";
                    $response["notify"]["notify"][] = "It has been already requested to cancel this time slot.";
                } else {

                    $response["notify"]["status"] = "failed";
                    $response["notify"]["notify"][] = "Requested time slot is not an active time slot.";
                }
            } else {
                $response["notify"]["status"] = "failed";
                $response["notify"]["notify"][] = "Requested timetable does not exist.";
            }
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Store a newly created resource in storage.=
     * @return JsonResponse
     */
    public function createRescheduleRequest(): JsonResponse
    {
        $timetableId = request()->post("academic_timetable_id");
        $timetable = AcademicTimetable::query()->find($timetableId);

        $response = $this->repository->validateTimetable($timetable);

        if ($response["notify"]["status"] === "success") {

            $slot = request()->post("slot");
            $slot = json_decode($slot, true);

            $cancelledSlotId = request()->post("cancelled_slot_id");

            $isValid = false;

            if ($cancelledSlotId !== null) {
                $cancelledSlot = AcademicTimetableInformation::query()->find($cancelledSlotId);

                if ($cancelledSlot) {

                    if ($cancelledSlot->approval_status === 1 && $cancelledSlot->slot_status === 0) {

                        $response = $this->repository->validateTimeSlot($timetable, $slot);

                        if ($response["notify"]["status"] === "success") {

                            $isValid = true;
                        }
                    } else {

                        $response["notify"]["status"] = "failed";
                        $response["notify"]["notify"][] = "Requested cancelled slot has not approved yet.";
                    }
                } else {

                    $response["notify"]["status"] = "failed";
                    $response["notify"]["notify"][] = "Requested cancelled slot does not exist.";
                }
            } else {

                $response["notify"]["status"] = "failed";
                $response["notify"]["notify"][] = "Cancelled slot selection is required.";
            }

            if ($isValid) {

                $success = false;

                DB::beginTransaction();
                try {

                    $ttRepo = new AcademicTimetableRepository();

                    $model = new AcademicTimetableInformation();
                    $model->slot_status = 0; //set as disabled
                    $model->slot_type = 3; //reschedule status
                    $model->cancelled_slot_id = $cancelledSlotId; //cancelled slot id

                    $ttRepo->deliveryModeId = request()->post("delivery_mode_id");
                    $model = $ttRepo->updateSlotInfo($model, $timetable, $slot);

                    if (isset($model->id)) {

                        $model->load(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
                            "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"]);

                        $this->repository->setApprovalSteps($model->slot_type);
                        $this->repository->setApprovalUrls($model->slot_type);
                        $this->repository->setApprovalStatuses($model->slot_type);

                        $response = $this->repository->startApprovalProcess($model, 0);

                        if ($response["notify"]["status"] === "success") {

                            $record = $model->toArray();
                            $response["data"] = $ttRepo->getCleanRecord($record);

                            $response["notify"]["notify"] = [];
                            $response["notify"]["notify"][] = "Reschedule request submitted successfully";

                            $success = true;
                        } else {
                            $response["notify"]["status"] = "failed";
                            $response["notify"]["code"] = 1;
                            $response["notify"]["notify"][] = "Reschedule request submission failed";
                        }
                    } else {

                        $response["notify"]["status"] = "failed";
                        $response["notify"]["code"] = 2;
                        $response["notify"]["notify"][] = "Reschedule request submission failed";
                    }
                } catch (Exception $exception) {

                    $response["notify"]["status"] = "failed";
                    $response["notify"]["code"] = 3;
                    $response["notify"]["error"] = $exception->getMessage();
                    $response["notify"]["notify"][] = "Reschedule request submission failed";
                }

                if ($success) {
                    DB::commit();
                } else {
                    DB::rollBack();
                }
            }
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Store a newly created resource in storage.=
     * @return JsonResponse
     */
    public function createRevisionRequest(): JsonResponse
    {
        $timetableId = request()->post("academic_timetable_id");
        $timetable = AcademicTimetable::query()->find($timetableId);

        $response = $this->repository->validateTimetable($timetable);

        if ($response["notify"]["status"] === "success") {

            $slot = request()->post("slot");
            $slot = json_decode($slot, true);

            $response = $this->repository->validateTimeSlot($timetable, $slot);

            if ($response["notify"]["status"] === "success") {

                $success = false;

                DB::beginTransaction();
                try {
                    $ttRepo = new AcademicTimetableRepository();

                    $model = new AcademicTimetableInformation();
                    $model->slot_status = 0; //set as disabled
                    $model->slot_type = 4; //Revision status

                    $ttRepo->deliveryModeId = request()->post("delivery_mode_id");
                    $model = $ttRepo->updateSlotInfo($model, $timetable, $slot);

                    if (isset($model->id)) {

                        $model->load(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
                            "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"]);

                        $this->repository->setApprovalSteps($model->slot_type);
                        $this->repository->setApprovalUrls($model->slot_type);
                        $this->repository->setApprovalStatuses($model->slot_type);

                        $response = $this->repository->startApprovalProcess($model, 0);

                        if ($response["notify"]["status"] === "success") {

                            $record = $model->toArray();
                            $response["data"] = $ttRepo->getCleanRecord($record);

                            $response["notify"]["notify"] = [];
                            $response["notify"]["notify"][] = "Revision request submitted successfully";

                            $success = true;
                        } else {
                            $response["notify"]["notify"][] = "Revision request submission failed";
                        }
                    } else {

                        $response["notify"]["status"] = "failed";
                        $response["notify"]["notify"][] = "Revision request submission failed";
                    }
                } catch (Exception $exception) {

                    $response["notify"]["status"] = "failed";
                    $response["notify"]["notify"][] = "Revision request submission failed";
                }

                if ($success) {
                    DB::commit();
                } else {
                    DB::rollBack();
                }
            }
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Store a newly created resource in storage.=
     * @return JsonResponse
     */
    public function createReliefRequest(): JsonResponse
    {
        $timetableId = request()->post("academic_timetable_id");
        $timetable = AcademicTimetable::query()->find($timetableId);

        $response = $this->repository->validateTimetable($timetable);

        if ($response["notify"]["status"] === "success") {

            $slot = request()->post("slot");
            $slot = json_decode($slot, true);

            $response = $this->repository->validateTimeSlot($timetable, $slot);

            if ($response["notify"]["status"] === "success") {

                $success = false;

                DB::beginTransaction();
                try {

                    $ttRepo = new AcademicTimetableRepository();

                    $model = new AcademicTimetableInformation();
                    $model->slot_status = 0; //set as disabled
                    $model->slot_type = 5; //Relief status

                    $ttRepo->deliveryModeId = request()->post("delivery_mode_id");
                    $model = $ttRepo->updateSlotInfo($model, $timetable, $slot);

                    if (isset($model->id)) {

                        $model->load(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
                            "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"]);

                        $this->repository->setApprovalSteps($model->slot_type);
                        $this->repository->setApprovalUrls($model->slot_type);
                        $this->repository->setApprovalStatuses($model->slot_type);

                        $response = $this->repository->startApprovalProcess($model, 0);

                        if ($response["notify"]["status"] === "success") {

                            $record = $model->toArray();
                            $response["data"] = $ttRepo->getCleanRecord($record);

                            $response["notify"]["notify"] = [];
                            $response["notify"]["notify"][] = "Relief request submitted successfully";

                            $success = true;
                        } else {
                            $response["notify"]["notify"][] = "Relief request submission failed";
                        }
                    } else {

                        $response["notify"]["status"] = "failed";
                        $response["notify"]["notify"][] = "Relief request submission failed";
                    }
                } catch (Exception $exception) {

                    $response["notify"]["status"] = "failed";
                    $response["notify"]["notify"][] = "Relief request submission failed";
                }

                if ($success) {
                    DB::commit();
                } else {
                    DB::rollBack();
                }
            }
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Show the specified resource.
     * @param $id
     * @return Application|Factory|View|void
     */
    public function show($id)
    {
        $model = AcademicTimetableInformation::withTrashed()->with(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
            "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"])->find($id);

        if ($model) {

            $this->repository->setApprovalSteps($model->slot_type);
            $this->repository->setApprovalUrls($model->slot_type);
            $this->repository->setApprovalStatuses($model->slot_type);
            $response = $this->repository->getApprovalInfoPrepared($model);

            if ($response["notify"]["status"] === "success") {

                $step = $this->repository->step;

                $type = "";
                if ($model->slot_type === 2) {
                    $type = "cancel";
                } elseif ($model->slot_type === 3) {
                    $type = "reschedule";
                } elseif ($model->slot_type === 4) {
                    $type = "revision";
                } elseif ($model->slot_type === 5) {
                    $type = "relief";
                }

                $pageTitle = $this->repository->getApprovalStepTitle($model, $step);
                $this->repository->setPageTitle($pageTitle);

                $timetableUrl = URL::to("/academic/academic_timetable/view/" . $model->academic_timetable_id);

                $record = $model->toArray();

                $rescheduleInfo = null;
                if (isset($record["rescheduled_slot_info"]["date"])
                    && isset($record["rescheduled_slot_info"]["start_time"])
                    && isset($record["rescheduled_slot_info"]["end_time"])) {

                    $rescheduleInfo = $record["rescheduled_slot_info"];
                    $rescheduleInfo["hours"] = Helper::getHourDiff($rescheduleInfo["start_time"], $rescheduleInfo["end_time"]);
                }

                return view('academic::academic_timetable_information.view', compact('record', 'timetableUrl', 'type', 'step', 'rescheduleInfo'));
            } else {

                abort(404, "Requested record does not exist.");
            }
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Search records
     * @param Request $request
     * @return JsonResponse|void
     */
    public function searchData(Request $request)
    {
        if ($request->expectsJson()) {

            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $timetableId = $request->post("timetable_id");
            $slotType = $request->post("slot_type");
            $moduleId = $request->post("module_id");
            $examTypeId = $request->post("exam_type_id");
            $examCategoryId = $request->post("exam_category_id");
            $deliveryModeId = $request->post("delivery_mode_id");
            $limit = $request->post("limit");

            $query = AcademicTimetableInformation::query()->with(["module"])
                ->select("academic_timetable_information_id", "tt_date", "start_time", "end_time", "module_id")
                ->whereHas("module")
                ->whereHas("timetable", function ($query) {

                    $type = request()->post("type");

                    if ($type === "academic") {

                        $query->where("type", 2);
                        $query->where("status", 1);
                    } elseif ($type === "master") {

                        $query->where("type", 1);
                    } else {

                        $query->where(function ($query){

                            $query->where("type", 1)->whereDoesntHave("academic");
                        })->orWhere(function ($query){

                            $query->where("type", 2);
                            $query->where("status", 1);
                        });
                    }
                })
                ->orderBy("tt_date")
                ->orderBy("start_time")
                ->groupBy("academic_timetable_information_id")
                ->limit(10);

            if (intval($slotType) === 2) {
                $query->whereNull("rescheduled_slot_id")
                    ->where("slot_status", 0)
                    ->where("approval_status", 1);
            } else {

                $query->where("slot_status", 1);
            }

            if ($timetableId !== null) {

                $query->where("academic_timetable_id", $timetableId);
            }

            if ($slotType !== null) {

                $query->where("slot_type", $slotType);
            }

            if ($examTypeId !== null) {

                $query->where("exam_type_id", $examTypeId);
            }

            if ($examCategoryId !== null) {

                $query->where("exam_category_id", $examCategoryId);
            }

            if ($deliveryModeId !== null) {

                $query->where("delivery_mode_id", $deliveryModeId);
            }

            if ($moduleId !== null) {

                $cMModel = CourseModule::query()->find($moduleId);

                if ($cMModel) {

                    $cMRepo = new CourseModuleRepository();
                    $similarIds = $cMRepo->getSimilarModules($cMModel);

                    $similarIds[] = $moduleId;

                    $query->whereIn("module_id", $similarIds);
                }
            }

            if ($searchText !== null) {

                $query->where(function ($query) use ($searchText) {

                    $query->where("tt_date", "LIKE", "%" . $searchText . "%")
                        ->orWhere("start_time", "LIKE", "%" . $searchText . "%")
                        ->orWhere("end_time", "LIKE", "%" . $searchText . "%")
                        ->orWhere(function ($query) use ($searchText) {

                            $query->whereHas("module", function ($query) use ($searchText) {

                                $query->where("module_name", "LIKE", "%" . $searchText . "%")
                                    ->orWhere("module_code", "LIKE", "%" . $searchText . "%");
                            });
                        });
                });
            }

            if ($idNot !== null) {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("academic_timetable_information_id", $idNot);
            }

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            $results = $query->get()->toArray();

            $data = [];
            if (is_array($results) && count($results) > 0) {

                foreach ($results as $result) {

                    $record = [];
                    $record["id"] = $result["id"];
                    $record["name"] = $result["name"];

                    $data[] = $record;
                }
            }

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }

    public function verification($id)
    {
        $model = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
            "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"])->find($id);

        if ($model) {
            $this->repository->validateRequest($model);

            $type = request()->route()->getAction()['type'];

            $this->repository->setApprovalSteps($type);
            $this->repository->setApprovalUrls($type);
            $this->repository->setApprovalStatuses($type);

            return $this->repository->renderApprovalView($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function verificationSubmit($id)
    {
        $model = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
            "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"])->find($id);

        if ($model) {
            $this->repository->validateRequest($model);

            $type = request()->route()->getAction()['type'];

            $this->repository->setApprovalSteps($type);
            $this->repository->setApprovalUrls($type);
            $this->repository->setApprovalStatuses($type);

            return $this->repository->processApprovalSubmission($model, "verification");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalHOD($id)
    {
        $model = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
            "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"])->find($id);

        if ($model) {
            $this->repository->validateRequest($model);

            $type = request()->route()->getAction()['type'];

            $this->repository->setApprovalSteps($type);
            $this->repository->setApprovalUrls($type);
            $this->repository->setApprovalStatuses($type);

            return $this->repository->renderApprovalView($model, "pre_approval_hod");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalHODSubmit($id)
    {
        $model = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
            "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"])->find($id);

        if ($model) {
            $this->repository->validateRequest($model);

            $type = request()->route()->getAction()['type'];

            $this->repository->setApprovalSteps($type);
            $this->repository->setApprovalUrls($type);
            $this->repository->setApprovalStatuses($type);

            return $this->repository->processApprovalSubmission($model, "pre_approval_hod");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalSAR($id)
    {
        $model = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
            "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"])->find($id);

        if ($model) {
            $this->repository->validateRequest($model);

            $type = request()->route()->getAction()['type'];

            $this->repository->setApprovalSteps($type);
            $this->repository->setApprovalUrls($type);
            $this->repository->setApprovalStatuses($type);

            return $this->repository->renderApprovalView($model, "pre_approval_sar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalSARSubmit($id)
    {
        $model = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
            "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"])->find($id);

        if ($model) {
            $this->repository->validateRequest($model);

            $type = request()->route()->getAction()['type'];

            $this->repository->setApprovalSteps($type);
            $this->repository->setApprovalUrls($type);
            $this->repository->setApprovalStatuses($type);

            return $this->repository->processApprovalSubmission($model, "pre_approval_sar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalRegistrar($id)
    {
        $model = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
            "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"])->find($id);

        if ($model) {
            $this->repository->validateRequest($model);

            $type = request()->route()->getAction()['type'];

            $this->repository->setApprovalSteps($type);
            $this->repository->setApprovalUrls($type);
            $this->repository->setApprovalStatuses($type);

            return $this->repository->renderApprovalView($model, "pre_approval_registrar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalRegistrarSubmit($id)
    {
        $model = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
            "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"])->find($id);

        if ($model) {
            $this->repository->validateRequest($model);

            $type = request()->route()->getAction()['type'];

            $this->repository->setApprovalSteps($type);
            $this->repository->setApprovalUrls($type);
            $this->repository->setApprovalStatuses($type);

            return $this->repository->processApprovalSubmission($model, "pre_approval_registrar");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalVC($id)
    {
        $model = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
            "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"])->find($id);

        if ($model) {
            $this->repository->validateRequest($model);

            $type = request()->route()->getAction()['type'];

            $this->repository->setApprovalSteps($type);
            $this->repository->setApprovalUrls($type);
            $this->repository->setApprovalStatuses($type);

            return $this->repository->renderApprovalView($model, "pre_approval_vc");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function preApprovalVCSubmit($id)
    {
        $model = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
            "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"])->find($id);

        if ($model) {
            $this->repository->validateRequest($model);

            $type = request()->route()->getAction()['type'];

            $this->repository->setApprovalSteps($type);
            $this->repository->setApprovalUrls($type);
            $this->repository->setApprovalStatuses($type);

            return $this->repository->processApprovalSubmission($model, "pre_approval_vc");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function approval($id)
    {
        $model = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
            "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"])->find($id);

        if ($model) {
            $this->repository->validateRequest($model);

            $type = request()->route()->getAction()['type'];

            $this->repository->setApprovalSteps($type);
            $this->repository->setApprovalUrls($type);
            $this->repository->setApprovalStatuses($type);

            return $this->repository->renderApprovalView($model, "approval");
        } else {
            $response["status"] = "failed";
            $response["notify"][] = "Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function approvalSubmit($id)
    {
        $model = AcademicTimetableInformation::with(["timetable", "module", "lessonTopic", "deliveryMode", "deliveryModeSpecial", "examType",
            "examCategory", "lecturers", "subgroups", "spaces", "cancelled", "rescheduled"])->find($id);

        if ($model) {
            $this->repository->validateRequest($model);

            $type = request()->route()->getAction()['type'];

            $this->repository->setApprovalSteps($type);
            $this->repository->setApprovalUrls($type);
            $this->repository->setApprovalStatuses($type);

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
        $model = new AcademicTimetableInformation();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new AcademicTimetableInformation();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
