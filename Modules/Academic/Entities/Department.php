<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class Department extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["faculty_id", "dept_code", "dept_name", "color_code", "dept_status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $primaryKey = "dept_id";

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
        return $this->dept_code." - ".$this->dept_name;
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, "faculty_id", "faculty_id");
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, "dept_id", "dept_id");
    }

    public function studentRegCourses(): HasMany
    {
        return $this->hasMany(StudentRegCourse::class, "dept_id", "dept_id");
    }

    public function lecturers(): HasMany
    {
        return $this->hasMany(Lecturer::class, "dept_id", "dept_id");
    }

    public function head(): HasOne
    {
        return $this->hasOne(DepartmentHead::class, "dept_id", "dept_id")->where("status", 1);
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
