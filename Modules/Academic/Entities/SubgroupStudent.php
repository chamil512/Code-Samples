<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubgroupStudent extends Model
{
    use SoftDeletes, BaseModel;

    protected $with = [];

    protected $table = "subgroupes_std";

    public function studentCourseIds(): HasMany
    {
        return $this->hasMany(StudentRegCourse::class, "student_id", "std_id");
    }

    public function subgroup(): HasMany
    {
        return $this->hasMany(Subgroup::class, "sg_id");
    }
}
