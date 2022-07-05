<?php

namespace Modules\Academic\Cron;

use App\Services\Notify;
use App\Traits\CronHandler;
use Illuminate\Support\Facades\URL;
use Modules\Academic\Entities\LecturerPaymentPlan;
use Exception;

class LecturerPaymentPlanExpiryDateReminderCron
{
    use CronHandler;

    protected string $cronJobName = "Lecturer Payment Plan Expiry Date Reminder";

    public function __construct()
    {
        $this->triggerReminder();
    }

    public function triggerReminder()
    {
        if ($this->triggerCronJob($this)) {

            try {
                $periods = $this->_getPeriodsList();

                if (is_array($periods) && count($periods) > 0) {

                    foreach ($periods as $period) {

                        $date = date("Y-m-d", strtotime($period));
                        $this->sendReminder($date);
                    }
                }

                $this->onCronSuccess($this);
            } catch (Exception $exception) {

                $this->onCronFail($this, $exception);
            }
        }
    }

    private function sendReminder($expiryDate)
    {
        //get payment pending
        $query = LecturerPaymentPlan::with(["lecturer"])
            ->where("plan_status", 1)
            ->where("applicable_till", $expiryDate)
            ->whereHas("lecturer", function ($query) {

                $query->where("status", 1);
            });

        $records = $query->get()->toArray();

        if (is_array($records) && count($records) > 0) {

            foreach ($records as $record) {

                $url = URL::to("/academic/lecturer_payment_plan/view/" . $record["id"]);

                $title = $record["lecturer"]["name"] . "'s payment plan expiration reminder.";
                $description = view("academic::lecturer_payment_plan.expiry_reminders.reminder", compact('record', 'url'));

                Notify::send($title, $description, $url);
            }
        }
    }

    /**
     * @return array
     */
    private function _getPeriodsList(): array
    {
        $data = [];
        $data[] = "+0 day";
        $data[] = "+3 days";
        $data[] = "+1 week";
        $data[] = "+2 weeks";
        $data[] = "+1 month";
        $data[] = "+2 months";

        return $data;
    }
}
