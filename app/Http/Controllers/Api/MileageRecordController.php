<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MileageRecord;
use App\Models\Vehicle;
use App\Services\AlertService;
use App\Services\BlockchainService;
use App\Services\Web3\ContractAnchorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MileageRecordController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $records = MileageRecord::query()
            ->with(['vehicle:id,registration_number,technical_id', 'driver:id,name'])
            ->when($request->vehicle_id, fn ($q, $id) => $q->where('vehicle_id', $id))
            ->latest('recorded_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($records);
    }

    public function store(
        Request $request,
        BlockchainService $blockchain,
        ContractAnchorService $anchor,
        AlertService $alerts,
    ): JsonResponse {

        $data = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'mileage' => ['required', 'integer', 'min:0'],
            'recorded_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'certify' => ['nullable', 'boolean'],
        ]);

        $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);

        if ($data['mileage'] < $vehicle->current_mileage) {
            return response()->json([
                'message' => 'Le kilométrage ne peut pas être inférieur au relevé actuel (anti-fraude).',
                'current_mileage' => $vehicle->current_mileage,
            ], 422);
        }

        $record = DB::transaction(function () use ($data, $vehicle, $request, $blockchain, $anchor) {
            $previous = $vehicle->current_mileage;

            $record = MileageRecord::query()->create([
                'vehicle_id' => $vehicle->id,
                'driver_id' => $request->user()->id,
                'mileage' => $data['mileage'],
                'previous_mileage' => $previous,
                'recorded_at' => $data['recorded_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'status' => 'recorded',
                'is_certified' => false,
            ]);

            $vehicle->update(['current_mileage' => $data['mileage']]);

            if ($data['certify'] ?? true) {
                $tx = $blockchain->prepareProof($vehicle, 'mileage_record', [
                    'mileage' => $record->mileage,
                    'previous_mileage' => $record->previous_mileage,
                    'recorded_at' => $record->recorded_at->toIso8601String(),
                ], $request->user());

                $tx = $anchor->anchor($tx);

                $record->update([
                    'blockchain_tx_hash' => $tx->tx_hash,
                    'is_certified' => true,
                    'status' => 'certified',
                ]);
            }

            return $record;
        });

        $alerts->checkVehicle($vehicle->fresh());

        return response()->json([
            'message' => 'Relevé kilométrique enregistré.',
            'record' => $record->fresh(['vehicle', 'driver']),
        ], 201);
    }
}
