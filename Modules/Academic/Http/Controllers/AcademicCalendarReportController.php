<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Academic\Exports\AcademicCalendarFormatExport;
use Modules\Academic\Repositories\AcademicCalendarRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AcademicCalendarReportController extends Controller
{
    private AcademicCalendarRepository $repository;

    public function __construct()
    {
        $this->repository = new AcademicCalendarRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function dashboard()
    {
        return view('academic::academic_calendar_report.index');
    }

    /**
     * Display a listing of the resource.
     * @return Application|Factory|View|BinaryFileResponse
     */
    public function formatReport($type = "view")
    {
        $data = [];
        $data["type"] = $type;
        $relations = ["calendarEvents", "faculty", "department", "course", "batch", "academicYear", "semester"];

        if ($type === "view") {

            $urls = [];
            $urls["exportUrl"] = URL::to("/academic/academic_calendar_report/academic_calendar_format_report/export");

            $this->repository->setPageUrls($urls);

            $formSubmitUrl = "/" . request()->path() . "/fetch";

            $pageTitle = "Academic Calendar Format Report";
            $this->repository->setPageTitle($pageTitle);

            return view('academic::academic_calendar_report.academic_calendar_format_report.view', compact('formSubmitUrl'));
        }
        else if ($type === "fetch") {

            $data["records"] = $this->repository->getFilteredData($relations);

            $export = new AcademicCalendarFormatExport();
            $export->prepareData($data);

            $data = $export->getPreparedData();
            return view('academic::academic_calendar_report.academic_calendar_format_report.export', compact('data'));
        } else {

            $data["records"] = $this->repository->getFilteredData($relations);

            $export = new AcademicCalendarFormatExport();
            $export->prepareData($data);

            $export->view = "academic::academic_calendar_report.academic_calendar_format_report.export";

            return Excel::download($export, "Academic Calendar Format Report.xlsx");
        }
    }
}
