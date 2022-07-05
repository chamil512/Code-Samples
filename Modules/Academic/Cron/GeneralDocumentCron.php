<?php

namespace Modules\Academic\Cron;

use App\Traits\CronHandler;
use Exception;
use Modules\General\Repositories\GeneralVehicleRepository;
use Modules\General\Repositories\GeneralMaintenanceServiceRepository;
use Modules\General\Repositories\GeneralMaintenanceReplaceRepository;

class GeneralDocumentCron
{
    use CronHandler;

    protected string $cronJobName = "Daily General Reminders";

    public function __construct()
    {
        $this->triggerReminder();
    }

    public function triggerReminder()
    {
        if ($this->triggerCronJob($this)) {

            try {
                $aTRepo = GeneralVehicleRepository::createDocuments();
                $aTRepo = GeneralMaintenanceServiceRepository::createDocuments();
                $aTRepo = GeneralMaintenanceReplaceRepository::sendMissingItemReminder();

                $this->onCronSuccess($this);
            } catch (Exception $exception) {

                $this->onCronFail($this, $exception);
            }
        }
    }
}
