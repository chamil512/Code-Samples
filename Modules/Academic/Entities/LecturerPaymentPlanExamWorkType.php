<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class LecturerPaymentPlanExamWorkType extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["lecturer_payment_plan_id", "exam_work_type_id", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function paymentPlan()
    {
        return $this->belongsTo(LecturerPaymentPlan::class, "lecturer_payment_plan_id");
    }

    public function examWorkType()
    {
        return $this->belongsTo(ExamWorkType::class, "exam_work_type_id", "exam_workers_type_id");
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
