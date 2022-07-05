<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class Faculty extends Model
{
    use SoftDeletes, BaseModel;
    public $timestamps = false;

    protected $fillable = ['acc_seg_id',"faculty_code","faculty_name", "color_code", "faculty_status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $primaryKey = "faculty_id";

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
        return $this->faculty_code." - ".$this->faculty_name;
    }
 
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, "faculty_id", "faculty_id");
    }

    public function studentRegCourses(): HasMany
    {
        return $this->hasMany(StudentRegCourse::class, "faculty_id", "faculty_id");
    }

    public function lecturers(): HasMany
    {
        return $this->hasMany(Lecturer::class, "faculty_id", "faculty_id");
    }

    public function dean(): HasOne
    {
        return $this->hasOne(FacultyDean::class, "faculty_id", "faculty_id")->where("status", 1);
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
