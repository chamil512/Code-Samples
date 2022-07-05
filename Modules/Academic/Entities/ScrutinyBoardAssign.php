<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;
use Illuminate\Database\Eloquent\Builder;

class ScrutinyBoardAssign extends ScrutinyBoard
{
    use SoftDeletes, BaseModel;

    protected $table = "scrutiny_boards";

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["name"];

    public function newQuery(): Builder
    {
        return parent::newQuery()->where("type", 3);
    }

    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {

            $model->setAttribute("type", 3);
        });

        //Use this code block to track activities regarding this model
        //Use this code block in every model you need to record
        //This will record created_by, updated_by, deleted_by admins too, if you have set those fields in your model
        self::observe(AdminActivityObserver::class);
    }
}
