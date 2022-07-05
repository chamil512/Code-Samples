<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class BatchAvailabilityDate extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["batch_availability_restriction_id", "date", "created_by", "updated_by", "deleted_by"];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["name"];

    public function getNameAttribute(): string
    {
        $name = "";

        if (isset($this->bar)) {

            $name .= $this->bar->name;
        }

        $name .= $this->date;

        return $name;
    }

    public function bar(): BelongsTo
    {
        return $this->belongsTo(BatchAvailabilityRestriction::class, "batch_availability_restriction_id");
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
