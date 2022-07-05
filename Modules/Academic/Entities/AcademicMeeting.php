<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class AcademicMeeting extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["academic_meeting_agenda_id", "academic_meeting_committee_id", "meeting_name", "meeting_status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["name"];

    public function getNameAttribute()
    {
        return $this->meeting_name;
    }

    public function agenda()
    {
        return $this->belongsTo(AcademicMeetingAgenda::class, "academic_meeting_agenda_id");
    }

    public function committee()
    {
        return $this->belongsTo(AcademicMeetingCommittee::class, "academic_meeting_committee_id");
    }

    public function meetingSchedules()
    {
        return $this->hasMany(AcademicMeetingSchedule::class, "academic_meeting_id");
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
