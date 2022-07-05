<?php

namespace Modules\Academic\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonBankingInformation extends Model
{
    protected $fillable = ["person_id", "type", "tax_id_no", "bank_name", "bank_branch", "bank_branch_code",
        "bank_acc_name", "bank_acc_number", "emg_contact_name", "emg_contact_no", "emg_postal_address"];

    protected $with = [];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, "person_id");
    }
}
