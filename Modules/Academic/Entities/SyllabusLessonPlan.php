<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class SyllabusLessonPlan extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["syllabus_id", "batch_id", "name", "syllabus_status", "approval_status", "remarks", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function syllabus(): BelongsTo
    {
        return $this->belongsTo(CourseSyllabus::class, "syllabus_id", "syllabus_id");
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, "batch_id", "batch_id");
    }

    public function topics(): HasMany
    {
        return $this->hasMany(SyllabusLessonTopic::class, "syllabus_lesson_plan_id");
    }

    public function moduleTopics(): hasManyThrough
    {
        return $this->hasManyThrough(CourseModule::class, SyllabusLessonTopic::class,
            "id", "id", "syllabus_lesson_plan_id", "module_id");
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
