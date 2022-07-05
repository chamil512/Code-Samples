<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class SlqfStructure extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["slqf_code", "slqf_name", "remarks", "approval_status", "slqf_status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $primaryKey = "slqf_id";

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["id", "name"];

    public function getIdAttribute()
    {
        return $this->{$this->primaryKey};
    }

    public function getNameAttribute()
    {
        return $this->slqf_code." - ".$this->slqf_name;
    }

    public function slqfVersions()
    {
        return $this->hasMany(SlqfVersion::class, "slqf_id", "slqf_id");
    }

    public function courses()
    {
        return $this->hasMany(Course::class, "slqf_id", "slqf_id");
    }

    public static function boot()
    {
        parent::boot();

        //Use this code block to track activities regarding this model
        //Use this code block in every model you need to record
        //This will record created_by, updated_by, deleted_by admins too, if you have set those fields in your model
        self::observe(AdminActivityObserver::class);
    }
}
