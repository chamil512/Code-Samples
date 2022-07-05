<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class LecturerPaymentPlan extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["lecturer_id", "course_id", "payment_type", "applicable_from", "applicable_till", "applicable_days", "special_rate", "fixed_amount", "plan_status", "remarks", "approval_status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
        'applicable_days' => 'array',
    ];

    public function lecturer()
    {
        return $this->belongsTo(Lecturer::class, "lecturer_id");
    }

    public function course()
    {
        return $this->belongsTo(Course::class, "course_id", "course_id");
    }

    public function ppDocuments()
    {
        return $this->hasMany(LecturerPaymentPlanDocument::class, "lecturer_payment_plan_id");
    }

    public function examWorkTypes()
    {
        return $this->hasMany(LecturerPaymentPlanExamWorkType::class, "lecturer_payment_plan_id")->with(["examWorkType"]);
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
