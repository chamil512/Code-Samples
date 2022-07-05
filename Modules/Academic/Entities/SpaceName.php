<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpaceName extends Model
{
    use SoftDeletes, BaseModel;

    protected $table = "space_categoryname";
}
