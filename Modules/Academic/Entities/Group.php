<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use SoftDeletes, BaseModel;

    protected $with = [];

    protected $table = "groupes";

    protected $primaryKey = "GroupID";

    protected $appends = ["id", "name"];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function getNameAttribute()
    {
        return $this->GroupName;
    }

    public function batchGroups()
    {
        return $this->hasMany(BatchGroup::class, "group_id", "GroupID");
    }
}
