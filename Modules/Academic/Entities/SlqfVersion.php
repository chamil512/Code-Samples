<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class SlqfVersion extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = [
        "slqf_id", "version_name", "version", "slqf_file_name", "version_date", "default_status", "version_status", "created_by", "updated_by", "deleted_by"
    ];

    protected $with = [];

    protected $primaryKey = "slqf_version_id";

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
        $name = $this->version_name;
        if(isset($this->slqf->slqf_name) && $this->slqf->slqf_name!="")
        {
            $name = $this->slqf->slqf_name." - ".$this->version_name;
        }

        return $name;
    }

    public function slqf()
    {
        return $this->belongsTo(SlqfStructure::class, "slqf_id", "slqf_id");
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
