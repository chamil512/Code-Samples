<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class SyllabusEntryCriteriaDocument extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["syllabus_entry_criteria_id", "document_name", "file_name"];

    protected $with = [];

    protected $appends = ["name"];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
        ];

    public function getNameAttribute()
    {
        return $this->document_name;
    }

    public function entryCriteria()
    {
        return $this->belongsTo(SyllabusEntryCriteria::class, "syllabus_entry_criteria_id");
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
