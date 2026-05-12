<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RtmfSubModulePhoto extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'rtmf_sub_module_id',
        'filename',
        'original_name',
        'mime_type',
        'size',
        'path',
        'url',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function subModule(): BelongsTo
    {
        return $this->belongsTo(RtmfSubModule::class, 'rtmf_sub_module_id');
    }
}
