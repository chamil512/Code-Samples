<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Entities\Admin;
use Modules\Admin\Observers\AdminActivityObserver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrutinyBoardPeople extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["scrutiny_board_exam_type_id", "person_id", "exam_person_type", "created_by", "updated_by", "deleted_by"];

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

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, "person_id");
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
