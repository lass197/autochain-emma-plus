<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelConsumption extends Model
{
    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'liters',
        'cost',
        'mileage_at_fill',
        'filled_at',
        'station',
        'consumption_l_per_100km',
    ];

    protected function casts(): array
    {
        return [
            'liters' => 'decimal:2',
            'cost' => 'decimal:2',
            'consumption_l_per_100km' => 'decimal:2',
            'filled_at' => 'datetime',
            'mileage_at_fill' => 'integer',
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
