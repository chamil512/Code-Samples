<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class AcademicMeetingSchedule extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["academic_meeting_id", "meeting_no", "meeting_date", "meeting_time", "space_id",
        "invitation", "invite_status", "doc_submit_deadline", "remarks", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
        'doc_submit_deadline' => 'datetime:Y-m-d H:i:s',
    ];

    protected $appends = ["name"];

    public function getNameAttribute()
    {
        $academicMeeting = "";
        if (isset($this->academicMeeting->name) && $this->academicMeeting->name != "") {
            $academicMeeting = $this->academicMeeting->name;
        }

        return $academicMeeting . " | " . $this->meeting_no;
    }

    public function academicMeeting()
    {
        return $this->belongsTo(AcademicMeeting::class, "academic_meeting_id");
    }

    public function space()
    {
        return $this->belongsTo(Space::class, "space_id");
    }

    public function meetingParticipants()
    {
        return $this->hasMany(AcademicMeetingParticipant::class, "academic_meeting_schedule_id");
    }

    public function documentSubmissions()
    {
        return $this->hasMany(AcademicMeetingDocument::class, "academic_meeting_schedule_id");
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
