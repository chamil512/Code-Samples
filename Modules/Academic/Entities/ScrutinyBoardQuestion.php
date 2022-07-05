<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class ScrutinyBoardQuestion extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = [
        "scrutiny_board_exam_type_id",
        "exam_calendar_id",
        "question_no",
        "marks",
        "remarks",
        "exam_category_id",
        "question_made_by",
        "created_by",
        "updated_by",
        "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function scrutinyBoardExamType(): BelongsTo
    {
        return $this->belongsTo(ScrutinyBoardExamType::class, "scrutiny_board_exam_type_id");
    }

    public function examCategory(): BelongsTo
    {
        return $this->belongsTo(ExamCategory::class, "exam_category_id", "exam_category_id");
    }

    public function questionMadeBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, "question_made_by");
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
