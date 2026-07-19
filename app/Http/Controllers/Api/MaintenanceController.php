<?php

namespace App\Http\Controllers\Api;

use App\Enums\MaintenanceType;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\Vehicle;
use App\Services\BlockchainService;
use App\Services\Web3\ContractAnchorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MaintenanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Maintenance::query()
            ->with(['vehicle:id,registration_number,technical_id', 'garage:id,name'])
            ->when($request->vehicle_id, fn ($q, $id) => $q->where('vehicle_id', $id))
            ->latest('performed_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    public function store(
        Request $request,
        BlockchainService $blockchain,
        ContractAnchorService $anchor,
    ): JsonResponse {

        $data = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'type' => ['required', Rule::enum(MaintenanceType::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parts_changed' => ['nullable', 'array'],
            'parts_changed.*' => ['string', 'max:255'],
            'mileage_at_service' => ['required', 'integer', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'performed_at' => ['nullable', 'date'],
            'next_service_mileage' => ['nullable', 'integer', 'min:0'],
            'next_service_at' => ['nullable', 'date'],
        ]);

        $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);

        $maintenance = DB::transaction(function () use ($data, $vehicle, $request, $blockchain, $anchor) {
            $maintenance = Maintenance::query()->create([
                'vehicle_id' => $vehicle->id,
                'garage_id' => $request->user()->id,
                'type' => $data['type'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'parts_changed' => $data['parts_changed'] ?? [],
                'mileage_at_service' => $data['mileage_at_service'],
                'cost' => $data['cost'] ?? 0,
                'performed_at' => $data['performed_at'] ?? now(),
                'is_certified' => false,
                'status' => 'pending',
            ]);

            $tx = $blockchain->prepareProof($vehicle, 'maintenance', [
                'type' => $maintenance->type->value,
                'mileage_at_service' => $maintenance->mileage_at_service,
                'parts_hash' => hash('sha256', json_encode($maintenance->parts_changed ?? [])),
                'performed_at' => $maintenance->performed_at->toIso8601String(),
            ], $request->user());

            $tx = $anchor->anchor($tx);

            $maintenance->update([
                'blockchain_tx_hash' => $tx->tx_hash,
                'is_certified' => true,
                'status' => 'certified',
            ]);

            $vehicle->update([
                'status' => VehicleStatus::Disponible,
                'current_mileage' => max($vehicle->current_mileage, $data['mileage_at_service']),
                'next_service_mileage' => $data['next_service_mileage'] ?? $vehicle->next_service_mileage,
                'next_service_at' => $data['next_service_at'] ?? $vehicle->next_service_at,
            ]);

            return $maintenance;
        });

        return response()->json([
            'message' => 'Maintenance certifiée et ancrée.',
            'maintenance' => $maintenance->fresh(['vehicle', 'garage']),
        ], 201);
    }

    public function show(Maintenance $maintenance): JsonResponse
    {
        return response()->json([
            'maintenance' => $maintenance->load(['vehicle', 'garage:id,name,email']),
        ]);
    }
}
