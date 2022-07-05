<?php

namespace Modules\Academic\Http\Controllers;

use App\Helpers\Helper;
use Illuminate\Contracts\View\Factory;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Academic\Exports\LecturerExport;
use Modules\Academic\Exports\LecturerPaymentSummaryExport;
use Modules\Academic\Exports\WorkScheduleSubmissionFailedExport;
use Modules\Academic\Exports\WorkScheduleSubmissionFailedDatesExport;
use Modules\Academic\Exports\WorkScheduleSummaryExport;
use Modules\Academic\Repositories\LecturerPaymentPlanRepository;
use Modules\Academic\Repositories\LecturerPaymentRepository;
use Modules\Academic\Repositories\LecturerWorkScheduleRepository;

class LecturerReportController extends Controller
{
    private LecturerWorkScheduleRepository $repository;

    public function __construct()
    {
        $this->repository = new LecturerWorkScheduleRepository();
    }

    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function dashboard()
    {
        return view('academic::lecturer_report.index');
    }

    public function lecturerInfo($type = "view")
    {
        $data = [];
        $data["type"] = $type;

        $pageTitle = "Lecturer Information Report";
        $this->repository->setPageTitle($pageTitle);

        if ($type === "view") {

            $urls = [];
            $urls["exportUrl"] = URL::to("/academic/lecturer_report/lecturer_info/export");

            $this->repository->setPageUrls($urls);

            $formSubmitUrl = "/" . request()->path() . "/fetch";

            return view('academic::lecturer_report.lecturer_info.view', compact('formSubmitUrl'));
        } elseif ($type === "fetch") {

            $export = new LecturerExport();
            $export->prepareData($data);

            $data = $export->getPreparedData();

            return view('academic::lecturer_report.lecturer_info.export', compact('data'));
        } else {

            $export = new LecturerExport();
            $export->prepareData($data);

            $export->view = "academic::lecturer_report.lecturer_info.export";

            return Excel::download($export, $pageTitle . ".xlsx");
        }
    }

    public function workScheduleSummary($type = "view")
    {
        $data = [];
        $data["type"] = $type;

        $relations = [
            "lecturer:title_id,id,name_with_init,given_name,surname,faculty_id,dept_id,staff_type",
            "lecturer.faculty",
            "lecturer.department",
        ];

        $pageTitle = "Work Schedule Summary Report";
        $this->repository->setPageTitle($pageTitle);

        if ($type === "view") {

            $urls = [];
            $urls["exportUrl"] = URL::to("/academic/lecturer_report/work_schedule_summary/export");

            $this->repository->setPageUrls($urls);

            $formSubmitUrl = "/" . request()->path() . "/fetch";

            return view('academic::lecturer_report.work_schedule_summary.view', compact('formSubmitUrl'));
        } elseif ($type === "fetch") {

            $data["records"] = $this->repository->getFilteredData($relations);

            $export = new WorkScheduleSummaryExport();
            $export->prepareData($data);

            $data = $export->getPreparedData();

            return view('academic::lecturer_report.work_schedule_summary.export', compact('data'));
        } else {

            $data["records"] = $this->repository->getFilteredData($relations);

            $export = new WorkScheduleSummaryExport();
            $export->prepareData($data);

            $export->view = "academic::lecturer_report.work_schedule_summary.export";

            return Excel::download($export, "Work Schedule Summary Report.xlsx");
        }
    }

    public function lecturerPayments($type = "view")
    {
        $lPPRepo = new LecturerPaymentPlanRepository();
        $lPRepo = new LecturerPaymentRepository();

        $paymentTypes = $lPPRepo->paymentTypes;
        if (count($paymentTypes) === 4) {

            array_pop($paymentTypes);
        }

        $data = [];
        $data["type"] = $type;
        $data["paymentTypes"] = $paymentTypes;
        $relations = [
            "lecturer:title_id,id,name_with_init,given_name,surname,faculty_id,dept_id",
            "lecturer.faculty",
            "lecturer.department",
            "course",
            "course.department",
            "course.department.faculty",
            "batch",
            "module",
            "paymentMethod",
            "paymentPlan",
        ];

        $pageTitle = "Lecturer Payment Report";
        $this->repository->setPageTitle($pageTitle);

        if ($type === "view") {

            $urls = [];
            $urls["exportUrl"] = URL::to("/academic/lecturer_report/lecturer_payment_report/export");

            $this->repository->setPageUrls($urls);

            $formSubmitUrl = "/" . request()->path() . "/fetch";

            $approvalStatuses = $lPRepo->approvalStatuses;
            $paidStatusOptions = $lPRepo->paidStatusOptions;

            return view('academic::lecturer_report.lecturer_payment_report.view',
                compact('formSubmitUrl', 'approvalStatuses', 'paymentTypes', 'paidStatusOptions'));
        } elseif ($type === "fetch") {

            $data["records"] = $lPRepo->getFilteredData($relations);

            $export = new LecturerPaymentSummaryExport();
            $export->prepareData($data);

            $data = $export->getPreparedData();

            return view('academic::lecturer_report.lecturer_payment_report.export', compact('data'));
        } else {

            $data["records"] = $lPRepo->getFilteredData($relations);

            $export = new LecturerPaymentSummaryExport();
            $export->prepareData($data);

            $export->view = "academic::lecturer_report.lecturer_payment_report.export";

            return Excel::download($export, "Lecturer Payment Report.xlsx");
        }
    }

