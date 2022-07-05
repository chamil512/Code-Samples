<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\Department;
use Modules\Academic\Entities\DepartmentHead;
use Modules\Academic\Repositories\DepartmentHeadRepository;

class DepartmentHeadController extends Controller
{
    private DepartmentHeadRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new DepartmentHeadRepository();
    }

    /**
     * Display a listing of the resource.
     * @param mixed $deptId
     * @return Factory|View
     */
    public function index($deptId = false)
    {
        $depTitle = "";
        if ($deptId) {
            $cc = Department::query()->find($deptId);

            if ($cc) {
                $depTitle = $cc["dept_name"];
            } else {
                abort(404, "Department not available");
            }
        }

        $pageTitle = "Department Heads";
        if ($depTitle != "") {
            $pageTitle = $depTitle . " | " . $pageTitle;
        }

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new DepartmentHead());

        $this->repository->setColumns("id", "profile_image", "hod_type", "name", "department", "date_from", "date_till", "status", "created_at")
            ->setColumnLabel("name", "Name of HOD/Assistant HOD")
            ->setColumnLabel("hod_type", "HOD Type")
            ->setColumnLabel("department", "Department")
            ->setColumnLabel("date_from", "Period From")
            ->setColumnLabel("date_till", "Period Till")
            ->setColumnLabel("status", "Current/Former Status")

            ->setColumnDBField("department", "dept_id")
            ->setColumnFKeyField("department", "dept_id")
            ->setColumnRelation("department", "department", "dept_name")

            ->setColumnDisplay("profile_image", array($this->repository, 'displayImageAs'), [$this->repository->upload_dir, 150])
            ->setColumnDisplay("department", array($this->repository, 'displayRelationAs'), ["department", "dept_id", "dept_name"])
            ->setColumnDisplay("status", array($this->repository, 'displayStatusAs'), [$this->repository->statuses])
            ->setColumnDisplay("hod_type", array($this->repository, 'displayStatusAs'), [$this->repository->hodTypes])
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("name", "text", [
                ["relation" => "person", "fields" => ["name_in_full", "given_name", "name_with_init", "surname"]]
            ])
            ->setColumnFilterMethod("department", "select", URL::to("/academic/department/search_data"))
            ->setColumnFilterMethod("date_from", "date_after")
            ->setColumnFilterMethod("date_till", "date_before")
            ->setColumnFilterMethod("status", "select", $this->repository->statuses)
            ->setColumnFilterMethod("hod_type", "select", $this->repository->hodTypes)

            ->setColumnSearchability("name", false)
            ->setColumnSearchability("profile_image", false)
            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false)

            ->setColumnOrderability("name", false);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = "Department Heads | Trashed";
            if ($deptId) {
                if ($depTitle != "") {
                    $tableTitle = $depTitle . " | " . $tableTitle;

                    $this->repository->setUrl("list", "/academic/department_head/" . $deptId);
                }
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = "Department Heads";
            if ($deptId) {
                if ($depTitle != "") {
                    $tableTitle = $depTitle . " | " . $tableTitle;

                    $this->repository->setUrl("trashList", "/academic/department_head/trash/" . $deptId);
                }
            }

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export");
        }

        if ($deptId) {
            $this->repository->setUrl("add", "/academic/department_head/create/" . $deptId);

            $this->repository->unsetColumns("department");
            $query = $query->where(["dept_id" => $deptId]);
        } else {
            $query = $query->with(["department"]);
        }

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param $deptId
     * @return Factory|View
     */
    public function trash($deptId = false)
    {
        $this->trash = true;
        return $this->index($deptId);
    }

    /**
     * Show the form for creating a new resource.
     * @param $deptId
     * @return Factory|View
     */
    public function create($deptId = false)
    {
        $model = new DepartmentHead();

        if ($deptId) {
            $model->dept_id = $deptId;
            $model->department()->get();
        }
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/" . request()->path();

        $urls = [];
        if ($deptId) {
            $urls["listUrl"] = URL::to("/academic/department_head/" . $deptId);
        } else {
            $urls["listUrl"] = URL::to("/academic/department_head");
        }

        $this->repository->setPageUrls($urls);

        $hodTypes = $this->repository->hodTypes;

        return view('academic::department_head.create', compact('formMode', 'formSubmitUrl', 'record', 'hodTypes'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(): JsonResponse
    {
        $model = new DepartmentHead();

        $model = $this->repository->getValidatedData($model, [
            "dept_id" => "required|exists:departments,dept_id",
            "hod_type" => "required",
            "person_id" => "required|exists:people,id",
            "description" => "",
            "date_from" => "required|date",
            "date_till" => [Rule::requiredIf(function () {
                return request()->post("status") == "0";
            })],
            "status" => "required",
        ], [], ["dept_id" => "Department", "person_id" => "HOD/Assistant HOD Name", "date_from" => "Date From", "date_till" => "Date Till"]);

        if ($this->repository->isValidData) {

            $profileImage = $this->repository->uploadImage();

            if ($profileImage !== "") {
                $model->profile_image = $profileImage;
            }

            $response = $this->repository->saveModel($model);

            if ($response["notify"]["status"] === "success") {

                if ($model->status == 1) {

                    $this->repository->resetOtherCurrent($model->dept_id, $model->id, $model->hod_type);
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
        $model = DepartmentHead::query()->find($id);

        if ($model) {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/department_head/create");
            $urls["listUrl"] = URL::to("/academic/department_head");

            $this->repository->setPageUrls($urls);

            return view('academic::department_head.view', compact('record'));
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
        $model = DepartmentHead::with(["person", "department"])->find($id);

        if ($model) {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/" . request()->path();

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/department_head/create");
            $urls["listUrl"] = URL::to("/academic/department_head");

            $this->repository->setPageUrls($urls);

            $hodTypes = $this->repository->hodTypes;

            return view('academic::department_head.create', compact('formMode', 'formSubmitUrl', 'record', 'hodTypes'));
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
        $model = DepartmentHead::query()->find($id);

        if ($model) {
            $model = $this->repository->getValidatedData($model, [
                "dept_id" => "required|exists:departments,dept_id",
                "hod_type" => "required",
                "person_id" => "required|exists:people,id",
                "description" => "",
                "date_from" => "required|date",
                "date_till" => [Rule::requiredIf(function () {
                    return request()->post("status") == "0";
                })],
                "status" => "required",
            ], [], ["dept_id" => "Department", "person_id" => "HOD/Assistant HOD Name", "date_from" => "Date From", "date_till" => "Date Till"]);

            if ($this->repository->isValidData) {

                $profileImage = $this->repository->uploadImage($model->profile_image);

                if ($profileImage !== "") {
                    $model->profile_image = $profileImage;
                }

                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] === "success") {

                    if ($model->status == 1) {

                        $this->repository->resetOtherCurrent($model->dept_id, $model->id, $model->hod_type);
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
        $model = DepartmentHead::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = DepartmentHead::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new DepartmentHead();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
