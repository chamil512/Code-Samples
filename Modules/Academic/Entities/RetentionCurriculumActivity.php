<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class RetentionCurriculumActivity extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["title", "activity_date", "rc_activity_type_id", "retention_curriculum_id", "created_by", "updated_by", "deleted_by"];

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

    public function rcActivityType()
    {
        return $this->belongsTo(RetentionCurriculumActivityType::class, "rc_activity_type_id");
    }

    public function rcCurriculum()
    {
        return $this->belongsTo(RetentionCurriculum::class, "retention_curriculum_id");
    }

    public function rcActivityDocuments()
    {
        return $this->hasMany(RetentionCurriculumActivityDocument::class, "rc_activity_id");
    }

    public function rcActivityLecturers()
    {
        return $this->hasMany(RetentionCurriculumActivityLecturer::class, "rc_activity_id")->with(["lecturer"]);
    }

    public function rcActivityMembers()
    {
        return $this->hasMany(RetentionCurriculumActivityMember::class, "rc_activity_id")->with(["externalIndividual"]);
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
