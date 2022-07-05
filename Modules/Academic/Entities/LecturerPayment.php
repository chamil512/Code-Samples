<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Entities\Admin;
use Modules\Admin\Observers\AdminActivityObserver;
use Illuminate\Support\Facades\DB;

class LecturerPayment extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = [
        "lecturer_id", "lecturer_payment_plan_id", "payment_type", "academic_timetable_information_id",
        "course_id", "batch_id", "module_id", "lecturer_payment_method_id", "remarks", "payment_month", "payment_date",
        "slot_start_time", "slot_end_time", "slot_hours", "slot_minutes", "start_time", "end_time", "actual_hours",
        "actual_minutes", "hourly_rate", "calculated_total", "approved_total", "paid_amount", "paid_hours", "paid_minutes",
        "paid_status", "status", "approval_status", "created_by", "updated_by", "deleted_by"
    ];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["month", "name"];

    public function getNameAttribute(): string
    {
        $name = "";
        if (isset($this->lecturer) && $this->lecturer != "") {

            $name = $this->lecturer->name . "'s payment request";
        }

        return $name;
    }

    public function getMonthAttribute()
    {
        $month = "";
        if (isset($this->payment_month) && $this->payment_month != "") {

            $month = date("F", strtotime($this->payment_month . "-01"));
        }

        return $month;
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(AcademicTimetableInformation::class, "academic_timetable_information_id", "academic_timetable_information_id");
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, "academic_year_id", "academic_year_id");
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(AcademicSemester::class, "semester_id", "semester_id");
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, "lecturer_id");
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, "course_id", "course_id");
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, "module_id", "module_id");
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, "batch_id", "batch_id");
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(LecturerPaymentMethod::class, "lecturer_payment_method_id", "lecturer_payment_method_id");
    }

    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(LecturerPaymentPlan::class, "lecturer_payment_plan_id");
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, "admin_id", "approval_by");
    }

    public static function boot()
    {
        parent::boot();

        //Use this code block to track activities regarding this model
        //Use this code block in every model you need to record
        //This will record created_by, updated_by, deleted_by admins too, if you have set those fields in your model
        self::observe(AdminActivityObserver::class);
    }

    public function fetchPaymentDetails($postArray, $lecturerId, $actionType = '')
    {
        if ($actionType == 'update') {
            // dd('came here');
            $query = LecturerPayment::query();
            if ($postArray['type'] == 'hourly') {
                $query->where(function ($query) {
                    $query->where('payment_type', 1);
                    $query->orWhere('payment_type', 2);
                });
                $query->where('payment_date', '<=', $postArray['to_date']);
                // $query->where('payment_date', '>=' , $postArray['from_date']);
            } elseif ($postArray['type'] == 'fixed') {
                $query->where('payment_type', 3);
                $query->where('payment_month', '=', $postArray['month']);
            }
            $query->where('paid_status', 0);
            $query->where('lecturer_id', $lecturerId);
            $query->where('approval_status', 1);
            $query->update(['paid_status' => 1, 'paid_amount' => DB::raw('`approved_total`')]);
        }
        $query = LecturerPayment::query()
            ->select('lecturer_payments.id', 'module_name', 'batch_name', 'lecturer_payments.start_time', 'lecturer_payments.end_time', 'payment_date', 'actual_hours', 'actual_minutes', 'hourly_rate', 'approved_total', 'lecturer_payments.remarks');
        if ($postArray['type'] == 'hourly') {
            $query->where(function ($query) {
                $query->where('payment_type', 1);
                $query->orWhere('payment_type', 2);
            });
            $query->where('payment_date', '<=', $postArray['to_date']);
            // $query->where('payment_date', '>=' , $postArray['from_date']);
        } elseif ($postArray['type'] == 'fixed') {
            $query->where('payment_type', 3);
            $query->where('payment_month', '=', $postArray['month']);
        }
        $query->where('lecturer_id', $lecturerId);
        $query->join('batches', 'lecturer_payments.batch_id', '=', 'batches.batch_id');
        $query->join('course_modules', 'lecturer_payments.module_id', '=', 'course_modules.module_id');
        // $query->join('academic_timetable_information', 'lecturer_payments.academic_timetable_information_id', '=', 'academic_timetable_information.academic_timetable_information_id');
        $query->where('paid_status', 0);
        $query->where('approval_status', 1);
        $details = $query->get();
        return $details;
    }

    public function fetchPaymentSummaryDetails($postArray, $filters = [], $type = 'result', $orderColumn = '', $orderDirection = 'asc', $perPage = 10, $offset = 0, $searchedFor = '')
    {
        $endDate = $postArray['endDate'];
        $query = LecturerPayment::query();
        if ($type == 'result') {
            $query->select(DB::raw('DISTINCT lecturer_payments.lecturer_id AS DT_RowId'), 'employee_id', 'staff_type', DB::raw('SUM(approved_total) AS total_to_pay'), 'lecturer_payments.id', 'hourly_rate', 'name_with_init', 'name_in_full');
        }
        if ($searchedFor != '') {
            $searchString = '%' . $searchedFor . '%';
            $query->orWhere('name_with_init', 'like', $searchString);
        }
        $query->join('people', function ($join) {
            $join->on('lecturer_payments.lecturer_id', 'people.id');
            $join->on('people.person_type', DB::raw("'lecturer'"));
        });
        if ($postArray['type'] == 'hourly') {
            $query->where(function ($query) {
                $query->where('payment_type', 1);
                $query->orWhere('payment_type', 2);
            });
            $query->where('payment_date', '<=', $endDate);
        } elseif ($postArray['type'] == 'fixed') {
            $query->where('payment_type', 3);
            $query->where('payment_month', '=', $postArray['month']);
        }
        $query->where('paid_status', 0);
        $query->where('lecturer_payments.approval_status', 1);
        $query->groupBy('lecturer_payments.lecturer_id');
        if ($type == 'count') {
            $result = $query->count();
            return $result;
        }
        if ($type == 'result') {
            $result = $query->get()->toArray();
            return $result;
        }
    }
}
