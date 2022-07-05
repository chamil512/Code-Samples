<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExamWorkType extends Model
{
    use SoftDeletes, BaseModel;

    protected $with = [];

    protected $fillable = [];

    protected $primaryKey = "exam_workers_type_id";

    protected $table = "exam_workers_types";

    protected $appends = ["id", "name"];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function getNameAttribute()
    {
        return $this->type_name;
    }
}
