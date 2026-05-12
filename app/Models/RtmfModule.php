<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RtmfModule extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = ['code', 'name', 'description', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function frontends(): HasMany
    {
        return $this->hasMany(RtmfFrontend::class, 'module_id');
    }

    public function subModules(): HasMany
    {
        return $this->hasMany(RtmfSubModule::class, 'module_id')->orderBy('sort_order')->orderBy('code');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(RtmfModulePhoto::class, 'rtmf_module_id');
    }
}
