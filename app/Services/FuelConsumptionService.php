<?php

namespace App\Services;

use App\Models\FuelConsumption;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class FuelConsumptionService
{
    public function record(Vehicle $vehicle, User $driver, array $data): FuelConsumption
    {
        return DB::transaction(function () use ($vehicle, $driver, $data) {
            $previous = FuelConsumption::query()
                ->where('vehicle_id', $vehicle->id)
                ->orderByDesc('mileage_at_fill')
                ->first();

            $consumption = null;
            if ($previous && $data['mileage_at_fill'] > $previous->mileage_at_fill) {
                $distance = $data['mileage_at_fill'] - $previous->mileage_at_fill;
                if ($distance > 0) {
                    $consumption = round(($data['liters'] / $distance) * 100, 2);
                }
            }

            return FuelConsumption::query()->create([
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'liters' => $data['liters'],
                'cost' => $data['cost'] ?? 0,
                'mileage_at_fill' => $data['mileage_at_fill'],
                'filled_at' => $data['filled_at'] ?? now(),
                'station' => $data['station'] ?? null,
                'consumption_l_per_100km' => $consumption,
            ]);
        });
    }

    public function averageForVehicle(Vehicle $vehicle): ?float
    {
        $avg = FuelConsumption::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereNotNull('consumption_l_per_100km')
            ->avg('consumption_l_per_100km');

        return $avg !== null ? round((float) $avg, 2) : null;
    }
}
