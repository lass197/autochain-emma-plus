<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $assignments = VehicleAssignment::query()
            ->with(['vehicle:id,registration_number,brand,model,status', 'driver:id,name,email', 'assigner:id,name'])
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->driver_id, fn ($q, $id) => $q->where('driver_id', $id))
            ->when(
                $request->user()->role === UserRole::Chauffeur,
                fn ($q) => $q->where('driver_id', $request->user()->id)
            )
            ->latest('started_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($assignments);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'driver_id' => ['required', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'started_at' => ['nullable', 'date'],
        ]);

        $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
        $driver = User::query()->findOrFail($data['driver_id']);

        if ($driver->role !== UserRole::Chauffeur) {
            return response()->json(['message' => 'L\'utilisateur sélectionné n\'est pas un chauffeur.'], 422);
        }

        if ($vehicle->activeAssignment) {
            return response()->json(['message' => 'Ce véhicule a déjà une affectation active.'], 422);
        }

        $assignment = DB::transaction(function () use ($data, $vehicle, $request) {
            VehicleAssignment::query()
                ->where('driver_id', $data['driver_id'])
                ->where('status', 'active')
                ->update([
                    'status' => 'closed',
                    'ended_at' => now(),
                    'mileage_at_end' => $vehicle->current_mileage,
                ]);

            $assignment = VehicleAssignment::query()->create([
                'vehicle_id' => $vehicle->id,
                'driver_id' => $data['driver_id'],
                'assigned_by' => $request->user()->id,
                'started_at' => $data['started_at'] ?? now(),
                'status' => 'active',
                'mileage_at_start' => $vehicle->current_mileage,
                'notes' => $data['notes'] ?? null,
            ]);

            $vehicle->update(['status' => VehicleStatus::Affecte]);

            return $assignment;
        });

        return response()->json([
            'message' => 'Véhicule affecté au chauffeur.',
            'assignment' => $assignment->load(['vehicle', 'driver']),
        ], 201);
    }

    public function acknowledge(Request $request, VehicleAssignment $assignment): JsonResponse
    {
        if ($request->user()->id !== $assignment->driver_id) {
            return response()->json(['message' => 'Seul le chauffeur affecté peut déclarer la prise en charge.'], 403);
        }

        if ($assignment->status !== 'active') {
            return response()->json(['message' => 'Affectation inactive.'], 422);
        }

        $assignment->update([
            'driver_acknowledged' => true,
            'acknowledged_at' => now(),
        ]);

        return response()->json([
            'message' => 'Prise en charge déclarée.',
            'assignment' => $assignment->fresh(),
        ]);
    }

    public function close(Request $request, VehicleAssignment $assignment): JsonResponse
    {
        $data = $request->validate([
            'mileage_at_end' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($assignment->status !== 'active') {
            return response()->json(['message' => 'Affectation déjà clôturée.'], 422);
        }

        DB::transaction(function () use ($assignment, $data) {
            $mileage = $data['mileage_at_end'] ?? $assignment->vehicle->current_mileage;

            $assignment->update([
                'status' => 'closed',
                'ended_at' => now(),
                'mileage_at_end' => $mileage,
                'notes' => $data['notes'] ?? $assignment->notes,
            ]);

            $assignment->vehicle->update(['status' => VehicleStatus::Disponible]);
        });

        return response()->json([
            'message' => 'Affectation clôturée.',
            'assignment' => $assignment->fresh(['vehicle', 'driver']),
        ]);
    }
}
