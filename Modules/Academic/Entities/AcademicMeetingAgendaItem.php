<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class AcademicMeetingAgendaItem extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["academic_meeting_agenda_id", "parent_item_id", "item_number", "item_heading", "item_status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["name"];

    protected $observerConfig = [
        "user" => true,
        "activity" => false,
    ];

    public function getNameAttribute()
    {
        return $this->item_number . " " . $this->item_heading;
    }

    public function agenda()
    {
        return $this->belongsTo(AcademicMeetingAgenda::class, "academic_meeting_agenda_id");
    }

    public function documentSubmissionHeadings()
    {
        return $this->belongsTo(AcademicMeetingDocument::class, "agenda_item_heading_id");
    }

    public function documentSubmissionSubHeadings()
    {
        return $this->belongsTo(AcademicMeetingAgenda::class, "agenda_item_sub_heading_id");
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
