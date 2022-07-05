<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Academic\Entities\Lecturer;
use Modules\Academic\Entities\LecturerAvailabilityTerm;
use Modules\Academic\Repositories\LecturerAvailabilityHourRepository;
use Modules\Academic\Repositories\LecturerAvailabilityTermRepository;

class LecturerAvailabilityTermController extends Controller
{
    private LecturerAvailabilityTermRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new LecturerAvailabilityTermRepository();
    }

    /**
     * Display a listing of the resource.
     * @param int $lecturerId
     * @return Factory|View
     */
    public function index($lecturerId)
    {
        $lecturer = Lecturer::query()->find($lecturerId);

        $lecTitle = "";
        if ($lecturer) {
            $lecTitle = $lecturer["name_with_init"];
        } else {
            abort(404, "Lecturer not available");
        }
        $pageTitle = $lecTitle . " | Lecturer Availability Terms";

        $this->repository->setPageTitle($pageTitle);

        $this->repository->initDatatable(new LecturerAvailabilityTerm());

        $this->repository->setColumns("id", "date_from", "date_till", "created_at")
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnSearchability("created_at", false);

        if ($this->trash) {
            $query = $this->repository->model::onlyTrashed();

            $tableTitle = $lecTitle . " | Lecturer Availability Terms | Trashed";
            $this->repository->setUrl("list", "/academic/lecturer_availability_term/" . $lecturerId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("list", "view", "restore", "export")
                ->disableViewData("edit", "delete");
        } else {
            $query = $this->repository->model::query();

            $tableTitle = $lecTitle . " | Lecturer Availability Terms";
            $this->repository->setCustomControllerUrl("/academic/lecturer_availability_term", ["list"], false)
                ->setUrl("trashList", "/academic/lecturer_availability_term/trash/" . $lecturerId);

            $this->repository->setTableTitle($tableTitle)
                ->enableViewData("trashList", "trash", "export");
        }

        $this->repository->setUrl("add", "/academic/lecturer_availability_term/create/" . $lecturerId);

        $query = $query->where(["lecturer_id" => $lecturerId]);

        return $this->repository->render("academic::layouts.master")->index($query);
    }

    /**
     * Display a listing of the resource.
     * @param int $lecturerId
     * @return Factory|View
     */
    public function trash($lecturerId)
    {
        $this->trash = true;
        return $this->index($lecturerId);
    }

    /**
     * Show the form for creating a new resource.
     * @param mixed $lecturerId
     * @return Factory|View
     */
    public function create($lecturerId)
    {
        $lecturer = Lecturer::query()->find($lecturerId);

        if (!$lecturer) {
            abort(404, "Lecturer not available");
        }

        $pageTitle = "Lecturer Availability Terms | Add New | [Lecturer : " . $lecturer["name"]. "]";

        $this->repository->setPageTitle($pageTitle);

        $model = new LecturerAvailabilityTerm();
        $model->lecturer = $lecturer;

        $record = $model;

        $formMode = "add";
        $formSubmitUrl = URL::to("/" . request()->path());

        $urls = [];
        $urls["listUrl"] = URL::to("/academic/lecturer_availability_term/" . $lecturerId);

        $this->repository->setPageUrls($urls);

        return view('academic::lecturer_availability_term.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @param $lecturerId
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store($lecturerId): JsonResponse
    {
        $lecturer = Lecturer::query()->find($lecturerId);

        if (!$lecturer) {
            abort(404, "Lecturer not available");
        }

        $model = new LecturerAvailabilityTerm();

        $model = $this->repository->getValidatedData($model, [
            "date_from" => "required|date",
            "date_till" => "required|date",
        ], [], ["date_form" => "Date from", "date_till" => "Date till"]);

        if ($this->repository->isValidData) {
            $model->lecturer_id = $lecturerId;

            $response = $this->repository->saveModel($model);

            if ($response["notify"]["status"] == "success") {
                $lCMRepo = new LecturerAvailabilityHourRepository();
                $lCMRepo->update($model);
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
        $model = LecturerAvailabilityTerm::withTrashed()->find($id);

        if ($model) {
            $lecturerId = $model->lecturer_id;
            $record = $model->toArray();

            $pageTitle = $record["name"];

            $this->repository->setPageTitle($pageTitle);

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/lecturer_availability_term/create/" . $lecturerId);
            $urls["editUrl"] = URL::to("/academic/lecturer_availability_term/edit/" . $id);
            $urls["listUrl"] = URL::to("/academic/lecturer_availability_term/" . $lecturerId);
            $urls["lecturerUrl"] = URL::to("/academic/lecturer/view/" . $lecturerId);

            $this->repository->setPageUrls($urls);

            return view('academic::lecturer_availability_term.view', compact('record'));
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
        $model = LecturerAvailabilityTerm::with(["lecturer", "availabilityHours"])->find($id);

        if ($model) {
            $lecturerId = $model->lecturer_id;

            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = URL::to("/" . request()->path());

            $pageTitle = "Lecturer Availability Terms | Edit " . $record["name"];

            $this->repository->setPageTitle($pageTitle);

            $urls = [];
            $urls["addUrl"] = URL::to("/academic/lecturer_availability_term/create/" . $lecturerId);
            $urls["listUrl"] = URL::to("/academic/lecturer_availability_term/" . $lecturerId);

            $this->repository->setPageUrls($urls);

            return view('academic::lecturer_availability_term.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = LecturerAvailabilityTerm::query()->find($id);

        if ($model) {

            $model = $this->repository->getValidatedData($model, [
                "date_from" => "required|date",
                "date_till" => "required|date",
            ], [], ["date_form" => "Date from", "date_till" => "Date till"]);

            if ($this->repository->isValidData) {
                $response = $this->repository->saveModel($model);

                if ($response["notify"]["status"] == "success") {
                    $lCMRepo = new LecturerAvailabilityHourRepository();
                    $lCMRepo->update($model);
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
        $model = LecturerAvailabilityTerm::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = LecturerAvailabilityTerm::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $options = [];
        $options["title"] = "Lecturer Availability Term";

        $model = new LecturerAvailabilityTerm();
        return $this->repository->recordHistory($model, $modelHash, $id, $options);
    }
}
