<?php

namespace Modules\Academic\Cron;

use Modules\Academic\Entities\AcademicTimetable;
use Modules\Academic\Repositories\AcademicTimetableAutoGenRepository;
use Error;

class TimetableCron
{
    public function __construct()
    {
        $this->autoGenerateTimetables();
    }

    public function autoGenerateTimetables()
    {
        //read only pending cron jobs
        $timetables = AcademicTimetable::query()->where("auto_gen_status", "0")->get();

        if($timetables)
        {
            foreach ($timetables as $timetable)
            {
                $autoGenRepo = new AcademicTimetableAutoGenRepository();
                try {
                    $autoGenRepo->autoGenerateTimetable($timetable);
                }
                catch (Error $error)
                {
                    $autoGenRepo->updateStatus($timetable, 4, $error->getMessage());
                }
            }
        }
    }
}
