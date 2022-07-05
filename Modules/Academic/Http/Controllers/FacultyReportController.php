<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Academic\Exports\FacultyCarderPositionAvailabilityExport;
use Modules\Academic\Exports\FacultyExport;
use Modules\Academic\Exports\FacultyLecturerExport;
use Modules\Academic\Repositories\FacultyRepository;

class FacultyReportController extends Controller
{
    private $repository;

    public function __construct()
    {
        $this->repository = new FacultyRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function dashboard()
    {
        return view('academic::faculty_report.index');
    }

    public function facultyInfo($type = "view")
    {
        $data = [];
        $data["type"] = $type;

        $pageTitle = "Faculty Information Report";
        $this->repository->setPageTitle($pageTitle);

        if ($type === "view") {

            $urls = [];
            $urls["exportUrl"] = URL::to("/academic/faculty_report/faculty_info/export");

            $this->repository->setPageUrls($urls);

            $formSubmitUrl = "/" . request()->path() . "/fetch";

            return view('academic::faculty_report.faculty_info.view', compact('formSubmitUrl'));
        }
        else if ($type === "fetch") {

            $export = new FacultyExport();
            $export->prepareData($data);

            $data = $export->getPreparedData();

            return view('academic::faculty_report.faculty_info.export', compact('data'));
        } else {

            $export = new FacultyExport();
            $export->prepareData($data);

            $export->view = "academic::faculty_report.faculty_info.export";

            return Excel::download($export, $pageTitle.".xlsx");
        }
    }

    public function carderPositionAvailability($type = "view")
    {
        $data = [];
        $data["type"] = $type;

        $pageTitle = "Faculty Carder Position Availability Report";
        $this->repository->setPageTitle($pageTitle);

        if ($type === "view") {

            $urls = [];
            $urls["exportUrl"] = URL::to("/academic/faculty_report/carder_position_availability/export");

            $this->repository->setPageUrls($urls);

            $formSubmitUrl = "/" . request()->path() . "/fetch";

            return view('academic::faculty_report.carder_position_availability.view', compact('formSubmitUrl'));
        }
        else if ($type === "fetch") {

            $export = new FacultyCarderPositionAvailabilityExport();
            $export->prepareData($data);

            $data = $export->getPreparedData();

            return view('academic::faculty_report.carder_position_availability.export', compact('data'));
        } else {

            $export = new FacultyCarderPositionAvailabilityExport();
            $export->prepareData($data);

            $export->view = "academic::faculty_report.carder_position_availability.export";

            return Excel::download($export, $pageTitle.".xlsx");
        }
    }

    public function facultyLecturer($type = "view")
    {
        $data = [];
        $data["type"] = $type;

        $pageTitle = "Faculty Lecturer Report";
        $this->repository->setPageTitle($pageTitle);

        if ($type === "view") {

            $urls = [];
            $urls["exportUrl"] = URL::to("/academic/faculty_report/faculty_lecturer/export");

            $this->repository->setPageUrls($urls);

            $formSubmitUrl = "/" . request()->path() . "/fetch";

            return view('academic::faculty_report.faculty_lecturer.view', compact('formSubmitUrl'));
        }
        else if ($type === "fetch") {

            $export = new FacultyLecturerExport();
            $export->prepareData($data);

            $data = $export->getPreparedData();

            return view('academic::faculty_report.faculty_lecturer.export', compact('data'));
        } else {

            $export = new FacultyLecturerExport();
            $export->prepareData($data);

            $export->view = "academic::faculty_report.faculty_lecturer.export";

            return Excel::download($export, $pageTitle.".xlsx");
        }
    }
}
