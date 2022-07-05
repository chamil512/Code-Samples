<?php

namespace Modules\Academic\Cron;

use App\Traits\CronHandler;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Entities\LecturerPaymentPlan;
use Exception;
use Modules\Academic\Repositories\LecturerPaymentRepository;

class LecturerFixedPaymentCron
{
    use CronHandler;

    protected string $cronJobName = "Lecturer's fixed payment reminder";

    public function __construct()
    {
        $this->processPayment();
    }

    public function processPayment()
    {
        if ($this->triggerCronJob($this)) {

            try {
                //get last month start date
                $month = date('Y-m', strtotime('last month'));
                $startDate = date('Y-m-01', strtotime('last month'));
                $endDate = date('Y-m-t', strtotime('last month'));

                //get payment pending
                $query = LecturerPaymentPlan::query()
                    ->select("id", "lecturer_id", "payment_type", "fixed_amount")
                    ->where("payment_type", 3)
                    ->where("plan_status", 1)
                    ->where("approval_status", 1)
                    ->whereHas("lecturer", function ($query){

                        $query->where("status", 1);
                    })
                    ->whereNotExists(function ($query) use($month) {

                        $query->from("lecturer_payments")
                            ->where("lecturer_id", "lecturer_payment_plans.lecturer_id")
                            ->where("payment_month", $month)
                            ->where(function ($query) {

                                $query->where("paid_status", 1)
                                    ->orWhereNotIn("approval_status", [1, 2]);
                            });
                    })
                    ->where(function ($query) use($startDate, $endDate){

                        $query->where(function ($query) use($startDate, $endDate){

                            $query->where(DB::raw("applicable_from"), "<=", DB::raw("'".$startDate."'"))
                                ->where(DB::raw("'".$startDate."'"), "<", DB::raw("applicable_till"));
                        })
                            ->orWhere(function ($query) use($startDate, $endDate){

                                $query->where(DB::raw("applicable_from"), "<", DB::raw("'".$endDate."'"))
                                    ->where(DB::raw("'".$endDate."'"), "<=", DB::raw("applicable_till"));
                            })
                            ->orWhere(function ($query) use($startDate, $endDate){

                                $query->where(DB::raw("'".$startDate."'"), "<=", DB::raw("applicable_from"))
                                    ->where(DB::raw("applicable_from"), "<", DB::raw("'".$endDate."'"));
                            })
                            ->orWhere(function ($query) use($startDate, $endDate){

                                $query->where(DB::raw("'".$startDate."'"), "<", DB::raw("applicable_till"))
                                    ->where(DB::raw("applicable_till"), "<=", DB::raw("'".$endDate."'"));
                            });
                    });

                $results = $query->get()->toArray();

                if (is_array($results) && count($results) > 0) {

                    foreach ($results as $result) {
                        $lPRepo = new LecturerPaymentRepository();
                        $lPRepo->sendFixedPaymentForApproval($result, $startDate, $endDate, $month);
                    }
                }

                $this->onCronSuccess($this);
            }
            catch (Exception $exception) {

                $this->onCronFail($this, $exception);
            }
        }
    }
}
