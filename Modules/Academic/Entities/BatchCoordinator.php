<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Entities\Admin;
use Modules\Admin\Observers\AdminActivityObserver;

class BatchCoordinator extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["batch_id", "admin_id", "description", "date_from", "date_till", "status", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["name"];

    public function getNameAttribute()
    {
        $name = "";
        if (isset($this->admin)) {

            $name = $this->admin->name;
        }

        return $name;
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class, "batch_id", "batch_id");
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, "admin_id", "admin_id");
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
