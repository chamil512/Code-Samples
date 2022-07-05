<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicTimetableAttendanceLecturer extends Model
{
    use SoftDeletes, BaseModel;

    protected $table = "slo_attendence_lectures";
}
