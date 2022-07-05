<?php

namespace Modules\Academic\Entities;

use App\Helpers\Helper;
use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class SyllabusLessonTopic extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["syllabus_lesson_plan_id", "module_id", "delivery_mode_id", "lecturer_id", "name", "hours", "lesson_order", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["name_with_hours"];

    public function getNameWithHoursAttribute()
    {
        $name = "";
        if ($this->name) {

            $name = $this->name;
        }

        if (isset($this->hours)) {

            $hours = Helper::convertHoursToMinutes($this->hours);
            $hours = Helper::convertMinutesToHumanTime($hours);

            $name = $name . " [" . $hours . "]";
        }

        return $name;
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SyllabusLessonPlan::class, "syllabus_lesson_plan_id");
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, "module_id");
    }

    public function deliveryMode(): BelongsTo
    {
        return $this->belongsTo(ModuleDeliveryMode::class, "delivery_mode_id", "delivery_mode_id");
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, "lecturer_id");
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
