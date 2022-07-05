<?php

namespace Modules\Academic\Cron;

use App\Services\Notify;
use App\Traits\CronHandler;
use Exception;

class NotifyCron
{
    use CronHandler;

    protected string $cronJobName = "Notification cron";

    public function __construct()
    {
        if ($this->triggerCronJob($this)) {

            try {
                Notify::processEmailQueue();

                $this->onCronSuccess($this);
            }
            catch (Exception $exception) {

                $this->onCronFail($this, $exception);
            }
        }
    }
}
