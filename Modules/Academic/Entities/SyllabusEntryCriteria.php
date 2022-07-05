<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class SyllabusEntryCriteria extends Model
{
    use SoftDeletes, BaseModel;

    protected $table = "syllabus_entry_criteria";

    protected $fillable = ["syllabus_id", "criteria_name", "description", "remarks", "criteria_status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["name"];

    public function getNameAttribute()
    {
        return $this->criteria_name;
    }

    public function syllabus(): BelongsTo
    {
        return $this->belongsTo(CourseSyllabus::class, "syllabus_id");
    }

    public function ecDocuments(): HasMany
    {
        return $this->hasMany(SyllabusEntryCriteriaDocument::class, "syllabus_entry_criteria_id");
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
