<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubgroupRelationship extends Model
{
    use SoftDeletes, BaseModel;

    protected $with = [];

    protected $table = "subgroup_relationships";

    public function subgroupOne(): BelongsTo
    {
        return $this->belongsTo(Subgroup::class, "subgroup1_id");
    }

    public function subgroupTwo(): BelongsTo
    {
        return $this->belongsTo(Subgroup::class, "subgroup2_id");
    }
}
