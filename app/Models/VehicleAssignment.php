<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleAssignment extends Model
{
    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'assigned_by',
        'started_at',
        'ended_at',
        'status',
        'mileage_at_start',
        'mileage_at_end',
        'driver_acknowledged',
        'acknowledged_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'driver_acknowledged' => 'boolean',
            'mileage_at_start' => 'integer',
            'mileage_at_end' => 'integer',
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

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
