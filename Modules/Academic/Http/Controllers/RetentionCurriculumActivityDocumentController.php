<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\RetentionCurriculumActivity;
use Modules\Academic\Entities\RetentionCurriculumActivityDocument;
use Modules\Academic\Repositories\RetentionCurriculumActivityDocumentRepository;

class RetentionCurriculumActivityDocumentController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new RetentionCurriculumActivityDocumentRepository();
    }

    /**
     * Display a listing of the resource.
     * @param $rcActivityId
     * @return Factory|View
     */
    public function index($rcActivityId)
    {
        $rcActivity = RetentionCurriculumActivity::query()->find($rcActivityId);

        if ($rcActivity) {
            $pageTitle = $rcActivity["name"] . " | Retention Curriculum Activity Documents";
            $tableTitle = $pageTitle;

            $this->repository->setPageTitle($pageTitle);

            $this->repository->initDatatable(new RetentionCurriculumActivityDocument());

            $this->repository->setColumns("id", "document_name", "download", "created_at")
                ->setColumnDisplay("download", array($this->repository, 'displayListButtonAs'), ["Download", URL::to("/academic/retention_curriculum_activity_document/download/")])
                ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

                ->setColumnSearchability("created_at", false)

                ->setColumnDBField("download", "id");

            if ($this->trash) {
                $query = $this->repository->model::onlyTrashed();

                $this->repository->setTableTitle($tableTitle . " | Trashed")
                    ->enableViewData("list", "restore", "export")
                    ->disableViewData("add", "view", "edit", "delete")
                    ->setUrl("list", $this->repository->getUrl("list") . "/" . $rcActivityId);
            } else {
                $query = $this->repository->model::query();

                $this->repository->setTableTitle($tableTitle)
                    ->enableViewData("trashList", "view", "trash", "export")
                    ->disableViewData("add", "view", "edit")
                    ->setUrl("trashList", $this->repository->getUrl("trashList") . "/" . $rcActivityId);
            }

            $query->where("rc_activity_id", "=", $rcActivityId);

            return $this->repository->render("academic::layouts.master")->index($query);
        } else {
            abort(404);
        }
    }

    /**
     * Display a listing of the resource.
     * @param $rcActivityId
     * @return Factory|View
     */
    public function trash($rcActivityId)
    {
        $this->trash = true;
        return $this->index($rcActivityId);
    }

    /**
     * Move the record to trash
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = RetentionCurriculumActivityDocument::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = RetentionCurriculumActivityDocument::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return void
     */
    public function download($id)
    {
        $model = RetentionCurriculumActivityDocument::withTrashed()->find($id);

        if($model) {

            return $this->repository->triggerDownloadDocument($model->rcActivity, $model->id);
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
        $model = new RetentionCurriculumActivityDocument();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