    public function workScheduleSubmissionFailed($type = "view")
    {
        $data = [];
        $data["type"] = $type;

        $pageTitle = "Work Schedule Submission Failed Report";
        $this->repository->setPageTitle($pageTitle);

        if ($type === "view") {

            $urls = [];
            $urls["exportUrl"] = URL::to("/academic/lecturer_report/work_schedule_submission_failed/export");

            $this->repository->setPageUrls($urls);

            $formSubmitUrl = "/" . request()->path() . "/fetch";

            return view('academic::lecturer_report.work_schedule_submission_failed.view', compact('formSubmitUrl'));
        } elseif ($type === "fetch") {

            $havingIds = $this->repository->getFilteredData([] , true);
            $data["records"] = $this->repository->getFilteredDataSF();

            $export = new WorkScheduleSubmissionFailedExport();
            $export->havingIds = $havingIds;
            $export->prepareData($data);

            $data = $export->getPreparedData();

            return view('academic::lecturer_report.work_schedule_submission_failed.export', compact('data'));
        } else {

            $havingIds = $this->repository->getFilteredData([], true);
            $data["records"] = $this->repository->getFilteredDataSF();

            $export = new WorkScheduleSubmissionFailedExport();
            $export->havingIds = $havingIds;
            $export->prepareData($data);

            $export->view = "academic::lecturer_report.work_schedule_submission_failed.export";

            return Excel::download($export, "Work Schedule Submission Failed Report.xlsx");
        }
    }

    public function workScheduleSubmissionFailedDates($type = "view")
    {
        $request = request();
        $dateFrom = $request->post("date_from");
        $dateTill = $request->post("date_till");

        $data = [];
        $data["type"] = $type;

        $pageTitle = "Work Schedule Submission Failed Dates Report";
        $this->repository->setPageTitle($pageTitle);

        if ($type === "view") {

            $urls = [];
            $urls["exportUrl"] = URL::to("/academic/lecturer_report/work_schedule_submission_failed_dates/export");

            $this->repository->setPageUrls($urls);

            $formSubmitUrl = "/" . request()->path() . "/fetch";

            return view('academic::lecturer_report.work_schedule_submission_failed_dates.view', compact('formSubmitUrl'));
        } elseif ($type === "fetch") {

            if (!$dateFrom) {

                $dateFrom = date("Y-m-d", time());
            }

            if (!$dateTill) {

                $dateTill = date("Y-m-d", time());
            }

            $schedules = $this->repository->getFilteredData([]);
            $data["records"] = $this->repository->getFilteredDataSF();
            $data["dates"] = Helper::getDatesBetweenTwoDates($dateFrom, $dateTill);

            $export = new WorkScheduleSubmissionFailedDatesExport();
            $export->schedules = $schedules;
            $export->prepareData($data);

            $data = $export->getPreparedData();

            return view('academic::lecturer_report.work_schedule_submission_failed_dates.export', compact('data'));
        } else {

            if (!$dateFrom) {

                $dateFrom = date("Y-m-d", time());
            }

            if (!$dateTill) {

                $dateTill = date("Y-m-d", time());
            }

            $schedules = $this->repository->getFilteredData([]);
            $data["records"] = $this->repository->getFilteredDataSF();
            $data["dates"] = Helper::getDatesBetweenTwoDates($dateFrom, $dateTill);

            $export = new WorkScheduleSubmissionFailedDatesExport();
            $export->schedules = $schedules;
            $export->prepareData($data);

            $export->view = "academic::lecturer_report.work_schedule_submission_failed_dates.export";

            return Excel::download($export, "Work Schedule Submission Failed Dates Report.xlsx");
        }
    }
}
