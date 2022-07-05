<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BatchGroup extends Model
{
    use SoftDeletes, BaseModel;

    protected $with = [];

    protected $table = "groupe_batches";

    public function batch()
    {
        return $this->belongsTo(Batch::class, "batch_id", "batch_id");
    }

    public function group()
    {
        return $this->belongsTo(Group::class, "group_id", "GroupID");
    }
}
