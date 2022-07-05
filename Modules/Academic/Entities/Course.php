<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class Course extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["dept_id", "slqf_id", "course_category_id", "course_type_id", "course_code", "course_name", "transcript_name", "abbreviation", "course_du_years", "course_du_months", "course_du_dates", "supplementary_status", "course_du_years_ex", "course_du_months_ex", "course_du_dates_ex", "course_status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $primaryKey = "course_id";

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["id", "name"];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function getNameAttribute(): string
    {
        return $this->course_code." - ".$this->course_name;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, "dept_id", "dept_id");
    }

    public function courseCategory(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, "course_category_id", "course_category_id");
    }

    public function courseType(): BelongsTo
    {
        return $this->belongsTo(CourseType::class, "course_type_id");
    }

    public function slqf(): BelongsTo
    {
        return $this->belongsTo(SlqfStructure::class, "slqf_id", "slqf_id");
    }

    public function studentRegCourses(): HasMany
    {
        return $this->hasMany(StudentRegCourse::class, "course_id", "course_id");
    }

    public function lecturers(): BelongsToMany
    {
        return $this->belongsToMany(Lecturer::class, "lecturer_courses", "course_id", "lecturer_id", "course_id", "id");
    }

    public function courseLecturers(): HasMany
    {
        return $this->hasMany(LecturerCourse::class, "course_id", "course_id");
    }

    public function syllabuses(): HasMany
    {
        return $this->hasMany( CourseSyllabus::class, "course_id", "course_id");
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CourseDocument::class, "course_id");
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
