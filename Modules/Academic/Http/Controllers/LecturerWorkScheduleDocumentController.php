<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\LecturerWorkSchedule;
use Modules\Academic\Entities\LecturerWorkScheduleDocument;
use Modules\Academic\Repositories\LecturerWorkScheduleDocumentRepository;
use Modules\Admin\Repositories\AdminActivityRepository;

class LecturerWorkScheduleDocumentController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new LecturerWorkScheduleDocumentRepository();
    }

    /**
     * Display a listing of the resource.
     * @param $lwScheduleId
     * @return Factory|View
     */
    public function index($lwScheduleId)
    {
        $lwSchedule = LecturerWorkSchedule::withTrashed()->find($lwScheduleId);

        if ($lwSchedule) {
            $pageTitle = $lwSchedule["name"] . " | Lecturer Work Schedule Documents";
            $tableTitle = $pageTitle;

            $this->repository->setPageTitle($pageTitle);

            $this->repository->initDatatable(new LecturerWorkScheduleDocument());

            $this->repository->setColumns("id", "document_name", "download", "created_at")
                ->setColumnDisplay("download", array($this->repository, 'displayListButtonAs'), ["Download", URL::to("/academic/lecturer_work_schedule_document/download/")])
                ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

                ->setColumnSearchability("created_at", false)

                ->setColumnDBField("download", "id");

            if ($this->trash) {
                $query = $this->repository->model::onlyTrashed();

                $this->repository->setTableTitle($tableTitle . " | Trashed")
                    ->enableViewData("list", "restore", "export")
                    ->disableViewData("add", "view", "edit", "delete")
                    ->setUrl("list", $this->repository->getUrl("list") . "/" . $lwScheduleId);
            } else {
                $query = $this->repository->model::query();

                $this->repository->setTableTitle($tableTitle)
                    ->enableViewData("trashList", "view", "trash", "export")
                    ->disableViewData("add", "view", "edit")
                    ->setUrl("trashList", $this->repository->getUrl("trashList") . "/" . $lwScheduleId);
            }

            $query->where("lecturer_work_schedule_id", "=", $lwScheduleId);

            return $this->repository->render("academic::layouts.master")->index($query);
        } else {
            abort(404);
        }
    }

    /**
     * Display a listing of the resource.
     * @param $lwScheduleId
     * @return Factory|View
     */
    public function trash($lwScheduleId)
    {
        $this->trash = true;
        return $this->index($lwScheduleId);
    }

    /**
     * Move the record to trash
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = LecturerWorkScheduleDocument::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = LecturerWorkScheduleDocument::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return void
     */
    public function download($id)
    {
        $model = LecturerWorkScheduleDocument::withTrashed()->find($id);

        if($model) {

            return $this->repository->triggerDownloadDocument($model->workSchedule, $model->id);
        } else {
            abort(404);
        }
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new LecturerWorkScheduleDocument();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
