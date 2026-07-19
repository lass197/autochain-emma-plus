<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MileageRecord extends Model
{
    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'mileage',
        'previous_mileage',
        'recorded_at',
        'blockchain_tx_hash',
        'is_certified',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'is_certified' => 'boolean',
            'mileage' => 'integer',
            'previous_mileage' => 'integer',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
