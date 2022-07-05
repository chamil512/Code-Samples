<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class LecturerPaymentPlanDocument extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["lecturer_payment_plan_id", "document_name", "file_name", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $appends = ["name"];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function getNameAttribute()
    {
        return $this->document_name;
    }

    public function paymentPlan()
    {
        return $this->belongsTo(LecturerPaymentPlan::class, "lecturer_payment_plan_id");
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
