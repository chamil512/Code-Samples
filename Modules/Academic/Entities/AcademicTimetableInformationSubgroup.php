<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class AcademicTimetableInformationSubgroup extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["academic_timetable_id", "module_id", "tt_date", "start_time", "end_time", "hours",
        "delivery_mode_id", "delivery_mode_id_special", "exam_type_id", "exam_category_id", "week",
        "slot_type", "slot_type_remarks", "approval_status", "cancelled_slot_id", "rescheduled_slot_id", "slot_type",
        "created_by", "updated_by", "deleted_by", "subgroup_id", "sg_module_id"];

    protected $primaryKey = "academic_timetable_information_id";

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $with = [];

    protected $appends = ["id", "name", "mode_id", "mode_name"];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function getNameAttribute()
    {
        $moduleName = "";
        if (isset($this->module->name)) {

            $moduleName = $this->module->name;
        }

        return $this->tt_date . " [" . $this->start_time . " - " . $this->end_time . "] - " . $moduleName;
    }

    public function getModeIdAttribute()
    {
        $modeId = $this->delivery_mode_id;
        if (isset($this->deliveryModeSpecial->id)) {

            $modeId = $this->deliveryModeSpecial->id;
        }

        return $modeId;
    }

    public function getModeNameAttribute()
    {
        $modeName = "";
        if (isset($this->deliveryModeSpecial->name) && $this->deliveryModeSpecial->name !== null) {

            $modeName = $this->deliveryModeSpecial->name;
        } else {

            if (isset($this->deliveryMode->name)) {

                $modeName = $this->deliveryMode->name;
            }
        }

        return $modeName;
    }

    public function cancelled(): BelongsTo
    {
        return $this->belongsTo(AcademicTimetableInformationSubgroup::class, "cancelled_slot_id", "academic_timetable_information_id")
            ->with(["timetable", "module", "deliveryMode", "deliveryModeSpecial", "examType",
                "examCategory", "lecturers", "subgroups", "spaces"]);
    }

    public function rescheduled(): BelongsTo
    {
        return $this->belongsTo(AcademicTimetableInformationSubgroup::class, "rescheduled_slot_id", "academic_timetable_information_id")
            ->with(["timetable", "module", "deliveryMode", "deliveryModeSpecial", "examType",
                "examCategory", "lecturers", "subgroups", "spaces"]);
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(AcademicTimetable::class, "academic_timetable_id", "academic_timetable_id")
            ->with(["academicYear", "semester"]);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, "module_id", "module_id")->select("module_id", "module_name", "module_code", "module_color_code");
    }

    public function deliveryMode(): BelongsTo
    {
        return $this->belongsTo(ModuleDeliveryMode::class, "delivery_mode_id", "delivery_mode_id")->select("delivery_mode_id", "mode_name");
    }

    public function deliveryModeSpecial(): BelongsTo
    {
        return $this->belongsTo(ModuleDeliveryMode::class, "delivery_mode_id_special", "delivery_mode_id")->select("delivery_mode_id", "mode_name");
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class, "exam_type_id", "exam_type_id")->select("exam_type_id", "exam_type");
    }

    public function examCategory(): BelongsTo
    {
        return $this->belongsTo(ExamCategory::class, "exam_category_id", "exam_category_id")->select("exam_category_id", "exam_category");
    }

    public function lecturers(): HasManyThrough
    {
        return $this->hasManyThrough(Lecturer::class, AcademicTimetableLecturer::class, "academic_timetable_information_id", "id", "academic_timetable_information_id", "lecturer_id");
    }

    public function subgroups(): HasManyThrough
    {
        return $this->hasManyThrough(Subgroup::class, AcademicTimetableSubgroup::class, "academic_timetable_information_id", "id", "academic_timetable_information_id", "subgroup_id");
    }

    public function spaces(): HasManyThrough
    {
        return $this->hasManyThrough(Space::class, AcademicTimetableSpace::class, "academic_timetable_information_id", "id", "academic_timetable_information_id", "space_id");
    }

    public function ttInfoLecturers(): HasMany
    {
        return $this->hasMany(AcademicTimetableLecturer::class, "academic_timetable_information_id", "academic_timetable_information_id")->with(["lecturer"]);
    }

    public function ttInfoSubgroups(): HasMany
    {
        return $this->hasMany(AcademicTimetableSubgroup::class, "academic_timetable_information_id", "academic_timetable_information_id");
    }

    public function ttInfoSpaces(): HasMany
    {
        return $this->hasMany(AcademicTimetableSpace::class, "academic_timetable_information_id", "academic_timetable_information_id")->with(["space"]);
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(AcademicTimetableAttendance::class, "at_information_id", "academic_timetable_information_id");
    }

    public function subgroup(): BelongsTo
    {
        return $this->belongsTo(Subgroup::class, "subgroup_id", "id");
    }

    public function sgModule(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, "sg_module_id", "module_id")->select("module_id", "module_name", "module_code", "module_color_code");
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

    public function syllabus(): BelongsTo
    {
        return $this->belongsTo(CourseSyllabus::class, "syllabus_id", "syllabus_id");
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, "batch_id", "batch_id");
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, "group_id", "GroupID");
    }
}
