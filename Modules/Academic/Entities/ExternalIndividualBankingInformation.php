<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class ExternalIndividualBankingInformation extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["external_individual_id", "type", "tax_id_no", "bank_name", "bank_branch", "bank_branch_code", "bank_acc_name", "emg_contact_name", "emg_contact_no", "emg_postal_address", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function externalIndividual()
    {
        return $this->belongsTo(ExternalIndividual::class, "external_individual_id");
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
