<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpaceAllocation extends Model
{
    use SoftDeletes, BaseModel;

    protected $table = "spaces_times";

    public function space()
    {
        return $this->belongsTo(Space::class, "spaces_id", "id");
    }
}
