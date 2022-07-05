<?php
namespace App\Traits;

use App\CronJob;
use App\Services\Notify;

trait CronHandler
{
    use UrlHelper;

    private ?CronJob $model = null;

    public function triggerCronJob($object): bool
    {
        $handlerName = $this->getClassName($object);
        $handlerHash = $this->generateClassNameHash($handlerName);

        //check if there is a running cron job
        $this->model = CronJob::query()
            ->where("cron_handler_hash", $handlerHash)
            ->where("status", 0)
            ->orderBy("id", "DESC")
            ->first();

        if (!$this->model) {

            $this->model = new CronJob();
            $this->model->cron_job_name = $this->cronJobName;
            $this->model->cron_handler_name = $handlerName;
            $this->model->cron_handler_hash = $handlerHash;
            $this->model->status = 0;
            $this->model->remarks = "Started the cron job.";

            if ($this->model->save()) {

                return true;
            }
        }

        return false;
    }

    public function onCronSuccess($object)
    {
        $this->stopCronJob($object, 1);
    }

    public function onCronFail($object, $exception)
    {
        $error = $exception->getMessage() . " in " . $exception->getFile() . " @ " . $exception->getLine();

        $message = "Following error occurred.";
        $message.= "<br><br>" . $error;
        $message.= "<br><br>" . $exception->getTraceAsString();

        Notify::sendToEmail($this->cronJobName . " got failed.", $message, ["chamilrupasinghe@gmail.com"]);

        $this->stopCronJob($object, 2, $error);
    }

    private function stopCronJob($object, $status, $remarks = "Successfully completed the cron job.")
    {
        $handlerName = $this->getClassName($object);
        $handlerHash = $this->generateClassNameHash($handlerName);

        if (!$this->model) {

            //check if there is a running cron job
            $this->model = CronJob::query()
                ->where("cron_handler_hash", $handlerHash)
                ->where("status", 0)
                ->orderBy("id", "DESC")
                ->first();
        }

        if ($this->model) {

            $this->model->status = $status;
            $this->model->remarks = $remarks;

            $this->model->save();
        }
    }
}
