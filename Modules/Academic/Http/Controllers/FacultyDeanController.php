<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Academic\Entities\FacultyDean;
use Modules\Academic\Entities\Faculty;
use Modules\Academic\Repositories\FacultyDeanRepository;
use Modules\Admin\Repositories\AdminActivityRepository;

class FacultyDeanController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new FacultyDeanRepository();
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

        $pageTitle = "Faculty Deans";
        if($facTitle != "")
        {
            $pageTitle = $facTitle." | ".$pageTitle;
        }

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new FacultyDean());

        $this->repository->setColumns("id", "profile_image", "name", "faculty", "date_from", "date_till", "status", "created_at")
            ->setColumnLabel("name", "Name of Dean")
            ->setColumnLabel("faculty", "Faculty")
            ->setColumnLabel("date_from", "Period From")
            ->setColumnLabel("date_till", "Period Till")
            ->setColumnLabel("status", "Current/Former Status")

            ->setColumnDBField("faculty", "faculty_id")
            ->setColumnFKeyField("faculty", "faculty_id")
            ->setColumnRelation("faculty", "faculty", "faculty_name")

            ->setColumnDisplay("profile_image", array($this->repository, 'displayImageAs'), [$this->repository->upload_dir, 150])
            ->setColumnDisplay("faculty", array($this->repository, 'displayRelationAs'), ["faculty", "faculty_id", "faculty_name"])
            ->setColumnDisplay("status", array($this->repository, 'displayStatusAs'), [$this->repository->statuses])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("name", "text", [
                ["relation" => "lecturer", "fields" => ["name_in_full", "given_name", "name_with_init", "surname"]],
                ["relation" => "employee", "fields" => ["name_in_full", "given_name", "name_with_init", "surname"]],
                ["relation" => "externalIndividual", "fields" => ["name_in_full", "given_name", "name_with_init", "surname"]]
            ])
            ->setColumnFilterMethod("faculty", "select", URL::to("/academic/faculty/search_data"))
            ->setColumnFilterMethod("date_from", "date_after")
            ->setColumnFilterMethod("date_till", "date_before")
            ->setColumnFilterMethod("status", "select", $this->repository->statuses)

            ->setColumnOrderability("name", false)
            ->setColumnSearchability("profile_image", false)
            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = "Faculty Deans | Trashed";
            if($facultyId)
            {
                if($facTitle!= "")
                {
                    $tableTitle = $facTitle." | ".$tableTitle;

                    $this->repository->setUrl("list", "/academic/faculty_dean/".$facultyId);
                }
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $tableTitle = "Faculty Deans";
            if($facultyId)
            {
                if($facTitle!= "")
                {
                    $tableTitle = $facTitle." | ".$tableTitle;

                    $this->repository->setUrl("trashList", "/academic/faculty_dean/trash/".$facultyId);
                }
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export");
        }

        if($facultyId)
        {
            $this->repository->setUrl("add", "/academic/faculty_dean/create/".$facultyId);

            $this->repository->unsetColumns("faculty");
            $query = $query->where(["faculty_id" => $facultyId]);
            $query = $query->with(["lecturer", "employee", "externalIndividual"]);
        }
        else
        {
            $query = $query->with(["faculty", "lecturer", "employee", "externalIndividual"]);
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
        $model = new FacultyDean();

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
            $urls["listUrl"]=URL::to("/academic/faculty_dean/".$facultyId);
        }
        else
        {
            $urls["listUrl"]=URL::to("/academic/faculty_dean");
        }

        $this->repository->setPageUrls($urls);

        $deanTypes = $this->repository->deanTypes;

        return view('academic::faculty_dean.create', compact('formMode', 'formSubmitUrl', 'record', 'deanTypes'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     */
    public function store()
    {
        $model = new FacultyDean();

        $model = $this->repository->getValidatedData($model, [
            "faculty_id" => "required|exists:faculties,faculty_id",
            "dean_type" => "required",
            "lecturer_id" => [Rule::requiredIf(function () { return request()->post("dean_type") == "1";})],
            "employee_id" => [Rule::requiredIf(function () { return request()->post("dean_type") == "2";})],
            "external_individual_id" => [Rule::requiredIf(function () { return request()->post("dean_type") == "3";})],
            "description" => "",
            "date_from" => "required|date",
            "date_till" => [Rule::requiredIf(function () { return request()->post("status") == "0";})],
            "status" => "required",
        ], [], ["faculty_id" => "Faculty", "dean_type" => "Dean Type", "lecturer_id" => "Dean Name", "employee_id" => "Dean Name", "external_individual_id" => "Dean Name", "date_from" => "Date From", "date_till" => "Date Till"]);

        if($this->repository->isValidData) {

            $profileImage = $this->repository->uploadImage();

            if ($profileImage !== "") {
                $model->profile_image = $profileImage;
            }

            $response = $this->repository->saveModel($model);

            if($response["notify"]["status"]=="success") {

                if ($model->status == 1) {

                    $this->repository->resetOtherCurrent($model->faculty_id, $model->id);
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
        $model = FacultyDean::query()->find($id);

        if($model)
        {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/faculty_dean/create");
            $urls["listUrl"]=URL::to("/academic/faculty_dean");

            $this->repository->setPageUrls($urls);

            return view('academic::faculty_dean.view', compact('record'));
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
        $model = FacultyDean::with(["lecturer", "employee", "externalIndividual"])->find($id);

        if($model)
        {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/faculty_dean/create");
            $urls["listUrl"]=URL::to("/academic/faculty_dean");

            $this->repository->setPageUrls($urls);

            $deanTypes = $this->repository->deanTypes;

            return view('academic::faculty_dean.create', compact('formMode', 'formSubmitUrl', 'record', 'deanTypes'));
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
        $model = FacultyDean::query()->find($id);

        if($model)
        {
            $model = $this->repository->getValidatedData($model, [
                "faculty_id" => "required|exists:faculties,faculty_id",
                "dean_type" => "required",
                "lecturer_id" => [Rule::requiredIf(function () { return request()->post("dean_type") == "1";})],
                "employee_id" => [Rule::requiredIf(function () { return request()->post("dean_type") == "2";})],
                "external_individual_id" => [Rule::requiredIf(function () { return request()->post("dean_type") == "3";})],
                "description" => "",
                "date_from" => "required|date",
                "date_till" => [Rule::requiredIf(function () { return request()->post("status") == "0";})],
                "status" => "required",
            ], [], ["faculty_id" => "Faculty", "dean_type" => "Dean Type", "lecturer_id" => "Dean Name", "employee_id" => "Dean Name", "external_individual_id" => "Dean Name", "date_from" => "Date From", "date_till" => "Date Till"]);

            if($this->repository->isValidData) {

                $profileImage = $this->repository->uploadImage($model->profile_image);

                if ($profileImage !== "") {
                    $model->profile_image = $profileImage;
                }

                $response = $this->repository->saveModel($model);

                if($response["notify"]["status"]=="success") {

                    if ($model->status == 1) {

                        $this->repository->resetOtherCurrent($model->faculty_id, $model->id);
                    }
                }
            } else {
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
        $model = FacultyDean::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = FacultyDean::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new FacultyDean();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
