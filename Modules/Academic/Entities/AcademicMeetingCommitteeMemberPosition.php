<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class AcademicMeetingCommitteeMemberPosition extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = [
        "academic_meeting_committee_member_id",
        "academic_meeting_committee_position_id",
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

    public function committeeMember(): BelongsTo
    {
        return $this->belongsTo(AcademicMeetingCommitteeMember::class, "academic_meeting_committee_member_id");
    }

    public function committeePosition(): BelongsTo
    {
        return $this->belongsTo(AcademicMeetingCommitteePosition::class, "academic_meeting_committee_position_id");
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
