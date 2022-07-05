<?php

namespace Modules\Academic\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonContactInformation extends Model
{
    protected $fillable = ["person_id", "contact_type", "contact_detail"];

    protected $with = [];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, "person_id");
    }
}
