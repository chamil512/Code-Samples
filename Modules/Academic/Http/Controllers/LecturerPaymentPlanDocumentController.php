<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\LecturerPaymentPlan;
use Modules\Academic\Entities\LecturerPaymentPlanDocument;
use Modules\Academic\Repositories\LecturerPaymentPlanDocumentRepository;

class LecturerPaymentPlanDocumentController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new LecturerPaymentPlanDocumentRepository();
    }

    /**
     * Display a listing of the resource.
     * @param $paymentPlanId
     * @return Factory|View
     */
    public function index($paymentPlanId)
    {
        $paymentPlan = LecturerPaymentPlan::query()->find($paymentPlanId);

        if ($paymentPlan) {
            $pageTitle = $paymentPlan["name"] . " | Payment Plan Documents";
            $tableTitle = $pageTitle;

            $this->repository->setPageTitle($pageTitle);

            $this->repository->initDatatable(new LecturerPaymentPlanDocument());

            $this->repository->setColumns("id", "document_name", "download", "created_at")
                ->setColumnDisplay("download", array($this->repository, 'displayListButtonAs'), ["Download", URL::to("/academic/lecturer_payment_plan_document/download/")])
                ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

                ->setColumnSearchability("created_at", false)

                ->setColumnDBField("download", "id");

            if ($this->trash) {
                $query = $this->repository->model::onlyTrashed();

                $this->repository->setTableTitle($tableTitle . " | Trashed")
                    ->enableViewData("list", "restore", "export")
                    ->disableViewData("add", "view", "edit", "delete")
                    ->setUrl("list", $this->repository->getUrl("list") . "/" . $paymentPlanId);
            } else {
                $query = $this->repository->model::query();

                $this->repository->setTableTitle($tableTitle)
                    ->enableViewData("trashList", "view", "trash", "export")
                    ->disableViewData("add", "view", "edit")
                    ->setUrl("trashList", $this->repository->getUrl("trashList") . "/" . $paymentPlanId);
            }

            $query->where("lecturer_payment_plan_id", "=", $paymentPlanId);

            return $this->repository->render("academic::layouts.master")->index($query);
        } else {
            abort(404);
        }
    }

    /**
     * Display a listing of the resource.
     * @param $paymentPlanId
     * @return Factory|View
     */
    public function trash($paymentPlanId)
    {
        $this->trash = true;
        return $this->index($paymentPlanId);
    }

    /**
     * Move the record to trash
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function delete($id)
    {
        $model = LecturerPaymentPlanDocument::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = LecturerPaymentPlanDocument::withTrashed()->find($id);

        return $this->repository->restore($model);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return void
     */
    public function download($id)
    {
        $model = LecturerPaymentPlanDocument::withTrashed()->find($id);

        if($model) {

            return $this->repository->triggerDownloadDocument($model->paymentPlan, $model->id);
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
        $model = new LecturerPaymentPlanDocument();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
