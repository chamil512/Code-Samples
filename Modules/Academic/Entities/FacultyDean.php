<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;

class FacultyDean extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = ["dean_type", "lecturer_id", "employee_id", "external_individual_id", "profile_image", "description", "date_from", "date_till", "status", "created_by", "updated_by", "deleted_by"];

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
        if ($this->dean_type === 1) {

            if (isset($this->lecturer)) {

                $name = $this->lecturer->name;
            }
        }
        else if ($this->dean_type === 2) {

            if (isset($this->employee)) {

                $name = $this->employee->name;
            }
        }
        else if ($this->dean_type === 3) {

            if (isset($this->externalIndividual)) {

                $name = $this->externalIndividual->name;
            }
        }

        return $name;
    }

    public function faculty()
    {
        return $this->belongsTo(Faculty::class, "faculty_id", "faculty_id");
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, "employee_id");
    }

    public function lecturer()
    {
        return $this->belongsTo(Lecturer::class, "lecturer_id");
    }

    public function externalIndividual()
    {
        return $this->belongsTo(ExternalIndividual::class, "external_individual_id");
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
