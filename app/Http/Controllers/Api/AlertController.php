<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Services\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $alerts = Alert::query()
            ->with('vehicle:id,registration_number,brand,model')
            ->where('user_id', $request->user()->id)
            ->when($request->boolean('unresolved_only', false), fn ($q) => $q->where('is_resolved', false))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($alerts);
    }

    public function generate(AlertService $alertService): JsonResponse
    {
        $created = $alertService->generateForFleet();

        return response()->json([
            'message' => 'Moteur d\'alertes exécuté.',
            'generated_count' => $created->count(),
        ]);
    }

    public function markRead(Alert $alert, Request $request): JsonResponse
    {
        if ($alert->user_id !== $request->user()->id && ! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $alert->update(['is_read' => true]);

        return response()->json(['message' => 'Alerte marquée comme lue.', 'alert' => $alert]);
    }

    public function resolve(Alert $alert, Request $request): JsonResponse
    {
        if ($alert->user_id !== $request->user()->id && ! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $alert->update([
            'is_resolved' => true,
            'is_read' => true,
            'resolved_at' => now(),
        ]);

        return response()->json(['message' => 'Alerte résolue.', 'alert' => $alert]);
    }
}
