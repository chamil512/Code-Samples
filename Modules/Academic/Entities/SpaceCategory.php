<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpaceCategory extends Model
{
    use SoftDeletes, BaseModel;

    protected $table = "space_category";

    protected $appends = ["name"];

    public function getNameAttribute()
    {
        return $this->category_name;
    }
}
