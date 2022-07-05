<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\Course;
use Modules\Academic\Entities\CourseDocument;
use Modules\Academic\Repositories\CourseDocumentRepository;

class CourseDocumentController extends Controller
{
    private CourseDocumentRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new CourseDocumentRepository();
    }

    /**
     * Display a listing of the resource.
     * @param $courseId
     * @return Factory|View
     */
    public function index($courseId)
    {
        $course = Course::query()->find($courseId);

        if ($course) {
            $pageTitle = $course["name"] . " | Course Documents";
            $tableTitle = $pageTitle;

            $this->repository->setPageTitle($pageTitle);

            $this->repository->initDatatable(new CourseDocument());

            $this->repository->setColumns("id", "document_name", "download", "created_at")
                ->setColumnDisplay("download", array($this->repository, 'displayListButtonAs'), ["Download", URL::to("/academic/course_document/download/")])
                ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

                ->setColumnSearchability("created_at", false)

                ->setColumnDBField("download", "id");

            if ($this->trash) {
                $query = $this->repository->model::onlyTrashed();

                $this->repository->setTableTitle($tableTitle . " | Trashed")
                    ->enableViewData("list", "restore", "export")
                    ->disableViewData("add", "view", "edit", "delete")
                    ->setUrl("list", $this->repository->getUrl("list") . "/" . $courseId);
            } else {
                $query = $this->repository->model::query();

                $this->repository->setTableTitle($tableTitle)
                    ->enableViewData("trashList", "view", "trash", "export")
                    ->disableViewData("add", "view", "edit")
                    ->setUrl("trashList", $this->repository->getUrl("trashList") . "/" . $courseId);
            }

            $query->where("course_id", "=", $courseId);

            return $this->repository->render("academic::layouts.master")->index($query);
        } else {
            abort(404);
        }
    }

    /**
     * Display a listing of the resource.
     * @param $courseId
     * @return Factory|View
     */
    public function trash($courseId)
    {
        $this->trash = true;
        return $this->index($courseId);
    }

    /**
     * Move the record to trash
     * @param $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = CourseDocument::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = CourseDocument::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Show the specified resource.
     * @param $id
     * @return void
     */
    public function download($id)
    {
        $model = CourseDocument::query()->find($id);

        if($model) {

            return $this->repository->triggerDownloadDocument($model->course, $model->id);
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
        $model = new CourseDocument();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
