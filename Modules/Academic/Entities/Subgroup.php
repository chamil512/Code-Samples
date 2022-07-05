<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subgroup extends Model
{
    use SoftDeletes, BaseModel;

    protected $with = [];

    protected $table = "subgroups";

    protected $appends = ["name"];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function getNameAttribute(): string
    {
        $year = "";
        if (isset($this->academicYear->name) && $this->academicYear->name != "") {
            $year = " [" . $this->academicYear->name . "]";
        }

        $semester = "";
        if (isset($this->academicSemester->name) && $this->academicSemester->name != "") {
            $semester = " [" . $this->academicSemester->name . "]";
        }

        return $this->sg_name . " [" . $this->max_students . " Max]" . $year . $semester;
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, "main_gid", "GroupID");
    }

    public function syllabus(): BelongsTo
    {
        return $this->belongsTo(CourseSyllabus::class, "syllabus_id", "syllabus_id");
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, "faculty_id", "faculty_id");
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, "dept_id", "dept_id");
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, "course_id", "course_id");
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, "batch_id", "batch_id");
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, "year", "academic_year_id");
    }

    public function academicSemester(): BelongsTo
    {
        return $this->belongsTo(AcademicSemester::class, "semester", "semester_id");
    }

    public function subgroupModules(): HasMany
    {
        return $this->hasMany(SubgroupModule::class, "subgroup_id");
    }

    public function deliveryMode(): HasOne
    {
        return $this->hasOne(ModuleDeliveryMode::class, "delivery_mode_id", "dm_id");
    }

    public function subgroupOne(): hasMany
    {
        return $this->hasMany(SubgroupRelationship::class, "subgroup1_id");
    }

    public function subgroupTwo(): hasMany
    {
        return $this->hasMany(SubgroupRelationship::class, "subgroup2_id");
    }

    public function subgroupStudents(): hasMany
    {
        return $this->hasMany(SubgroupStudent::class, "sg_id");
    }
}
