<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\Faculty;
use Modules\Accounting\Entities\SegmentsE;
use Modules\Academic\Repositories\FacultyDeanRepository;
use Modules\Academic\Repositories\FacultyRepository;

class FacultyController extends Controller
{
    private FacultyRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new FacultyRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Faculties");

        $this->repository->initDatatable(new Faculty());

        $this->repository->setColumns("id", "faculty_name", "faculty_code", "departments", "deans", $this->repository->statusField, $this->repository->approvalField, "created_at")
            ->setColumnLabel("faculty_code", "Code")
            ->setColumnLabel($this->repository->statusField, "Status")
            ->setColumnDisplay("departments", array($this->repository, 'displayListButtonAs'), ["Departments", URL::to("/academic/department/")])
            ->setColumnDBField("departments", $this->repository->primaryKey)
            ->setColumnDisplay("deans", array($this->repository, 'displayListButtonAs'), ["Deans", URL::to("/academic/faculty_dean/")])
            ->setColumnDBField("deans", $this->repository->primaryKey)
            ->setColumnDisplay($this->repository->statusField, array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses, "", "", true])
            ->setColumnDisplay($this->repository->approvalField, array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnFilterMethod("faculty_name")
            ->setColumnFilterMethod($this->repository->statusField, "select", $this->repository->statuses)
            ->setColumnFilterMethod($this->repository->approvalField, "select", $this->repository->approvalStatuses)
            ->setColumnSearchability("created_at", false);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Faculties | Trashed")
                ->enableViewData("list", "view", "restore", "export")
                ->disableViewData("edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Faculties")
                ->enableViewData("trashList", "view", "trash", "export");
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
        $model = new Faculty();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/faculty");

        $this->repository->setPageUrls($urls);

        $segments = SegmentsE::getActiveAllSubSegmentFaculty();

        
        return view('academic::faculty.create', compact('formMode', 'formSubmitUrl', 'record','segments'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(): JsonResponse
    {
        $model = new Faculty();

        $model = $this->repository->getValidatedData($model, [
            "faculty_name" => "required",
            "color_code" => "required",
           
            // "acc_seg_id"=> "required"
            
        ]);

        if ($this->repository->isValidData) {
            $model->faculty_code = $this->repository->generateFacultyCode();

            $response = $this->repository->saveModel($model);

            if ($response["notify"]["status"] == "success") {
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
        $model = Faculty::withTrashed()->with([
            "createdUser",
            "updatedUser",
            "deletedUser"])->find($id);

        if ($model) {
            $record = $model->toArray();

            $fDRepo = new FacultyDeanRepository();
            $dean = $fDRepo->getDefault($model->id);

            $record["dean"] = $dean;

            $controllerUrl = URL::to("/academic/faculty/");

            $urls = [];
            $urls["addUrl"] = URL::to($controllerUrl . "/create");
            $urls["editUrl"] = URL::to($controllerUrl . "/edit/" . $id);
            $urls["listUrl"] = URL::to($controllerUrl);
            $urls["deptUrl"] = URL::to("/academic/department/");
            $urls["deansUrl"] = URL::to("/academic/faculty_dean/");
            $urls["adminUrl"] = URL::to("/admin/admin/view/");
            $urls["recordHistoryUrl"] = $this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);
            $urls["approvalHistoryUrl"] = $this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);

            $this->repository->setPageUrls($urls);

            $statusInfo = [];
            $statusInfo["status"] = $this->repository->getStatusInfo($model);
            $statusInfo[$this->repository->approvalField] = $this->repository->getStatusInfo($model, $this->repository->approvalField, $this->repository->approvalStatuses);

            return view('academic::faculty.view', compact('record', 'statusInfo'));
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
        $model = Faculty::query()->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/faculty/create");
            $urls["listUrl"] = URL::to("/academic/faculty");

            $this->repository->setPageUrls($urls);

            $segments = SegmentsE::getActiveAllSubSegmentFaculty();

            return view('academic::faculty.create', compact('formMode', 'formSubmitUrl', 'record','segments'));
        }
        else
        {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Update the specified resource in storage.
     * @param $id
     * @return JsonResponse
     * @throws ValidationException
     */
    public function update($id,Request $request): JsonResponse
    {
        $model = Faculty::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "faculty_name" => "required",
                "color_code" => "required",
                "acc_seg_id"=> "required"
              
            ]);
            // dd($request->input('acc_seg_id'));

            if ($this->repository->isValidData) {
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] === "success") {

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
        $model = Faculty::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = Faculty::withTrashed()->find($id);

        return $this->repository->restore($model);
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
            $limit = $request->post("limit");

            $query = Faculty::query()
                ->select("faculty_id", "faculty_name", "faculty_code")
                ->where($this->repository->statusField, "=", "1")
                ->orderBy("faculty_name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($searchText != "") {
                $query = $query->where("faculty_name", "LIKE", "%" . $searchText . "%");
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("faculty_id", $idNot);
            }

            $data = $query->get();

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }

    /**
     * Update status of the specified resource in storage.
     * @param $id
     * @return JsonResponse
     */
    public function changeStatus($id): JsonResponse
    {
        $model = Faculty::query()->find($id);
        return $this->repository->updateStatus($model, $this->repository->statusField, "", "remarks");
    }

    public function verification($id)
    {
        $model = Faculty::query()->find($id);

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
        $model = Faculty::query()->find($id);

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
        $model = Faculty::query()->find($id);

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
        $model = Faculty::query()->find($id);

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
        $model = new Faculty();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new Faculty();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
