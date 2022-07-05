<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Entities\Admin;
use Modules\Admin\Observers\AdminActivityObserver;

class AcademicMeetingDocument extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = [
        "academic_meeting_schedule_id",
        "submission_type",
        "academic_meeting_committee_id",
        "faculty_id",
        "dept_id",
        "purpose_type",
        "agenda_item_heading_id",
        "agenda_item_sub_heading_id",
        "file_name",
        "approval_status",
        "approval_by",
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

    public function committee()
    {
        return $this->belongsTo(AcademicMeetingCommittee::class, "academic_meeting_committee_id");
    }

    public function faculty()
    {
        return $this->belongsTo(Faculty::class, "faculty_id", "faculty_id");
    }

    public function department()
    {
        return $this->belongsTo(Department::class, "dept_id", "dept_id");
    }

    public function meetingSchedule()
    {
        return $this->belongsTo(AcademicMeetingSchedule::class, "academic_meeting_schedule_id");
    }

    public function agendaItemHeading()
    {
        return $this->belongsTo(AcademicMeetingAgendaItem::class, "agenda_item_heading_id");
    }

    public function agendaItemSubHeading()
    {
        return $this->belongsTo(AcademicMeetingAgendaItem::class, "agenda_item_sub_heading_id");
    }

    public function approver()
    {
        return $this->belongsTo(Admin::class, "approval_by", "admin_id");
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
