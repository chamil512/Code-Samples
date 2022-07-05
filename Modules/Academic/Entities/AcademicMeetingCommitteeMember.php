<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Entities\Admin;
use Modules\Admin\Observers\AdminActivityObserver;

class AcademicMeetingCommitteeMember extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["academic_meeting_committee_id", "admin_id", "appointed_date", "member_status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["name"];

    public function getNameAttribute()
    {
        $name = "";
        if (isset($this->admin)) {

            $name = $this->admin->name;
        }

        return $name;
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(AcademicMeetingCommittee::class, "academic_meeting_committee_id");
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, "admin_id", "admin_id");
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AcademicMeetingCommitteeMemberDocument::class, "academic_meeting_committee_member_id");
    }

    public function committeePositions(): HasMany
    {
        return $this->hasMany(AcademicMeetingCommitteeMemberPosition::class, "academic_meeting_committee_member_id");
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
