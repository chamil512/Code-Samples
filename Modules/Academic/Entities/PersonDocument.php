<?php

namespace Modules\Academic\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonDocument extends Model
{
    protected $fillable = ["person_id", "document_type", "document_name", "file_name"];

    protected $with = [];

    protected $appends = ["name"];

    public function getNameAttribute()
    {
        return $this->document_name;
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, "person_id");
    }
}
