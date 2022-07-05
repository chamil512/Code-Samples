<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academic\Repositories\AcademicCalendarRepository;
use Modules\Admin\Observers\AdminActivityObserver;
use Modules\Settings\Entities\CalendarEvent;
use Modules\Settings\Repositories\CalendarEventRepository;

class AcademicCalendar extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["name", "faculty_id", "dept_id", "course_id", "academic_year_id", "semester_id", "batch_id",
        "complete_status", "ac_status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $primaryKey = "academic_calendar_id";

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["id", "base_name"];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function getBaseNameAttribute(): string
    {
        $name = "";
        if (isset($this->course)) {

            $name = $this->course->name;
        }

        if (isset($this->academicYear)) {

            $name .= " - " . $this->academicYear->name;
        }

        if (isset($this->semester)) {

            $name .= " - " . $this->semester->name;
        }

        if (isset($this->batch)) {

            $name .= " - " . $this->batch->name;
        }

        return $name;
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

    public function calendarEvents(): HasMany
    {
        $aCRepo = new AcademicCalendarRepository();
        $calEvRepo = new CalendarEventRepository();
        $modelTypeHash = $calEvRepo->generateModelTypeHash($aCRepo->modelType);

        return $this->hasMany(CalendarEvent::class, "model_id", "academic_calendar_id")->where("model_type", $modelTypeHash);
    }

    public function dates(): HasMany
    {
        return $this->hasMany(AcademicCalendarExtraDate::class, "academic_calendar_id", "academic_calendar_id");
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
