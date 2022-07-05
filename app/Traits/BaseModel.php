<?php


namespace App\Traits;

/*use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Exception;*/

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Admin\Entities\Admin;

trait BaseModel
{
    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(Admin::class, "created_by", "admin_id");
    }

    public function updatedUser(): BelongsTo
    {
        return $this->belongsTo(Admin::class, "updated_by", "admin_id");
    }

    public function deletedUser(): BelongsTo
    {
        return $this->belongsTo(Admin::class, "deleted_by", "admin_id");
    }

    /*protected function asDateTime($value)
    {
        if($value instanceof Carbon) {
            $value->timezone('Asia/Colombo');
        } else {

            try {
                $date = new DateTime($value, new DateTimeZone("UTC"));
                $date->setTimezone(new DateTimeZone('Asia/Colombo'));

                $value = $date->format("Y-m-d H:i:s");
            } catch (Exception $exception) {

            }
        }

        return parent::asDateTime($value);
    }*/
}
