<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Observers\AdminActivityObserver;
use Modules\Hr\Entities\CalendarE;
use Modules\Hr\Entities\EmployeeParameterConfigurationE;
use Modules\Hr\Entities\EmployeeRostersE;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Person
{
    use SoftDeletes, BaseModel;

    protected $table = "people";

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    public string $personType = "employee";

    public array $personTypes = ["lecturer", "employee", "individual"];

    public function newQuery(): Builder
    {
        return parent::newQuery()->where("staff_type", 1);
    }

    public static function getData($postArray, $filters = [], $type = 'result', $orderColumn = '', $orderDirection = 'desc', $perPage = 10, $offset = 0, $searchedFor = '')
    {
        DB::connection()->enableQueryLog();
        $query = Employee::query();
        if (!empty($filters)) {
            foreach ($filters as $key => $val) {
                if ($key != 'to_date') {
                    if ($val != 'all') {
                        $query->Where($key, '=', $val);
                    }
                }
            }
        }
        if ($searchedFor != '') {
            $searchString = '%' . $searchedFor . '%';
            $query->where(function ($query) use ($searchedFor, $searchString) {
                $query->orWhere('name_in_full', 'like', $searchString);
                $query->orWhere('people.serial_number', $searchedFor);
            });
        }
        $query->where('staff_type', 1);
        if ($type == 'result') {
            $query->select(
                'people.id AS DT_RowId',
                'people.serial_number AS employee_number',
                'name_with_init',
                'name_in_full',
                'nic_no',
                'contact_no',
                'email',
                'people.status',
                'admin_departments.department_name',
                'hr_emp_designation.name AS designation'
            )
                ->skip($offset * $perPage)
                ->take($perPage)
                ->orderBy($orderColumn, $orderDirection);
        }
        // $query->LeftJoin('hr_emp_param_configuration', 'people.id', 'hr_emp_param_configuration.employee_id');
        $query->LeftJoin('admin_departments', 'people.admin_department_id', 'admin_departments.admin_department_id');
        $query->LeftJoin('hr_emp_designation', 'people.designation', 'hr_emp_designation.id');
        if ($type == 'count') {
            $result = $query->count();
            return $result;
        }
        $result = $query->get()->toArray();
        if ($type == 'result') {
            return $result;
        }
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, "faculty_id", "faculty_id");
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, "dept_id", "dept_id");
    }

    public    function rosters()
    {
        return $this->hasMany(EmployeeRostersE::class, 'employee_number', 'id')->where('status', 'ACTIVE');
    }

    public function rosterSchedule()
    {
        return $this->hasMany(\Modules\Hr\Entities\EmployeeRosterScheduleE::class, "employee_id", "id");
    }

    public function parameterConfig()
    {
        return $this->belongsTo(EmployeeParameterConfigurationE::class, 'id', 'employee_id');
    }

    public static function boot()
    {
        parent::boot();

        //Use this code block to track activities regarding this model
        //Use this code block in every model you need to record
        //This will record created_by, updated_by, deleted_by admins too, if you have set those fields in your model
        self::observe(AdminActivityObserver::class);
    }

    public static function getEmployeeDetails($adminId)
    {
        $employee = Employee::query()->where('admin_id', $adminId)->get()->first();
        return $employee;
    }
}

