<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrutinyBoardModule extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["scrutiny_board_id", "module_id", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function scrutinyBoard(): BelongsTo
    {
        return $this->belongsTo(ScrutinyBoard::class, "scrutiny_board_id");
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, "module_id", "module_id");
    }

    public function examTypes(): HasMany
    {
        return $this->hasMany(ScrutinyBoardExamType::class, "scrutiny_board_module_id");
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
