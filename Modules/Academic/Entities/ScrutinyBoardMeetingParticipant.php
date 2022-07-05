<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Entities\Admin;
use Modules\Admin\Observers\AdminActivityObserver;

class ScrutinyBoardMeetingParticipant extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = [
        "scrutiny_board_meeting_id",
        "admin_id",
        "rsvp_status",
        "rsvp_excuse",
        "participate_status",
        "excuse",
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

    protected $appends = ["name"];

    public function getNameAttribute()
    {
        $name = "";
        if (isset($this->participant)) {

            $name = $this->participant->name;
        }

        return $name;
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ScrutinyBoardMeeting::class, "scrutiny_board_meeting_id");
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, "admin_id", "admin_id");
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Admin::class, "admin_id", "admin_id");
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
