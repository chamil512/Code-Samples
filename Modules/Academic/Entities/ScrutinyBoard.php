<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrutinyBoard extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["type", "master_scrutiny_board_id", "based_scrutiny_board_id", "board_name", "course_id",
        "syllabus_id", "academic_calendar_id", "sb_status", "approval_status", "sb_approval_status",
        "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["name"];

    public function getNameAttribute()
    {
        return $this->board_name;
    }

    public function academicCalendar(): BelongsTo
    {
        return $this->belongsTo(AcademicCalendar::class, "academic_calendar_id", "academic_calendar_id");
    }

    public function syllabus(): BelongsTo
    {
        return $this->belongsTo(CourseSyllabus::class, "syllabus_id", "syllabus_id");
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, "course_id", "course_id");
    }

    public function modules(): hasMany
    {
        return $this->hasMany(ScrutinyBoardModule::class, "scrutiny_board_id");
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(ScrutinyBoard::class, "master_scrutiny_board_id");
    }

    public function academic(): HasOne
    {
        return $this->hasOne(ScrutinyBoard::class, "master_scrutiny_board_id");
    }

    public function base(): BelongsTo
    {
        return $this->belongsTo(ScrutinyBoard::class, "based_scrutiny_board_id");
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
