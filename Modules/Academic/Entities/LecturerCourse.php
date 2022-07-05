<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class LecturerCourse extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["lecturer_id", "course_id", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $primaryKey = "lecturer_course_id";

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["id"];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function lecturer()
    {
        return $this->belongsTo(Lecturer::class, "lecturer_id");
    }

    public function course()
    {
        return $this->belongsTo(Course::class, "course_id", "course_id");
    }

    public function modules()
    {
        return $this->hasMany(LecturerCourseModule::class, "lecturer_course_id", "lecturer_course_id")->with(["courseModule"]);
    }

    public function lecturers()
    {
        return $this->belongsToMany(Lecturer::class, "lecturer_courses", "course_id", "lecturer_id", "course_id", "id");
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
