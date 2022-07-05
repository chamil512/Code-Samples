<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExamCalendar extends Model
{
    use SoftDeletes, BaseModel;

    protected $table = 'exam_calendar';

    protected $primaryKey = "exam_calendar_id";

    protected $appends = ["id"];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function timetableInfo(): BelongsTo
    {
        return $this->belongsTo(AcademicTimetableInformation::class, "academic_timetable_information_id", "academic_timetable_information_id");
    }
}
