<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelConsumption;
use App\Models\Vehicle;
use App\Services\FuelConsumptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FuelConsumptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = FuelConsumption::query()
            ->with(['vehicle:id,registration_number', 'driver:id,name'])
            ->when($request->vehicle_id, fn ($q, $id) => $q->where('vehicle_id', $id))
            ->latest('filled_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($items);
    }

    public function store(Request $request, FuelConsumptionService $service): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'liters' => ['required', 'numeric', 'min:0.1'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'mileage_at_fill' => ['required', 'integer', 'min:0'],
            'filled_at' => ['nullable', 'date'],
            'station' => ['nullable', 'string', 'max:255'],
        ]);

        $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
        $record = $service->record($vehicle, $request->user(), $data);

        return response()->json([
            'message' => 'Plein enregistré.',
            'record' => $record,
            'average_consumption' => $service->averageForVehicle($vehicle),
        ], 201);
    }

    public function average(Vehicle $vehicle, FuelConsumptionService $service): JsonResponse
    {
        return response()->json([
            'vehicle_id' => $vehicle->id,
            'registration_number' => $vehicle->registration_number,
            'average_l_per_100km' => $service->averageForVehicle($vehicle),
        ]);
    }
}
