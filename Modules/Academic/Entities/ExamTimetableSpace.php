<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class ExamTimetableSpace extends Model
{
    use SoftDeletes, BaseModel;

    protected $with = [];

    protected $primaryKey = "exam_timetable_space_id";

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

    public function timetableInfo()
    {
        return $this->belongsTo(AcademicTimetableInformation::class, "academic_timetable_information_id", "academic_timetable_information_id");
    }

    public function space()
    {
        return $this->belongsTo(Space::class, "space_id");
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
