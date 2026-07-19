<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'technical_id',
        'vin',
        'registration_number',
        'brand',
        'model',
        'year',
        'color',
        'current_mileage',
        'status',
        'fuel_type',
        'tank_capacity',
        'insurance_expires_at',
        'technical_control_expires_at',
        'next_service_mileage',
        'next_service_at',
        'blockchain_hash',
        'ipfs_cid',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => VehicleStatus::class,
            'insurance_expires_at' => 'date',
            'technical_control_expires_at' => 'date',
            'next_service_at' => 'date',
            'tank_capacity' => 'decimal:2',
            'current_mileage' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Vehicle $vehicle): void {
            if (empty($vehicle->technical_id)) {
                $vehicle->technical_id = (string) Str::uuid();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(VehicleAssignment::class)->where('status', 'active')->latestOfMany();
    }

    public function mileageRecords(): HasMany
    {
        return $this->hasMany(MileageRecord::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function fuelConsumptions(): HasMany
    {
        return $this->hasMany(FuelConsumption::class);
    }

    public function blockchainTransactions(): HasMany
    {
        return $this->hasMany(BlockchainTransaction::class);
    }

    public function displayName(): string
    {
        return "{$this->brand} {$this->model} ({$this->registration_number})";
    }
}
