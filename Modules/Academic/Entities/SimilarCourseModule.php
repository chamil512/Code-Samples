<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Modules\Admin\Observers\AdminActivityObserver;

class SimilarCourseModule extends Model
{
    use BaseModel;

    protected $fillable = ["module_id", "similar_module_id", "created_by", "updated_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function module()
    {
        return $this->belongsTo(CourseModule::class, "module_id", "module_id")->with(["course"]);
    }

    public function similarModule()
    {
        return $this->belongsTo(CourseModule::class, "similar_module_id", "module_id")->with(["course"]);
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
