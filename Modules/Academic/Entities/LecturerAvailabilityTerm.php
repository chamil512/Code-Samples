<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class LecturerAvailabilityTerm extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["lecturer_id", "date_from", "date_till"];

    protected $with = [];

    protected $appends = ["name"];

    public function getNameAttribute(): string
    {
        $lecturer = "";

        if (isset($this->lecturer)) {

            $lecturer = $this->lecturer->name . " | ";
        }

        return $lecturer . "[" . $this->date_from . " - " . $this->date_till . "] availability term.";
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, "lecturer_id");
    }

    public function availabilityHours(): HasMany
    {
        return $this->hasMany(LecturerAvailabilityHour::class, "lecturer_availability_term_id");
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
