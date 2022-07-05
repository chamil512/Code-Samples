<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class SyllabusGradingImplication extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["syllabus_id", "grading_implication_id", "min_marks", "max_marks", "points", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function syllabus()
    {
        return $this->belongsTo(CourseSyllabus::class, "syllabus_id", "syllabus_id");
    }

    public function gradingImplication()
    {
        return $this->belongsTo(GradingImplication::class, "grading_implication_id");
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
