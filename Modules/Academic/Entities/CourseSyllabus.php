<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class CourseSyllabus extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["based_syllabus_id", "type", "course_id", "syllabus_name", "default_status", "syllabus_status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $primaryKey = "syllabus_id";

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["id", "name"];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function getNameAttribute()
    {
        return $this->syllabus_name;
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, "course_id", "course_id");
    }

    public function slqfVersion(): BelongsTo
    {
        return $this->belongsTo(SlqfVersion::class, "slqf_version_id", "slqf_version_id");
    }

    public function syllabusModules(): HasMany
    {
        return $this->hasMany(SyllabusModule::class, "syllabus_id", "syllabus_id")->with(["module", "deliveryModes"]);
    }

    public function syllabusEntryCriteria(): HasMany
    {
        return $this->hasMany(SyllabusEntryCriteria::class, "syllabus_id", "syllabus_id");
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, "syllabus_id", "syllabus_id");
    }

    public function based(): BelongsTo
    {
        return $this->belongsTo(CourseSyllabus::class, "based_syllabus_id", "syllabus_id");
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
