<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class LecturerWorkSchedule extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = [
        "title",
        "work_date",
        "lecturer_id",
        "lecturer_work_category_id",
        "lecturer_work_type_id",
        "delivery_mode_id",
        "academic_timetable_information_id",
        "start_time",
        "end_time",
        "work_count",
        "note",
        "created_by",
        "updated_by",
        "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
        'activity_date' => 'datetime:Y-m-d'
    ];

    protected $appends = ["name"];

    public function getNameAttribute()
    {
        return $this->title;
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, "lecturer_id");
    }

    public function workCategory(): BelongsTo
    {
        return $this->belongsTo(LecturerWorkCategory::class, "lecturer_work_category_id");
    }

    public function workType(): BelongsTo
    {
        return $this->belongsTo(LecturerWorkType::class, "lecturer_work_type_id");
    }

    public function deliveryMode(): BelongsTo
    {
        return $this->belongsTo(ModuleDeliveryMode::class, "delivery_mode_id", "delivery_mode_id");
    }

    public function wsDocuments(): HasMany
    {
        return $this->hasMany(LecturerWorkScheduleDocument::class, "lecturer_work_schedule_id");
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
