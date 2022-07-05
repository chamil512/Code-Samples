<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class BatchAvailabilityRestriction extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["batch_id", "academic_year_id", "semester_id", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["base_name"];

    public function getBaseNameAttribute(): string
    {
        $name = "";

        if (isset($this->batch)) {

            $name .= $this->batch->name;
        }

        if (isset($this->academicYear)) {

            $name .= " - " . $this->academicYear->name;
        }

        if (isset($this->semester)) {

            $name .= " - " . $this->semester->name;
        }

        return $name;
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, "batch_id", "batch_id");
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, "academic_year_id", "academic_year_id");
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(AcademicSemester::class, "semester_id", "semester_id");
    }

    public function dates(): HasMany
    {
        return $this->hasMany(BatchAvailabilityDate::class, "batch_availability_restriction_id");
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
