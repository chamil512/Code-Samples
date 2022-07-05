<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Academic\Entities\ExternalIndividual;
use Modules\Academic\Repositories\ExternalIndividualRepository;
use Modules\Academic\Repositories\PersonBankingInformationRepository;
use Modules\Academic\Repositories\PersonContactInformationRepository;
use Modules\Academic\Repositories\PersonDocumentRepository;

class ExternalIndividualController extends Controller
{
    private $repository;
    private $trash = false;

    public function __construct()
    {
        $this->repository = new ExternalIndividualRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function index()
    {
        $this->repository->setPageTitle("External Individuals");

        $this->repository->initDatatable(new ExternalIndividual());

        $this->repository->setColumns("id", "name_with_init", "nic_no", "contact_info", "status", "created_at")
            ->setColumnLabel("name_with_init", "Name")
            ->setColumnLabel("status", "Status")
            ->setColumnDisplay("contact_info", array($this->repository, 'displayContactInfoAs'))
            ->setColumnDisplay("status", array($this->repository, 'displayStatusActionAs'))
            ->setColumnDisplay("created_at", array($this->repository, 'displayCreatedAtAs'), [true])

            ->setColumnFilterMethod("name_with_init", "text", ["name_in_full", "given_name", "name_with_init", "surname"])
            ->setColumnFilterMethod("status", "select", $this->repository->statuses)

            ->setColumnSearchability("created_at", false)
            ->setColumnSearchability("updated_at", false)
            ->setColumnSearchability("updated_at", false)
            ->setColumnOrderability("contact_info", false)

            ->setColumnDBField("contact_info", "CONCAT(contact_no, ' ', email)")
            ->setColumnDBField("courses", $this->repository->primaryKey);

        if($this->trash)
        {
            $query = $this->repository->model::onlyTrashed();

            $this->repository->setTableTitle("External Individuals | Trashed")
                ->enableViewData("list", "restore", "export")
                ->disableViewData("view", "edit", "delete");
        }
        else
        {
            $query = $this->repository->model::query();

            $this->repository->setTableTitle("External Individuals")
                ->enableViewData("trashList", "trash", "export");
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
        $model = new ExternalIndividual();
        $record = $model;

        $formMode = "add";
        $formSubmitUrl = "/".request()->path();

        $urls = [];
        $urls["listUrl"]=URL::to("/academic/external_individual");

        $this->repository->setPageUrls($urls);

        return view('academic::external_individual.create', compact('formMode', 'formSubmitUrl', 'record'));
    }

    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     */
    public function store()
    {
        $model = new ExternalIndividual();

        $model = $this->repository->getValidatedData($model, [
            "title_id" => "required|exists:honorific_titles,title_id",
            "given_name" => "required",
            "surname" => "required",
            "name_in_full" => "required",
            "name_with_init" => "required",
            "date_of_birth" => "required|date",
            "nic_no" => [Rule::requiredIf(function () { return request()->post("passport_no") == "";})],
            "passport_no" => [Rule::requiredIf(function () { return request()->post("nic_no") == "";})],
            "perm_address" => "required",
            "perm_work_address" => "",
            "contact_no" => "required|digits_between:8,15",
            "email" => "required",
            "qualification_id" => "required|exists:academic_qualifications,qualification_id",
            "qualification_level_id" => "required|exists:academic_qualification_levels,id",
            "university_id" => "required|exists:universities,university_id",
            "qualified_year" => "required|digits:4",
            "status" => "required",
        ], [], ["title_id" => "Title", "name_with_init" => "Name with initials", "perm_address" => "Permanent address", "qualification_id" => "Highest Qualification", "university_id" => "Qualified University", "qualified_year" => "Qualified Year"]);

        if($this->repository->isValidData)
        {
            //will be saved as 1 until approval process is implemented
            $model->status = "1";
            $model->staff_type = 2;
            $response = $this->repository->saveModel($model);

            if($response["notify"]["status"] == "success")
            {
                $cIRepo = new PersonContactInformationRepository();
                $cIRepo->update($model);

                $docRepo = new PersonDocumentRepository();
                $docRepo->upload_dir = "public/external_individual_documents/";
                $docRepo->update($model);

                $bIRepo = new PersonBankingInformationRepository();
                $bIRepo->update($model);
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
        $model = ExternalIndividual::query()->find($id);

        if($model)
        {
            $record = $model->toArray();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/external_individual/create");
            $urls["listUrl"]=URL::to("/academic/external_individual");

            $this->repository->setPageUrls($urls);

            return view('academic::external_individual.view', compact('record'));
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
        $model = ExternalIndividual::with(["contactInfo", "documents", "bankingInfo", "qualification", "qualificationLevel", "university", "title"])->find($id);

        if($model)
        {
            $record = $model->toArray();
            $formMode = "edit";
            $formSubmitUrl = "/".request()->path();

            $urls = [];
            $urls["addUrl"]=URL::to("/academic/external_individual/create");
            $urls["listUrl"]=URL::to("/academic/external_individual");
            $urls["downloadUrl"]=URL::to("/academic/external_individual/download_document/".$id) . "/";

            $this->repository->setPageUrls($urls);

            return view('academic::external_individual.create', compact('formMode', 'formSubmitUrl', 'record'));
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
        $model = ExternalIndividual::query()->find($id);

        if($model)
        {
            $model = $this->repository->getValidatedData($model, [
                "title_id" => "required|exists:honorific_titles,title_id",
                "given_name" => "required",
                "surname" => "required",
                "name_in_full" => "required",
                "name_with_init" => "required",
                "date_of_birth" => "required|date",
                "nic_no" => [Rule::requiredIf(function () { return request()->post("passport_no") == "";})],
                "passport_no" => [Rule::requiredIf(function () { return request()->post("nic_no") == "";})],
                "perm_address" => "required",
                "perm_work_address" => "",
                "contact_no" => "required|digits_between:8,15",
                "email" => "required",
                "qualification_id" => "required|exists:academic_qualifications,qualification_id",
                "qualification_level_id" => "required|exists:academic_qualification_levels,id",
                "university_id" => "required|exists:universities,university_id",
                "qualified_year" => "required|digits:4",
                "status" => "required",
            ], [], ["title_id" => "Title", "name_with_init" => "Name with initials", "perm_address" => "Permanent address", "qualification_id" => "Highest Qualification", "university_id" => "Qualified University"]);

            if($this->repository->isValidData)
            {
                $response = $this->repository->saveModel($model);

                if($response["notify"]["status"] == "success")
                {
                    $cIRepo = new PersonContactInformationRepository();
                    $cIRepo->update($model);

                    $docRepo = new PersonDocumentRepository();
                    $docRepo->upload_dir = "public/external_individual_documents/";
                    $docRepo->update($model);

                    $bIRepo = new PersonBankingInformationRepository();
                    $bIRepo->update($model);

                    $response["data"]["contactInfo"] = $model->contactInfo()->get()->toArray();
                    $response["data"]["documents"] = $model->documents()->get()->toArray();
                    $response["data"]["bankingInfo"] = $model->bankingInfo()->get()->toArray();
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
        $model = ExternalIndividual::query()->find($id);

        return $this->repository->delete($model);
    }

    /**
     * Restore record
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function restore($id)
    {
        $model = ExternalIndividual::withTrashed()->find($id);

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

            $query = ExternalIndividual::query()
                ->select("id", "given_name", "surname", "title_id")
                ->orderBy("given_name");

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
                $query->where(function ($query) use($searchText) {
                    $query->where("name_with_init", "LIKE", "%".$searchText."%")
                        ->orWhere("name_in_full", "LIKE", "%".$searchText."%")
                        ->orWhere("given_name", "LIKE", "%".$searchText."%")
                        ->orWhere("surname", "LIKE", "%".$searchText."%");
                });
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

    /**
     * Download external individual's document
     * @param $id
     * @param $documentId
     * @return mixed
     */
    public function downloadDocument($id, $documentId)
    {
        $model = ExternalIndividual::query()->find($id);

        if($model)
        {
            $docRepo = new PersonDocumentRepository();
            $docRepo->upload_dir = "public/external_individual_documents/";
            return $docRepo->triggerDownloadDocument($model, $documentId);
        }
        else
        {
            abort(404, "Requested record does not exist.");
        }
    }

    /**
     * Update status of the specified resource in storage.
     * @param int $id
     * @return mixed
     */
    public function changeStatus($id)
    {
        $model = ExternalIndividual::query()->find($id);
        return $this->repository->updateStatus($model, "status");
    }

    /**
     * @param $modelHash
     * @param $id
     * @return mixed
     */
    public function recordHistory($modelHash, $id)
    {
        $model = new ExternalIndividual();
        return $this->repository->recordHistory($model, $modelHash, $id);
    }
}
