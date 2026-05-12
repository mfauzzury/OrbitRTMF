<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RtmfModulePhoto extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'rtmf_module_id',
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

    public function module(): BelongsTo
    {
        return $this->belongsTo(RtmfModule::class, 'rtmf_module_id');
    }
}
