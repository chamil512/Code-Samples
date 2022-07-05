<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class CourseModule extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["course_id", "academic_year_id", "semester_id", "module_code", "module_name", "module_color_code", "total_hours", "total_credits", "module_order", "module_status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $primaryKey = "module_id";

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["id", "name", "name_year_semester", "year_name", "year_no", "semester_name", "semester_no"];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function getNameAttribute()
    {
        return $this->module_code." - ".$this->module_name;
    }

    public function getNameYearSemesterAttribute()
    {
        $name = $this->module_code." - ".$this->module_name;

        if(isset($this->academicYear->year_name) && $this->academicYear->year_name!="")
        {
            $name .= " / " . $this->academicYear->year_name;
        }

        if(isset($this->semester->semester_name) && $this->semester->semester_name!="")
        {
            $name .= " - " . $this->semester->semester_name;
        }

        return $name;
    }

    public function getYearNameAttribute()
    {
        $name = "";
        if(isset($this->academicYear->year_name) && $this->academicYear->year_name!="")
        {
            $name = $this->academicYear->year_name;
        }

        return $name;
    }

    public function getYearNoAttribute()
    {
        $name = "";
        if(isset($this->academicYear->year_no) && $this->academicYear->year_no!="")
        {
            $name = $this->academicYear->year_no;
        }

        return $name;
    }

    public function getSemesterNameAttribute()
    {
        $name = "";
        if(isset($this->semester->semester_name) && $this->semester->semester_name!="")
        {
            $name = $this->semester->semester_name;
        }

        return $name;
    }

    public function getSemesterNoAttribute()
    {
        $name = "";
        if(isset($this->semester->semester_no) && $this->semester->semester_no!="")
        {
            $name = $this->semester->semester_no;
        }

        return $name;
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, "course_id", "course_id");
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, "academic_year_id", "academic_year_id");
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(AcademicSemester::class, "semester_id", "semester_id");
    }

    public function deliveryModes(): BelongsToMany
    {
        return $this->belongsToMany(CourseSyllabus::class, "syllabus_module_delivery_modes", "module_id", "syllabus_id");
    }

    public function moduleLecturerIds(): HasMany
    {
        return $this->hasMany(LecturerCourseModule::class, "module_id", "module_id")->select("lecturer_id");
    }

    public function similarModules(): HasMany
    {
        return $this->hasMany(SimilarCourseModule::class, "module_id", "module_id")->with(["similarModule", "module"]);
    }

    public function syllabusModules(): HasMany
    {
        return $this->hasMany(SyllabusModule::class, "module_id", "module_id");
    }

    public function scrutinyBoards(): HasMany
    {
        return $this->hasMany(ScrutinyBoardModule::class, "module_id", "module_id");
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
