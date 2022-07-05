<?php

namespace Modules\Academic\Entities;

use Illuminate\Database\Eloquent\Model;

class LecturerCourseModule extends Model
{
    protected $fillable = ["lecturer_course_id", "lecturer_id", "module_id"];

    protected $with = [];

    protected $primaryKey = "lecturer_module_id";

    /*protected $observerConfig = [
        "activity" => false,
        "user" => true
    ];*/

    protected $appends = ["id"];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function lecturer()
    {
        return $this->belongsTo(Lecturer::class, "lecturer_id");
    }

    public function courseModule()
    {
        return $this->belongsTo(CourseModule::class, "module_id", "module_id");
    }
}
