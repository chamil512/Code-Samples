<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class AcademicTimetableSubgroup extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["academic_timetable_information_id", "subgroup_id", "module_id", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $primaryKey = "academic_timetable_subgroup_id";

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["id"];

    protected array $observerConfig = [
        "activity" => false,
        "user" => true
    ];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function timetableInfo(): BelongsTo
    {
        return $this->belongsTo(AcademicTimetableInformation::class, "academic_timetable_information_id", "academic_timetable_information_id");
    }

    public function subgroup(): BelongsTo
    {
        return $this->belongsTo(Subgroup::class, "subgroup_id");
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, "module_id", "module_id");
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
