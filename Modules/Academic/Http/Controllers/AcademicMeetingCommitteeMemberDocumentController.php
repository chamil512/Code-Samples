<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicMeetingCommitteeMember;
use Modules\Academic\Entities\AcademicMeetingCommitteeMemberDocument;
use Modules\Academic\Repositories\AcademicMeetingCommitteeMemberDocumentRepository;

class AcademicMeetingCommitteeMemberDocumentController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new AcademicMeetingCommitteeMemberDocumentRepository();
    }

    /**
     * Display a listing of the resource.
     * @param $committeeMemberId
     * @return Factory|View|void
     */
    public function index($committeeMemberId)
    {
        $committeeMember = AcademicMeetingCommitteeMember::query()->find($committeeMemberId);

        if ($committeeMember) {
            $pageTitle = $committeeMember["name"] . " | Academic Meeting Committee Member Documents";
            $tableTitle = $pageTitle;

            $this->repository->setPageTitle($pageTitle);

            $this->repository->initDatatable(new AcademicMeetingCommitteeMemberDocument());

            $this->repository->setColumns("id", "document_name", "appointed_from", "appointed_till", "download", "created_at")
                ->setColumnDisplay("download", array($this->repository, 'displayListButtonAs'), ["Download", URL::to("/academic/academic_meeting_committee_member_document/download/")])
                ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

                ->setColumnSearchability("created_at", false)

                ->setColumnDBField("download", "id");

            if ($this->trash) {
                $query = $this->repository->model::onlyTrashed();

                $this->repository->setTableTitle($tableTitle . " | Trashed")
                    ->enableViewData("list", "restore", "export")
                    ->disableViewData("add", "view", "edit", "delete")
                    ->setUrl("list", $this->repository->getUrl("list") . "/" . $committeeMemberId);
            } else {
                $query = $this->repository->model::query();

                $this->repository->setTableTitle($tableTitle)
                    ->enableViewData("trashList", "view", "trash", "export")
                    ->disableViewData("add", "view", "edit")
                    ->setUrl("trashList", $this->repository->getUrl("trashList") . "/" . $committeeMemberId);
            }

            $query->where("academic_meeting_committee_member_id", "=", $committeeMemberId);

            return $this->repository->render("academic::layouts.master")->index($query);
        } else {
            abort(404);
        }
    }

    /**
     * Display a listing of the resource.
     * @param $committeeMemberId
     * @return Factory|View
     */
    public function trash($committeeMemberId)
    {
        $this->trash = true;
        return $this->index($committeeMemberId);
    }

    /**
     * Move the record to trash
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = AcademicMeetingCommitteeMemberDocument::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = AcademicMeetingCommitteeMemberDocument::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return void
     */
    public function download($id)
    {
        $model = AcademicMeetingCommitteeMemberDocument::withTrashed()->find($id);

        if($model) {

            return $this->repository->triggerDownloadDocument($model->committeeMember, $model->id);
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
        $model = new AcademicMeetingCommitteeMemberDocument();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
