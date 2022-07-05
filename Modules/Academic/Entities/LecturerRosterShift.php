<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class LecturerRosterShift extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = [
        "lecturer_roster_id",
        "lecturer_id",
        "restrict_shift",
        "shift_date",
        "start_time",
        "end_time",
        "hours",
        "actual_start_time",
        "actual_end_time",
        "actual_hours",
        "attend_status",
        "created_by",
        "updated_by",
        "deleted_by"
    ];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function lecturerRoster()
    {
        return $this->belongsTo(LecturerRoster::class, "lecturer_roster_id");
    }

    public function lecturer()
    {
        return $this->belongsTo(Lecturer::class, "lecturer_id");
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
