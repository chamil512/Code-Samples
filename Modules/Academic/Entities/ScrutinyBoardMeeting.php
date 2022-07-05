<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class ScrutinyBoardMeeting extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = [
        "scrutiny_board_id",
        "type",
        "sb_meeting_appointment_id",
        "academic_calendar_id",
        "space_id",
        "meeting_name",
        "meeting_date",
        "start_time",
        "end_time",
        "status",
        "remarks",
        "approval_status",
        "created_by",
        "updated_by",
        "deleted_by",
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
        return $this->meeting_name;
    }

    public function scrutinyBoard(): BelongsTo
    {
        return $this->belongsTo(ScrutinyBoard::class, "scrutiny_board_id", "id");
    }

    public function academicCalendar(): BelongsTo
    {
        return $this->belongsTo(AcademicCalendar::class, "academic_calendar_id", "academic_calendar_id");
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class, "space_id", "id");
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ScrutinyBoardMeetingDocument::class, "scrutiny_board_meeting_id", "id");
    }

    public function modules(): HasMany
    {
        return $this->hasMany(ScrutinyBoardMeetingModule::class, "scrutiny_board_meeting_id", "id")->with(["module"]);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ScrutinyBoardMeetingParticipant::class, "scrutiny_board_meeting_id", "id");
    }

    public function scheduledMeeting(): HasOne
    {
        return $this->hasOne(ScrutinyBoardMeeting::class, "sb_meeting_appointment_id");
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(ScrutinyBoardMeeting::class, "sb_meeting_appointment_id");
    }

    public function newQuery(): Builder
    {
        $type = request()->route()->getAction()['type'] ?? "";

        if (!empty($type)) {

            if ($type === 2) {

                return parent::newQuery()->whereIn("type", [2, 3]);
            } else {


                return parent::newQuery()->where("type", $type);
            }
        }

        return parent::newQuery();
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
