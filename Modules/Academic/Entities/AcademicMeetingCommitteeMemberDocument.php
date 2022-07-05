<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class AcademicMeetingCommitteeMemberDocument extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["academic_meeting_committee_member_id", "document_name", "appointed_from",
        "appointed_till", "file_name", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $appends = ["name"];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function getNameAttribute()
    {
        return $this->document_name;
    }

    public function committeeMember()
    {
        return $this->belongsTo(AcademicMeetingCommitteeMember::class, "academic_meeting_committee_member_id");
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
