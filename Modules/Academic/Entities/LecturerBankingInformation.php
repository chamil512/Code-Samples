<?php

namespace Modules\Academic\Entities;

use Illuminate\Database\Eloquent\Model;

class LecturerBankingInformation extends Model
{
    protected $fillable = ["lecturer_id", "type", "tax_id_no", "bank_name", "bank_branch", "bank_branch_code", "bank_acc_name", "emg_contact_name", "emg_contact_no", "emg_postal_address"];

    protected $with = [];

    protected $primaryKey = "lb_info_id";

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
