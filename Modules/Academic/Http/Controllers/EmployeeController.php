<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Academic\Entities\Employee;
use Modules\Academic\Repositories\EmployeeAvailabilityHourRepository;
use Modules\Academic\Repositories\EmployeeBankingInformationRepository;
use Modules\Academic\Repositories\EmployeeContactInformationRepository;
use Modules\Academic\Repositories\EmployeeRepository;
use Modules\Academic\Repositories\PersonBankingInformationRepository;
use Modules\Academic\Repositories\PersonContactInformationRepository;
use Modules\Academic\Repositories\PersonDocumentRepository;
use Modules\Admin\Repositories\AdminActivityRepository;

class EmployeeController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new EmployeeRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Employees");

        $this->repository->initDatatable(new Employee());

        $this->repository->setColumns("id", "name_with_init", "nic_no", "contact_info", "courses", "status", "created_at")
            ->setColumnLabel("name_with_init", "Name")
            ->setColumnLabel("status", "Status")
            ->setColumnDisplay("contact_info", array($this->repository, 'displayContactInfoAs'))
            ->setColumnDisplay("courses", array($this->repository, 'displayListButtonAs'), ["Courses", URL::to("/academic/employee_course/")])
            ->setColumnDisplay("status", array($this->repository, 'displayStatusAs'))
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("name_with_init", "text", ["name_in_full", "given_name", "name_with_init", "surname"])
            ->setColumnFilterMethod("status", "select", $this->repository->statuses)

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false)
            ->setColumnSearchability("updated_at", false)
            ->setColumnOrderability("contact_info", false)

            ->setColumnDBField("contact_info", "CONCAT(contact_no, ' ', email)")
            ->setColumnDBField("courses", $this->repository->primaryKey);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Employees | Trashed")
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Employees")
                ->enableViewData("trashList", "trash", "export");
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
        $model = new Employee();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/".request()->path();

        $urls = [];
        $urls["listUrl"]=URL::to("/academic/employee");

        $this->repository->setPageUrls($urls);

        return view('academic::employee.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     */
    public function store()
    {
        $model = new Employee();

        $model = $this->repository->getValidatedData($model, [
            "title_id" => "required|exists:honorific_titles,title_id",
            "academic_carder_position_id" => "required|exists:academic_carder_positions,id",
            "given_name" => "required",
            "surname" => "required",
            "name_in_full" => "required",
            "name_with_init" => "required",
            "date_of_birth" => "required|date",
            "nic_no" => [Rule::requiredIf(function () { return request()->post("passport_no") == "";})],
            "passport_no" => [Rule::requiredIf(function () { return request()->post("nic_no") == "";})],
            "perm_address" => "required",
            "perm_work_address" => "",
            "contact_no" => "required|digits_between:8,15",
            "email" => "required",
            "qualification_id" => "required|exists:academic_qualifications,qualification_id",
            "qualification_level_id" => "required|exists:academic_qualification_levels,id",
            "university_id" => "required|exists:universities,university_id",
            "qualified_year" => "required|digits:4",
        ], [], ["title_id" => "Title", "academic_carder_position_id" => "Academic Carder Position",
            "name_with_init" => "Name with initials", "perm_address" => "Permanent address",
            "qualification_id" => "Highest Qualification", "university_id" => "Qualified University",
            "qualified_year" => "Qualified Year"]);

        if($this->repository->isValidData)
        {
            $model->staff_type = 1;
            //will be saved as 1 until approval process is implemented
            $model->status = "1";
            //$model->status = "0";
            $response = $this->repository->saveModel($model);

            if ($response["notify"]["status"] == "success") {
                $cIRepo = new PersonContactInformationRepository();
                $cIRepo->update($model);

                $docRepo = new PersonDocumentRepository();
                $docRepo->upload_dir = "public/employee_documents/";
                $docRepo->update($model);

                $bIRepo = new PersonBankingInformationRepository();
                $bIRepo->update($model);
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
        $model = Employee::query()->find($id);

        if($model)
        {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/employee/create");
            $urls["listUrl"]=URL::to("/academic/employee");

            $this->repository->setPageUrls($urls);

            return view('academic::employee.view', compact('record'));
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
        $model = Employee::with(["carderPosition", "contactInfo", "documents", "bankingInfo", "qualification",
            "qualificationLevel", "university", "title"])->find($id);

        if($model)
        {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/employee/create");
            $urls["listUrl"]=URL::to("/academic/employee");
            $urls["downloadUrl"] = URL::to("/academic/employee/download_document/" . $id) . "/";

            $this->repository->setPageUrls($urls);

            return view('academic::employee.create', compact('formMode', 'formSubmitUrl', 'record'));
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
     */
    public function update($id)
    {
        $model = Employee::query()->find($id);

        if($model)
        {
            $model = $this->repository->getValidatedData($model, [
                "title_id" => "required|exists:honorific_titles,title_id",
                "academic_carder_position_id" => "required|exists:academic_carder_positions,id",
                "given_name" => "required",
                "surname" => "required",
                "name_in_full" => "required",
                "name_with_init" => "required",
                "date_of_birth" => "required|date",
                "nic_no" => [Rule::requiredIf(function () { return request()->post("passport_no") == "";})],
                "passport_no" => [Rule::requiredIf(function () { return request()->post("nic_no") == "";})],
                "perm_address" => "required",
                "perm_work_address" => "",
                "contact_no" => "required|digits_between:8,15",
                "email" => "required",
                "qualification_id" => "required|exists:academic_qualifications,qualification_id",
                "qualification_level_id" => "required|exists:academic_qualification_levels,id",
                "university_id" => "required|exists:universities,university_id",
                "qualified_year" => "required|digits:4",
            ], [], ["title_id" => "Title", "academic_carder_position_id" => "Academic Carder Position",
                "name_with_init" => "Name with initials", "perm_address" => "Permanent address",
                "qualification_id" => "Highest Qualification", "university_id" => "Qualified University"]);

            if($this->repository->isValidData)
            {
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] == "success") {
                    $cIRepo = new PersonContactInformationRepository();
                    $cIRepo->update($model);

                    $docRepo = new PersonDocumentRepository();
                    $docRepo->upload_dir = "public/employee_documents/";
                    $docRepo->update($model);

                    $bIRepo = new PersonBankingInformationRepository();
                    $bIRepo->update($model);
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
        $model = Employee::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = Employee::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Search records
     * @param Request $request
     * @return JsonResponse
     */
    public function searchData(Request $request)
    {
        if($request->expectsJson())
        {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $limit = $request->post("limit");

            $query = Employee::query()
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

            if($searchText != "")
            {
                $query->where(function ($query) use($searchText) {
                    $query->where("name_with_init", "LIKE", "%".$searchText."%")
                        ->orWhere("name_in_full", "LIKE", "%".$searchText."%")
                        ->orWhere("given_name", "LIKE", "%".$searchText."%")
                        ->orWhere("surname", "LIKE", "%".$searchText."%");
                });
            }

            if($idNot != "")
            {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("id", $idNot);
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
        $model = Employee::query()->find($id);

        if ($model) {
            $docRepo = new PersonDocumentRepository();
            $docRepo->upload_dir = "public/employee_documents/";
            return $docRepo->triggerDownloadDocument($model, $documentId);
        } else {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return mixed
     */
    public function changeStatus($id)
    {
        $model = Employee::query()->find($id);
        return $this->repository->updateStatus($model, "status");
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new Employee();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
