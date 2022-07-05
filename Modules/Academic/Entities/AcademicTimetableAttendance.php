<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicTimetableAttendance extends Model
{
    use SoftDeletes, BaseModel;

    protected $table = "slo_attendence_update";

    protected $fillable = ["lecture_id", "at_information_id", "start_time", "end_time", "sp_note", "created_by", "updated_by", "deleted_by"];

    protected $primaryKey = "attendence_update_id";

    protected $appends = ["id", "lecturer_attendance", "student_attendance"];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function getLecturerAttendanceAttribute()
    {
        return $this->lecturer === 1 ? "Updated" : "Pending";
    }

    public function getStudentAttendanceAttribute()
    {
        return $this->student === 1 ? "Updated" : "Pending";
    }
}
