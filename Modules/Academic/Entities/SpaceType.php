<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpaceType extends Model
{
    use SoftDeletes, BaseModel;

    protected $table = "space_categorytypes";

    protected $appends = ["name"];

    public function getNameAttribute()
    {
        return $this->type_name;
    }
}
