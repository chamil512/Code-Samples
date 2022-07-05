<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentRegCourse extends Model
{
    use SoftDeletes, BaseModel;

    protected $with = [];

    protected $fillable = [];

    protected $table = "student_reg_courses";
}
