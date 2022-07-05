<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\AcademicMeetingAgenda;
use Modules\Academic\Repositories\AcademicMeetingAgendaRepository;
use Exception;

class AcademicMeetingAgendaController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new AcademicMeetingAgendaRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("Academic Meeting Agendas");

        $this->repository->initDatatable(new AcademicMeetingAgenda());

        $this->repository->setColumns("id", "agenda_name", "agenda_status", "created_at")
            ->setColumnLabel("agenda_status", "Status")
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])
            ->setColumnDisplay("agenda_status", array($this->repository, 'displayStatusActionAs'))

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("Academic Meeting Agendas | Trashed")
                ->enableViewData("list", "restore", "export")
                ->disableViewData("edit", "view", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("Academic Meeting Agendas")
                ->enableViewData("trashList", "view", "trash", "export");
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
        $model = new AcademicMeetingAgenda();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/".request()->path();

        $urls = [];
        $urls["listUrl"]=URL::to("/academic/academic_meeting_agenda");

        $this->repository->setPageUrls($urls);

        return view('academic::academic_meeting_agenda.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     */
    public function store()
    {
        $model = new AcademicMeetingAgenda();

        $model = $this->repository->getValidatedData($model, [
            "agenda_name" => "required",
            "agenda_status" => "required|digits:1",
        ], [], ["agenda_name" => "Agenda Name", "agenda_status" => "Agenda Status"]);

        if($this->repository->isValidData)
        {
            DB::beginTransaction();
            try {
                $response = $this->repository->saveModel($model);

                if($response["notify"]["status"]=="success")
                {
                    $response = $this->repository->triggerUpdateAgendaItems($model);
                }

                $response["notify"]["status"]="success";
                $response["notify"]["notify"][]="Successfully saved the agenda.";
            }
            catch (Exception $error)
            {
                $response["notify"]["status"]="failed";
                $response["notify"]["notify"][]="Error occurred while saving agenda items.";
                $response["notify"]["notify"][]="Please send following error to your system administrator.";
                $response["notify"]["notify"][]=$error->getMessage();
            }

            if($response["notify"]["status"]=="success")
            {
                DB::commit();

                $model->load("agendaItems");
                $response["data"] = $this->repository->getAgendaItems($model);
            }
            else
            {
                DB::rollBack();
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
        $model = AcademicMeetingAgenda::with(["agendaItems"])->find($id);

        if($model)
        {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/academic_meeting_agenda/create");
            $urls["listUrl"]=URL::to("/academic/academic_meeting_agenda");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_meeting_agenda.view', compact('record'));
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
        $model = AcademicMeetingAgenda::query()->find($id);

        if($model)
        {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/academic_meeting_agenda/create");
            $urls["listUrl"]=URL::to("/academic/academic_meeting_agenda");

            $this->repository->setPageUrls($urls);

            return view('academic::academic_meeting_agenda.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = AcademicMeetingAgenda::query()->find($id);

        if($model)
        {
            $model = $this->repository->getValidatedData($model, [
                "agenda_name" => "required",
                "agenda_status" => "required|digits:1",
            ], [], ["agenda_name" => "Agenda Name", "agenda_status" => "Agenda Status"]);

            if($this->repository->isValidData)
            {
                DB::beginTransaction();
                try {
                    $response = $this->repository->saveModel($model);

                    if($response["notify"]["status"]=="success")
                    {
                        $response = $this->repository->triggerUpdateAgendaItems($model);
                    }

                    $response["notify"]["status"]="success";
                    $response["notify"]["notify"][]="Successfully saved the agenda.";
                }
                catch (Exception $error)
                {
                    $response["notify"]["status"]="failed";
                    $response["notify"]["notify"][]="Error occurred while saving agenda items.";
                    $response["notify"]["notify"][]="Please send following error to your system administrator.";
                    $response["notify"]["notify"][]=$error->getMessage();
                }

                if($response["notify"]["status"]=="success")
                {
                    DB::commit();

                    $model->load("agendaItems");
                    $response["data"] = $this->repository->getAgendaItems($model);
                }
                else
                {
                    DB::rollBack();
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
        $model = AcademicMeetingAgenda::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = AcademicMeetingAgenda::withTrashed()->find($id);

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

            $query = AcademicMeetingAgenda::query()
                ->select("id", "agenda_name")
                ->where("agenda_status", "=", "1")
                ->orderBy("agenda_name");

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
                $query = $query->where("agenda_name", "LIKE", "%".$searchText."%");
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

    public function getAgendaItems($id)
    {
        $model = AcademicMeetingAgenda::query()->find($id);

        if($model)
        {
            $notify["status"]="success";

            $response["notify"]=$notify;
            $response["data"]=$this->repository->getAgendaItems($model);
        }
        else
        {
            $notify = array();
            $notify["status"]="failed";
            $notify["notify"][]="Details loading was failed. Requested record does not exist.";

            $response["notify"]=$notify;
        }

        return $this->repository->handleResponse($response);
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return mixed
     */
    public function changeStatus($id)
    {
        $model = AcademicMeetingAgenda::query()->find($id);
        return $this->repository->updateStatus($model, "agenda_status");
    }

    /**
     * Display a listing of the resource.
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new AcademicMeetingAgenda();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
