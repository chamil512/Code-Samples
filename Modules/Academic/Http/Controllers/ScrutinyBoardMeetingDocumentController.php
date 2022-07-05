<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\ScrutinyBoardMeeting;
use Modules\Academic\Entities\ScrutinyBoardMeetingDocument;
use Modules\Academic\Repositories\ScrutinyBoardMeetingDocumentRepository;

class ScrutinyBoardMeetingDocumentController extends Controller
{
    private ScrutinyBoardMeetingDocumentRepository $repository;
    private bool $trash = false;

    public function __construct()
    {
        $this->repository = new ScrutinyBoardMeetingDocumentRepository();
    }

    /**
     * Display a listing of the resource.
     * @param $meetingId
     * @return Factory|View
     */
    public function index($meetingId)
    {
        $meeting = ScrutinyBoardMeeting::query()->find($meetingId);

        if ($meeting) {
            $pageTitle = $meeting["name"] . " | Scrutiny Board Meeting Documents";
            $tableTitle = $pageTitle;

            $this->repository->setPageTitle($pageTitle);

            $this->repository->initDatatable(new ScrutinyBoardMeetingDocument());

            $this->repository->setColumns("id", "document_name", "download", "created_at")
                ->setColumnDisplay("download", array($this->repository, 'displayListButtonAs'), ["Download", URL::to("/academic/scrutiny_board_meeting_document/download/")])
                ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

                ->setColumnSearchability("created_at", false)

                ->setColumnDBField("download", "id");

            if ($this->trash) {
                $query = $this->repository->model::onlyTrashed();

                $this->repository->setTableTitle($tableTitle . " | Trashed")
                    ->enableViewData("list", "restore", "export")
                    ->disableViewData("add", "view", "edit", "delete")
                    ->setUrl("list", $this->repository->getUrl("list") . "/" . $meetingId);
            } else {
                $query = $this->repository->model::query();

                $this->repository->setTableTitle($tableTitle)
                    ->enableViewData("trashList", "view", "trash", "export")
                    ->disableViewData("add", "view", "edit")
                    ->setUrl("trashList", $this->repository->getUrl("trashList") . "/" . $meetingId);
            }

            $query->where("scrutiny_board_meeting_id", "=", $meetingId);

            return $this->repository->render("academic::layouts.master")->index($query);
        } else {
            abort(404);
        }
    }

    /**
     * Display a listing of the resource.
     * @param $meetingId
     * @return Factory|View
     */
    public function trash($meetingId)
    {
        $this->trash = true;
        return $this->index($meetingId);
    }

    /**
     * Move the record to trash
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = ScrutinyBoardMeetingDocument::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = ScrutinyBoardMeetingDocument::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return void
     */
    public function download($id)
    {
        $model = ScrutinyBoardMeetingDocument::withTrashed()->find($id);

        if($model) {

            return $this->repository->triggerDownloadDocument($model->meeting, $model->id);
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
        $model = new ScrutinyBoardMeetingDocument();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
