<?php

namespace Modules\Academic\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LecturerAvailabilityHour extends Model
{
    protected $fillable = ["lecturer_availability_term_id", "week_day", "time_from", "time_till"];

    protected $with = [];

    protected $primaryKey = "lah_id";

    protected $appends = ["id"];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, "lecturer_id");
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(LecturerAvailabilityTerm::class, "lecturer_availability_term_id");
    }
}
