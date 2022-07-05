<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Modules\Admin\Observers\AdminActivityObserver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubgroupDeactivateTemporary extends Model
{
    use BaseModel;

    protected $fillable = ["academic_calendar_id", "status"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function academicCalendar(): BelongsTo
    {
        return $this->belongsTo(AcademicCalendar::class, "academic_calendar_id", "academic_calendar_id");
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
