<?php

namespace Modules\Academic\Cron;

use App\Traits\CronHandler;
use Exception;
use Modules\Academic\Repositories\AcademicTimetableInformationRepository;

class DailyLectureReminderCron
{
    use CronHandler;

    protected string $cronJobName = "Daily Lecture Reminder";

    public function __construct()
    {
        $this->triggerReminder();
    }

    public function triggerReminder()
    {
        if ($this->triggerCronJob($this)) {

            try {
                $aTRepo = new AcademicTimetableInformationRepository();
                $aTRepo->sendLectureReminders();

                $this->onCronSuccess($this);
            } catch (Exception $exception) {

                $this->onCronFail($this, $exception);
            }
        }
    }
}
