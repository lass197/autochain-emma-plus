<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockchainTransaction extends Model
{
    protected $fillable = [
        'vehicle_id',
        'initiated_by',
        'action_type',
        'payload_hash',
        'payload',
        'tx_hash',
        'block_number',
        'status',
        'signed_by_admin',
        'signed_by_buyer',
        'admin_signed_at',
        'buyer_signed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'admin_signed_at' => 'datetime',
            'buyer_signed_at' => 'datetime',
            'block_number' => 'integer',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function adminSigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_admin');
    }

    public function buyerSigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_buyer');
    }

    public function isFullySigned(): bool
    {
        return $this->signed_by_admin !== null && $this->signed_by_buyer !== null;
    }
}
