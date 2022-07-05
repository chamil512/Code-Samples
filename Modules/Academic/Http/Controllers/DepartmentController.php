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
use Modules\Academic\Entities\Department;
use Modules\Academic\Entities\Faculty;
use Modules\Academic\Repositories\DepartmentHeadRepository;
use Modules\Academic\Repositories\DepartmentRepository;
use Modules\Accounting\Entities\SegmentsE;

class DepartmentController extends Controller
{
    private DepartmentRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new DepartmentRepository();
    }

    /**
     * Display a listing of the resource.
     * @param mixed $facultyId
     * @return Factory|View
     */
    public function index($facultyId=false)
    {
        $facTitle = "";
        if($facultyId)
        {
            $cc = Faculty::query()->find($facultyId);

            if($cc)
            {
                $facTitle = $cc["faculty_name"];
            }
            else
            {
                abort(404, "Faculty not available");
            }
        }

        $pageTitle = "Departments";
        if($facTitle != "")
        {
            $pageTitle = $facTitle." | ".$pageTitle;
        }

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new Department());

        $this->repository->setColumns("id", "dept_name", "dept_code", "faculty", "hod", $this->repository->statusField, $this->repository->approvalField, "created_at")
            ->setColumnLabel("dept_code", "Code")
            ->setColumnLabel("dept_name", "Department")
            ->setColumnLabel("hod", "Department Heads")
            ->setColumnLabel($this->repository->statusField, "Status")

            ->setColumnDBField("faculty", "faculty_id")
            ->setColumnFKeyField("faculty", "faculty_id")
            ->setColumnRelation("faculty", "faculty", "faculty_name")

            ->setColumnDisplay("faculty", array($this->repository, 'displayRelationAs'), ["faculty", "faculty_id", "faculty_name"])
            ->setColumnDisplay($this->repository->statusField, array($this->repository, 'displayStatusActionAs'), [$this->repository->statuses, "/academic/department/change_status/", "", true])
            ->setColumnDisplay($this->repository->approvalField, array($this->repository, 'displayApprovalStatusAs'), [$this->repository->approvalStatuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnDisplay("hod", array($this->repository, 'displayListButtonAs'), ["Department Heads", URL::to("/academic/department_head/")])
            ->setColumnDBField("hod", $this->repository->primaryKey)

            ->setColumnFilterMethod($this->repository->statusField, "select", $this->repository->statuses)
            ->setColumnFilterMethod("faculty", "select", URL::to("/academic/faculty/search_data"))

            ->setColumnSearchability("created_at", false);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = "Departments | Trashed";
            if($facultyId)
            {
                if($facTitle!= "")
                {
                    $tableTitle = $facTitle." | ".$tableTitle;

                    $this->repository->setUrl("list", "/academic/department/".$facultyId);
                }
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "view", "restore", "export")
                ->disableViewData("edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $tableTitle = "Departments";
            if($facultyId)
            {
                if($facTitle!= "")
                {
                    $tableTitle = $facTitle." | ".$tableTitle;

                    $this->repository->setUrl("trashList", "/academic/department/trash/".$facultyId);
                }
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("view", "trashList", "trash", "export");
        }

        if($facultyId)
        {
            $this->repository->setUrl("add", "/academic/department/create/".$facultyId);

            $this->repository->unsetColumns("faculty");
            $query = $query->where(["faculty_id" => $facultyId]);
        }
        else
        {
            $query = $query->with(["faculty"]);
        }

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param $facultyId
     * @return Factory|View
     */
    public function trash($facultyId=false)
    {
        $this->trash = true;
        return $this->index($facultyId);
    }

    /**
     * Show the form for creating a new resource.
     * @param $facultyId
     * @return Factory|View
     */
    public function create($facultyId=false)
    {
        $model = new Department();

        if($facultyId)
        {
            $model->faculty_id = $facultyId;
            $model->faculty()->get();
        }
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/".request()->path();

        $urls = [];
        if($facultyId)
        {
            $urls["listUrl"]=URL::to("/academic/department/".$facultyId);
        }
        else
        {
            $urls["listUrl"]=URL::to("/academic/department");
        }

        $this->repository->setPageUrls($urls);

        $segments = SegmentsE::getActiveAllSubSegmentDepartment(null);

        return view('academic::department.create', compact('formMode', 'formSubmitUrl', 'record','segments'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(): JsonResponse
    {
        $model = new Department();

        $model = $this->repository->getValidatedData($model, [
            "faculty_id" => "required|exists:faculties,faculty_id",
            "dept_name" => "required",
            "color_code" => "required",
            "acc_seg_id"=> "required",
           
        ], [], ["faculty_id" => "Faculty", "dept_name" => "Department name"]);

        if($this->repository->isValidData)
        {
             $model->dept_code = $this->repository->generateDeptCode($model->faculty_id);

            $response = $this->repository->saveModel($model);

            if($response["notify"]["status"]=="success")
            {
                if(request()->post("send_for_approval")=="1")
                {
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
        }
        else
        {
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
        $model = Department::withTrashed()->with([
            "faculty",
            "courses",
            "createdUser",
            "updatedUser",
            "deletedUser"])->find($id);

        if($model)
        {
            $record = $model->toArray();

            $fDRepo = new DepartmentHeadRepository();
            $hod = $fDRepo->getDefault($model->id);

            $record["hod"] = $hod;

            $controllerUrl = URL::to("/academic/department/");

            $urls = [];
            $urls["addUrl"]=URL::to($controllerUrl . "/create");
            $urls["editUrl"]=URL::to($controllerUrl . "/edit/" . $id);
            $urls["listUrl"]=URL::to($controllerUrl);
            $urls["hodsUrl"]=URL::to("/academic/department_head/");
            $urls["adminUrl"]=URL::to("/admin/admin/view/");
            $urls["courseUrl"]=URL::to("/academic/course/view/");
            $urls["recordHistoryUrl"]=$this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);
            $urls["approvalHistoryUrl"]=$this->repository->getDefaultRecordHistoryUrl($controllerUrl, $model);

            $this->repository->setPageUrls($urls);

            $statusInfo = [];
            $statusInfo["status"] = $this->repository->getStatusInfo($model);
            $statusInfo[$this->repository->approvalField] = $this->repository->getStatusInfo($model, $this->repository->approvalField, $this->repository->approvalStatuses);

            return view('academic::department.view', compact('record', 'statusInfo'));
        }
        else
        {
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
        $model = Department::with(["faculty"])->find($id);

        if($model)
        {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/department/create");
            $urls["listUrl"]=URL::to("/academic/department"); 

            $this->repository->setPageUrls($urls);
            $faculty_name= $record["faculty"]["faculty_name"];
            $segments = SegmentsE::getActiveAllSubSegmentDepartment($faculty_name);

            return view('academic::department.create', compact('formMode', 'formSubmitUrl', 'record','segments'));
        }
        else
        {
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
        $model = Department::query()->find($id);

        if($model)
        {
            $currModel = $model;
            $model = $this->repository->getValidatedData($model, [
                "faculty_id" => "required|exists:faculties,faculty_id",
                "dept_name" => "required",
                "color_code" => "required",
                "acc_seg_id"=> "required",
             ], [], ["faculty_id" => "Faculty", "dept_name" => "Department name", "color_code" => "Colour Code"]);

            if($this->repository->isValidData)
            {
                if($currModel->faculty_id != $model->faculty_id)
                {
                   $model->dept_code = $this->repository->generateDeptCode($model->faculty_id);
                }
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
            }
            else
            {
                $response = $model;
            }
        }
        else
        {
            $notify = array();
            $notify["status"]="failed";
            $notify["notify"][]="Details saving was failed. Requested record does not exist.";

            $response["notify"]=$notify;
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
        $model = Department::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = Department::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Search records
     * @param Request $request
     * @return JsonResponse
     */
    public function searchData(Request $request): JsonResponse
    {
        if($request->expectsJson())
        {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $facultyId = $request->post("faculty_id");
            $limit = $request->post("limit");

            $query = Department::query()
                ->select("dept_id", "dept_name", "dept_code")
                ->where($this->repository->statusField, "=", "1")
                ->orderBy("dept_name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if($facultyId != "")
            {
                if (is_array($facultyId) && count($facultyId) > 0) {

                    $query = $query->whereIn("faculty_id", $facultyId);
                } else {
                    $query = $query->where("faculty_id", $facultyId);
                }
            }

            if($searchText != "")
            {
                $query = $query->where("dept_name", "LIKE", "%".$searchText."%");
            }

            if($idNot != "")
            { 
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("dept_id", $idNot);
            }

            $data = $query->get();

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return JsonResponse|RedirectResponse|null
     */
    public function changeStatus($id)
    {
        $model = Department::query()->find($id);
        return $this->repository->updateStatus($model, $this->repository->statusField,"", "remarks");
    }

    public function verification($id)
    {
        $model = Department::query()->find($id);

        if($model)
        {
            return $this->repository->renderApprovalView($model, "verification");
        }
        else
        {
            $response["status"]="failed";
            $response["notify"][]="Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @throws ValidationException
     */
    public function verificationSubmit($id)
    {
        $model = Department::query()->find($id);

        if($model)
        {
            return $this->repository->processApprovalSubmission($model, "verification");
        }
        else
        {
            $response["status"]="failed";
            $response["notify"][]="Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    public function approval($id)
    {
        $model = Department::query()->find($id);

        if($model)
        {
            return $this->repository->renderApprovalView($model, "approval");
        }
        else
        {
            $response["status"]="failed";
            $response["notify"][]="Requested record does not exist.";

            return $this->repository->handleResponse($response);
        }
    }

    /**
     * @throws ValidationException
     */
    public function approvalSubmit($id)
    {
        $model = Department::query()->find($id);

        if($model)
        {
            return $this->repository->processApprovalSubmission($model, "approval");
        }
        else
        {
            $response["status"]="failed";
            $response["notify"][]="Requested record does not exist.";

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
        $model = new Department();
        return $this->repository->approvalHistory($model, $modelHash, $id);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new Department();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }

    public function getDepartment($faculty_name){
       if($faculty_name=="null"){
        $segments = SegmentsE::getActiveAllSubSegmentDepartment(null);
        return $segments;
        }else{
        $segments = SegmentsE::getActiveAllSubSegmentDepartment($faculty_name);
        return $segments;
        }
    }
}
