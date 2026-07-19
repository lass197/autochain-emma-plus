<?php

namespace App\Models;

use App\Enums\MaintenanceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Maintenance extends Model
{
    protected $fillable = [
        'vehicle_id',
        'garage_id',
        'type',
        'title',
        'description',
        'parts_changed',
        'mileage_at_service',
        'cost',
        'performed_at',
        'blockchain_tx_hash',
        'is_certified',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => MaintenanceType::class,
            'parts_changed' => 'array',
            'performed_at' => 'datetime',
            'is_certified' => 'boolean',
            'cost' => 'decimal:2',
            'mileage_at_service' => 'integer',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(User::class, 'garage_id');
    }
}
