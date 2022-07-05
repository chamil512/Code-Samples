<?php

namespace Modules\Academic\Entities;

use Illuminate\Database\Eloquent\Model;

class LecturerContactInformation extends Model
{
    protected $fillable = ["lecturer_id", "contact_type", "contact_detail"];

    protected $with = [];

    protected $primaryKey = "lc_info_id";

    protected $appends = ["id"];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function lecturer()
    {
        return $this->belongsTo(Lecturer::class, "lecturer_id");
    }
}
