<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class SyllabusModule extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["syllabus_id", "module_id", "mandatory_status", "exempted_status", "module_order", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $primaryKey = "syllabus_module_id";

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

    public function syllabus(): BelongsTo
    {
        return $this->belongsTo(CourseSyllabus::class, "syllabus_id", "syllabus_id")->with(["course"]);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, "module_id", "module_id");
    }

    public function deliveryModes(): HasMany
    {
        return $this->hasMany(SyllabusModuleDeliveryMode::class, "syllabus_module_id", "syllabus_module_id")->with(["deliveryMode"]);
    }

    public function examTypes(): HasMany
    {
        return $this->hasMany(SyllabusModuleExamType::class, "syllabus_module_id", "syllabus_module_id")->with(["examType", "examCategory"]);
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
