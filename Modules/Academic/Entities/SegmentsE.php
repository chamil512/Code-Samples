<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Entities\AdminDepartment;
use Modules\Admin\Observers\AdminActivityObserver;
use Modules\Finance\Http\Controllers\StudentPaymentPlanC;
use Illuminate\Support\Facades\DB;

class SegmentsE extends Model
{
    // use SoftDeletes;
    protected $table = 'acc_cmn_segments';
    protected $guarded = [];
    protected $fillable  = [
        'name',
        'qulifier',
        'format_type',
        'max_size_input_field'
    ];
    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function setCreatedAtAs()
    {
        return $this->created_at = date('Y-m-d H:i:s');
    }
    public function setUpdatedAtAs()
    {
        return  $this->updated_at = date('Y-m-d H:i:s');
    }

    public static function getActiveAll()
    {
        $data = SegmentsE::query()->orderBy('name')->get()->all();
       
        return $data;
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
