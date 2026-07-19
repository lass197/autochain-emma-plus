<?php

namespace App\Http\Controllers\Api;

use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\AlertService;
use App\Services\BlockchainService;
use App\Services\FuelConsumptionService;
use App\Services\VehicleTimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vehicles = Vehicle::query()
            ->with(['activeAssignment.driver', 'creator:id,name'])
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('registration_number', 'like', "%{$search}%")
                        ->orWhere('vin', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('technical_id', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($vehicles);
    }

    public function store(Request $request, BlockchainService $blockchain): JsonResponse
    {
        $data = $request->validate([
            'vin' => ['required', 'string', 'size:17', 'unique:vehicles,vin'],
            'registration_number' => ['required', 'string', 'max:20', 'unique:vehicles,registration_number'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
            'color' => ['nullable', 'string', 'max:50'],
            'current_mileage' => ['nullable', 'integer', 'min:0'],
            'fuel_type' => ['nullable', 'string', 'max:50'],
            'tank_capacity' => ['nullable', 'numeric', 'min:0'],
            'insurance_expires_at' => ['nullable', 'date'],
            'technical_control_expires_at' => ['nullable', 'date'],
            'next_service_mileage' => ['nullable', 'integer', 'min:0'],
            'next_service_at' => ['nullable', 'date'],
        ]);

        $vehicle = Vehicle::query()->create([
            ...$data,
            'status' => VehicleStatus::Disponible,
            'created_by' => $request->user()->id,
        ]);

        $blockchain->prepareProof($vehicle, 'vehicle_registered', [
            'vin_hash' => hash('sha256', $vehicle->vin),
            'registration_hash' => hash('sha256', $vehicle->registration_number),
            'mileage' => $vehicle->current_mileage,
        ], $request->user());

        return response()->json([
            'message' => 'Véhicule enregistré.',
            'vehicle' => $vehicle,
        ], 201);
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        $vehicle->load([
            'activeAssignment.driver:id,name,email,phone',
            'creator:id,name',
            'documents',
            'alerts' => fn ($q) => $q->where('is_resolved', false)->latest()->limit(10),
        ]);

        return response()->json(['vehicle' => $vehicle]);
    }

    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->validate([
            'registration_number' => ['sometimes', 'string', 'max:20', Rule::unique('vehicles', 'registration_number')->ignore($vehicle->id)],
            'brand' => ['sometimes', 'string', 'max:100'],
            'model' => ['sometimes', 'string', 'max:100'],
            'year' => ['sometimes', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
            'color' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::enum(VehicleStatus::class)],
            'fuel_type' => ['nullable', 'string', 'max:50'],
            'tank_capacity' => ['nullable', 'numeric', 'min:0'],
            'insurance_expires_at' => ['nullable', 'date'],
            'technical_control_expires_at' => ['nullable', 'date'],
            'next_service_mileage' => ['nullable', 'integer', 'min:0'],
            'next_service_at' => ['nullable', 'date'],
            'ipfs_cid' => ['nullable', 'string', 'max:255'],
        ]);

        $vehicle->update($data);

        return response()->json(['message' => 'Véhicule mis à jour.', 'vehicle' => $vehicle->fresh()]);
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $vehicle->update(['status' => VehicleStatus::Archive]);
        $vehicle->delete();

        return response()->json(['message' => 'Véhicule archivé.']);
    }

    public function timeline(Vehicle $vehicle, VehicleTimelineService $timeline): JsonResponse
    {
        return response()->json([
            'vehicle_id' => $vehicle->id,
            'technical_id' => $vehicle->technical_id,
            'timeline' => $timeline->build($vehicle),
        ]);
    }

    public function dashboard(Request $request, FuelConsumptionService $fuelService, AlertService $alertService): JsonResponse
    {
        $stats = [
            'total_vehicles' => Vehicle::query()->count(),
            'disponibles' => Vehicle::query()->where('status', VehicleStatus::Disponible)->count(),
            'affectes' => Vehicle::query()->where('status', VehicleStatus::Affecte)->count(),
            'en_maintenance' => Vehicle::query()->where('status', VehicleStatus::EnMaintenance)->count(),
            'alerts_ouvertes' => $request->user()->alerts()->where('is_resolved', false)->count(),
        ];

        $recentVehicles = Vehicle::query()
            ->with('activeAssignment.driver:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'registration_number' => $v->registration_number,
                'brand' => $v->brand,
                'model' => $v->model,
                'status' => $v->status?->value,
                'current_mileage' => $v->current_mileage,
                'avg_consumption' => $fuelService->averageForVehicle($v),
                'driver' => $v->activeAssignment?->driver?->only(['id', 'name']),
            ]);

        return response()->json([
            'stats' => $stats,
            'recent_vehicles' => $recentVehicles,
            'project' => config('autochain.name'),
        ]);
    }
}
