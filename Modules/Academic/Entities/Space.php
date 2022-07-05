<?php

namespace Modules\Academic\Entities;

use App\Traits\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Space extends Model
{
    use SoftDeletes, BaseModel;

    protected $with = [];

    protected $fillable = [];

    protected $table = "spaces_assign";

    protected $appends = ["name", "common_name"];

    public function getNameAttribute(): string
    {
        $spaceName = "";
        if (isset($this->spaceName->name) && $this->spaceName->name != "") {
            $spaceName = $this->spaceName->name;
        }

        $typeName = "";
        if (isset($this->spaceType->name) && $this->spaceType->name != "") {
            $typeName = $this->spaceType->name;
        }

        $max = "";
        if (isset($this->std_count) && $this->std_count != "") {
            $max = $this->std_count;
        }

        return $spaceName . " [" . $typeName . "] [" . $max . " Max]";
    }

    public function getCommonNameAttribute(): string
    {
        $spaceName = "";
        if (isset($this->spaceName->name) && $this->spaceName->name != "") {
            $spaceName = $this->spaceName->name;
        }

        $typeName = "";
        if (isset($this->spaceType->name) && $this->spaceType->name != "") {
            $typeName = $this->spaceType->name;
        }

        return $spaceName . " [" . $typeName . "]";
    }

    public function spaceName(): BelongsTo
    {
        return $this->belongsTo(SpaceName::class, "cn_id", "id");
    }

    public function spaceType(): BelongsTo
    {
        return $this->belongsTo(SpaceType::class, "type_id", "id");
    }
}
