<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Admin\Observers\AdminActivityObserver;

class SyllabusModuleDeliveryMode extends Model
{
    use BaseModel;

    protected $fillable = ["syllabus_module_id", "syllabus_id", "module_id", "delivery_mode_id", "hours", "credits", "created_by", "updated_by"];

    protected $with = [];

    protected $primaryKey = "smdm_id";

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected $appends = ["id"];

    protected $observerConfig = [
        "activity" => false,
        "user" => true
    ];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function syllabus(): BelongsTo
    {
        return $this->belongsTo(CourseSyllabus::class, "syllabus_id", "syllabus_id");
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, "module_id", "module_id");
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
