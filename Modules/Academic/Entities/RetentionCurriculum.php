<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class RetentionCurriculum extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["title", "rc_category_id", "faculty_id", "dept_id", "course_id", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["name"];

    public function getNameAttribute()
    {
        return $this->title;
    }

    public function rcCategory()
    {
        return $this->belongsTo(RetentionCurriculumCategory::class, "rc_category_id");
    }

    public function faculty()
    {
        return $this->belongsTo(Faculty::class, "faculty_id", "faculty_id");
    }

    public function department()
    {
        return $this->belongsTo(Department::class, "dept_id", "dept_id");
    }

    public function course()
    {
        return $this->belongsTo(Course::class, "course_id", "course_id");
    }

    public function rcActivities()
    {
        return $this->hasMany(RetentionCurriculumActivity::class, "retention_curriculum_id");
    }

    public function rcDocuments()
    {
        return $this->hasMany(RetentionCurriculumDocument::class, "retention_curriculum_id");
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
