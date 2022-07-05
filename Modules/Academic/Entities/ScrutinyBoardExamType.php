<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class ScrutinyBoardExamType extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["scrutiny_board_module_id", "academic_timetable_information_id",
        "exam_type_id", "based_exam_category_id", "exam_category_id", "marks_percentage", "exam_method",
        "no_of_questions", "duration_in_minutes", "examiner_type", "paper_typing", "paper_setting",
        "paper_marking", "active_status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function scrutinyBoardModule(): BelongsTo
    {
        return $this->belongsTo(ScrutinyBoardModule::class, "scrutiny_board_module_id");
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(AcademicTimetableInformation::class,
            "academic_timetable_information_id", "academic_timetable_information_id");
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class, "exam_type_id", "exam_type_id");
    }

    public function examCategory(): BelongsTo
    {
        return $this->belongsTo(ExamCategory::class, "exam_category_id", "exam_category_id");
    }

    public function people(): HasMany
    {
        return $this->hasMany(ScrutinyBoardPeople::class, "scrutiny_board_exam_type_id");
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ScrutinyBoardQuestion::class, "scrutiny_board_exam_type_id");
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
