<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class ModuleDeliveryMode extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["mode_name", "type", "mode_status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $primaryKey = "delivery_mode_id";

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
        return $this->mode_name;
    }

    /*public function syllabusModuleDeliveryModes()
    {
        return $this->belongsTo(SyllabusModuleDeliveryMode::class, "delivery_mode_id", "delivery_mode_id");
    }*/

    public static function boot()
    {
        parent::boot();

        //Use this code block to track activities regarding this model
        //Use this code block in every model you need to record
        //This will record created_by, updated_by, deleted_by admins too, if you have set those fields in your model
        self::observe(AdminActivityObserver::class);
    }
}
