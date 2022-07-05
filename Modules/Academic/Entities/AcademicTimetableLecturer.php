<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class AcademicTimetableLecturer extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["academic_timetable_information_id", "lecturer_id", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $primaryKey = "academic_timetable_lecturer_id";

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["id"];

    protected $observerConfig = [
        "activity" => false,
        "user" => true
    ];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function timetableInfo()
    {
        return $this->belongsTo(AcademicTimetableInformation::class, "academic_timetable_information_id", "academic_timetable_information_id");
    }

    public function lecturer()
    {
        return $this->belongsTo(Lecturer::class, "lecturer_id");
    }

    public function attendance()
    {
        return $this->hasOneThrough(AcademicTimetableLecturer::class, AcademicTimetableLecturerAttendance::class,
            "lecture_id", "at_information_id",
            "lecturer_id", "academic_timetable_information_id");
    }

    public static function boot()
    {
        parent::boot();

        //Use this code block to track activities regarding this model
        //Use this code block in every model you need to record
        //This will record created_by, updated_by, deleted_by admins too, if you have set those fields in your model
        self::observe(AdminActivityObserver::class);
    }
}
