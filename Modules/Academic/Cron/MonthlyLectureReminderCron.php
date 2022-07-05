<?php

namespace Modules\Academic\Cron;

use App\Traits\CronHandler;
use Exception;
use Modules\Academic\Repositories\AcademicTimetableInformationRepository;

class MonthlyLectureReminderCron
{
    use CronHandler;

    protected string $cronJobName = "Monthly Lecture Reminder";

    public function __construct()
    {
        $this->triggerReminder();
    }

    public function triggerReminder()
    {
        if ($this->triggerCronJob($this)) {

            try {
                $aTRepo = new AcademicTimetableInformationRepository();
                $aTRepo->sendMonthlyLectureSchedule();

                $this->onCronSuccess($this);
            } catch (Exception $exception) {

                $this->onCronFail($this, $exception);
            }
        }
    }
}
