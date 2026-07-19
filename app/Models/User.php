<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'wallet_address', 'phone', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function hasRole(UserRole|string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        return collect($roles)->contains(function ($role) {
            $value = $role instanceof UserRole ? $role->value : $role;

            return $this->role?->value === $value;
        });
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function createdVehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'created_by');
    }

    public function assignmentsAsDriver(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class, 'driver_id');
    }

    public function mileageRecords(): HasMany
    {
        return $this->hasMany(MileageRecord::class, 'driver_id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class, 'garage_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }
}
