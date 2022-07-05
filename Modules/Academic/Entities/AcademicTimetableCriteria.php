<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class AcademicTimetableCriteria extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["academic_timetable_id", "delivery_mode_id", "mode_type", "timetable_criteria", "created_by", "updated_by", "deleted_by"];

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

        if (isset($this->delivery_mode->name)) {

            $name .= " | " . $this->delivery_mode->name;
        }

        return $name . " | Criteria";
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(AcademicTimetable::class, "academic_timetable_id", "academic_timetable_id");
    }

    public function deliveryMode(): BelongsTo
    {
        return $this->belongsTo(ModuleDeliveryMode::class, "delivery_mode_id", "delivery_mode_id");
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
