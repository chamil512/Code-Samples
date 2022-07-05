<?php

namespace Modules\Academic\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LecturerDocument extends Model
{
    use SoftDeletes;

    protected $fillable = ["lecturer_id", "document_type", "document_name", "file_name"];

    protected $with = [];

    protected $appends = ["name"];

    public function getNameAttribute()
    {
        return $this->document_name;
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, "lecturer_id");
    }
}
