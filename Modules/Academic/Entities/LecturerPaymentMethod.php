<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class LecturerPaymentMethod extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = [
        "payment_method",
        "faculty_id",
        "dept_id",
        "course_category_id",
        "course_id",
        "qualification_id",
        "hourly_rate",
        "pm_status",
        "approval_status",
        "remarks",
        "created_by",
        "updated_by",
        "deleted_by"
    ];

    protected $with = [];

    protected $primaryKey = "lecturer_payment_method_id";

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["id", "name"];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function getNameAttribute()
    {
        $name = $this->payment_method;

        if (isset($this->academicQualification)) {

            $name .= " (" . $this->academicQualification->name . ")";
        }

        return $name;
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, "faculty_id", "faculty_id");
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, "dept_id", "dept_id");
    }

    public function courseCategory(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, "course_category_id", "course_category_id");
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, "course_id", "course_id");
    }

    public function academicQualification(): BelongsTo
    {
        return $this->belongsTo(AcademicQualification::class, "qualification_id", "qualification_id");
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LecturerPayment::class, "lecturer_payment_method_id", "lecturer_payment_method_id");
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
