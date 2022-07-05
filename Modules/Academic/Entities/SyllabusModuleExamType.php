<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class SyllabusModuleExamType extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["syllabus_module_id", "exam_type_id", "exam_category_id", "marks_percentage", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $primaryKey = "smet_id";

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["id"];

    /*protected $observerConfig = [
        "activity" => false,
        "user" => true
    ];*/

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function syllabusModule(): BelongsTo
    {
        return $this->belongsTo(SyllabusModule::class, "syllabus_module_id", "syllabus_module_id");
    }

    public function deliveryMode(): BelongsTo
    {
        return $this->belongsTo(ModuleDeliveryMode::class, "delivery_mode_id", "delivery_mode_id");
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class, "exam_type_id", "exam_type_id");
    }

    public function examCategory(): BelongsTo
    {
        return $this->belongsTo(ExamCategory::class, "exam_category_id", "exam_category_id");
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
