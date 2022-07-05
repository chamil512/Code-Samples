<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubgroupModule extends Model
{
    use SoftDeletes, BaseModel;

    protected $with = [];

    protected $table = "subgroupes_modules";

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function subgroup(): BelongsTo
    {
        return $this->belongsTo(Subgroup::class, "subgroup_id");
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, "module_id", "module_id")->select(["module_id", "module_name", "module_code", "module_color_code"])->where("module_status", "1");
    }
}
