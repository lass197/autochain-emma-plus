<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Vehicle;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $documents = Document::query()
            ->with(['vehicle:id,registration_number,technical_id', 'uploader:id,name'])
            ->when($request->vehicle_id, fn ($q, $id) => $q->where('vehicle_id', $id))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($documents);
    }

    public function store(Request $request, DocumentService $documents): JsonResponse
    {
        $maxKb = config('autochain.documents.max_size_kb', 10240);

        $data = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'file' => ['required', 'file', "max:{$maxKb}"],
            'type' => ['required', Rule::enum(DocumentType::class)],
            'title' => ['nullable', 'string', 'max:255'],
            'ipfs_cid' => ['nullable', 'string', 'max:255'],
            'is_public' => ['boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
        $document = $documents->store($vehicle, $request->user(), $request->file('file'), $data);

        return response()->json([
            'message' => 'Document stocké de façon sécurisée.',
            'document' => $document,
        ], 201);
    }

    public function show(Document $document): JsonResponse
    {
        return response()->json(['document' => $document->load(['vehicle', 'uploader:id,name'])]);
    }

    public function verify(Document $document, DocumentService $documents): JsonResponse
    {
        $valid = $documents->verifyIntegrity($document);

        return response()->json([
            'document_id' => $document->id,
            'file_hash' => $document->file_hash,
            'integrity_ok' => $valid,
            'message' => $valid
                ? 'Intégrité confirmée : le document n\'a pas été altéré.'
                : 'Alerte : le hash ne correspond pas, document potentiellement altéré.',
        ]);
    }

    public function download(Document $document): StreamedResponse|JsonResponse
    {
        $disk = config('autochain.documents.disk', 'local');

        if (! Storage::disk($disk)->exists($document->file_path)) {
            return response()->json(['message' => 'Fichier introuvable.'], 404);
        }

        return Storage::disk($disk)->download($document->file_path, $document->original_name);
    }

    public function destroy(Document $document, DocumentService $documents): JsonResponse
    {
        $documents->delete($document);

        return response()->json(['message' => 'Document supprimé.']);
    }
}
