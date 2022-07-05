<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class Lecturer extends Person
{
    use SoftDeletes, BaseModel;

    protected $table = "people";

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["name", "lecturer_id"];

    public function getLecturerIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public string $personType = "lecturer";
    public array $personTypes = ["lecturer"];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, "faculty_id", "faculty_id");
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, "dept_id", "dept_id");
    }

    public function availabilityTerms(): HasMany
    {
        return $this->hasMany(LecturerAvailabilityTerm::class, "lecturer_id");
    }

    public function availabilityHours(): HasMany
    {
        return $this->hasMany(LecturerAvailabilityHour::class, "lecturer_id");
    }

    public function courses(): HasMany
    {
        return $this->hasMany(LecturerCourse::class, "lecturer_id");
    }

    public function modules(): HasMany
    {
        return $this->hasMany(LecturerCourseModule::class, "lecturer_id");
    }

    public function workSchedules(): HasMany
    {
        return $this->hasMany(LecturerWorkSchedule::class, "lecturer_id");
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
