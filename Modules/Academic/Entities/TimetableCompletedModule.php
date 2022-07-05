<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class TimetableCompletedModule extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["academic_timetable_id", "module_id", "status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["name"];

    public function getNameAttribute(): string
    {
        $name = "";
        if (isset($this->timetable->name)) {

            $name .= $this->timetable->name;
        }

        if (isset($this->module->name)) {

            $name .= " | " . $this->module->name;
        }

        return $name . " | Module Completion";
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(AcademicTimetable::class, "academic_timetable_id", "academic_timetable_id");
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, "module_id", "module_id");
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
