<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class AcademicTimetable extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["academic_calendar_id", "master_timetable_id", "timetable_name", "faculty_id", "dept_id",
        "course_id", "semester_id", "academic_year_id", "batch_id", "syllabus_id", "syllabus_lesson_plan_id", "type",
        "start_date", "end_date", "auto_gen_status", "status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $primaryKey = "academic_timetable_id";

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
        'week_days' => "array"
    ];

    protected $appends = ["id", "name"];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function getNameAttribute()
    {
        return $this->timetable_name;
    }

    public function academicCalendar(): BelongsTo
    {
        return $this->belongsTo(AcademicCalendar::class, "academic_calendar_id", "academic_calendar_id");
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, "faculty_id", "faculty_id");
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, "dept_id", "dept_id");
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, "course_id", "course_id");
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, "academic_year_id", "academic_year_id");
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(AcademicSemester::class, "semester_id", "semester_id");
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, "batch_id", "batch_id");
    }

    public function syllabus(): BelongsTo
    {
        return $this->belongsTo(CourseSyllabus::class, "syllabus_id", "syllabus_id");
    }

    public function lessonPlan(): BelongsTo
    {
        return $this->belongsTo(SyllabusLessonPlan::class, "syllabus_lesson_plan_id");
    }

    public function information(): HasMany
    {
        return $this->hasMany(AcademicTimetableInformation::class, "academic_timetable_id", "academic_timetable_id");
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(AcademicTimetable::class, "master_timetable_id", "academic_timetable_id");
    }

    public function academic(): HasOne
    {
        return $this->hasOne(AcademicTimetable::class, "master_timetable_id", "academic_timetable_id");
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(AcademicTimetableCriteria::class, "academic_timetable_id", "academic_timetable_id");
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
