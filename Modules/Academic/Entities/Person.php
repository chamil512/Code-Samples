<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Entities\Admin;
use Modules\Admin\Observers\AdminActivityObserver;
use Modules\Settings\Entities\HonorificTitle;
use Modules\Settings\Entities\University;

class Person extends Model
{
    use SoftDeletes, BaseModel;

    protected $fillable = [
        "id", "person_type", "staff_type", "academic_carder_position_id", "title_id", "name_in_full", "given_name",
        "name_with_init", "surname", "date_of_birth", "nic_no", "passport_no", "perm_address", "perm_work_address",
        "contact_no", "email", "qualification_id", "qualification_level_id", "qualified_local_foreign", "qualified_year",
        "status", "remarks", "admin_id", "created_by", "updated_by", "deleted_by"
    ];

    protected $with = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s'
    ];

    protected $appends = ["name"];

    public string $personType = "";
    public array $personTypes = [];

    public function getNameAttribute(): string
    {
        $title = "";
        if (isset($this->title)) {

            $title = $this->title->title_name;
        }

        return $title . $this->given_name . " " . $this->surname;
    }

    public function contactInfo(): HasMany
    {
        return $this->hasMany(PersonContactInformation::class, "person_id");
    }

    public function bankingInfo(): HasMany
    {
        return $this->hasMany(PersonBankingInformation::class, "person_id");
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PersonDocument::class, "person_id");
    }

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(AcademicQualification::class, "qualification_id", "qualification_id");
    }

    public function qualificationLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicQualificationLevel::class, "qualification_level_id");
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class, "university_id", "university_id");
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(HonorificTitle::class, "title_id", "title_id");
    }

    public function carderPosition(): BelongsTo
    {
        return $this->belongsTo(AcademicCarderPosition::class, "academic_carder_position_id");
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, "admin_id", "admin_id");
    }

    public function newQuery(): Builder
    {
        if (is_array($this->personTypes) && count($this->personTypes) > 0) {

            return parent::newQuery()->whereIn("person_type", $this->personTypes);
        }

        return parent::newQuery();
    }

    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if (isset($model->personType) && $model->personType !== "") {

                $model->setAttribute("person_type", $model->personType);
            }
        });

        //Use this code block to track activities regarding this model
        //Use this code block in every model you need to record
        //This will record created_by, updated_by, deleted_by admins too, if you have set those fields in your model
        self::observe(AdminActivityObserver::class);
    }
}
