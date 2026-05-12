<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RtmfFrontendItem extends Model
{
    use Auditable;
    protected $fillable = [
        'rtmf_frontend_id',
        'id_fr',
        'type',
        'label',
        'condition',
        'validation',
        'mandatory',
        'screen_name',
        'table_fieldname',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'mandatory'  => 'boolean',
        ];
    }

    public function frontend(): BelongsTo
    {
        return $this->belongsTo(RtmfFrontend::class, 'rtmf_frontend_id');
    }
}
