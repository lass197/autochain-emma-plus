<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    public function store(Vehicle $vehicle, User $uploader, UploadedFile $file, array $data): Document
    {
        $disk = config('autochain.documents.disk', 'local');
        $basePath = config('autochain.documents.path', 'documents/vehicules');
        $directory = "{$basePath}/{$vehicle->technical_id}";
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $filename, $disk);
        $absolutePath = Storage::disk($disk)->path($path);
        $hash = hash_file('sha256', $absolutePath);

        return Document::query()->create([
            'vehicle_id' => $vehicle->id,
            'uploaded_by' => $uploader->id,
            'type' => $data['type'] ?? DocumentType::Autre->value,
            'title' => $data['title'] ?? $file->getClientOriginalName(),
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_hash' => $hash,
            'ipfs_cid' => $data['ipfs_cid'] ?? null,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'is_public' => (bool) ($data['is_public'] ?? false),
            'expires_at' => $data['expires_at'] ?? null,
        ]);
    }

    public function verifyIntegrity(Document $document): bool
    {
        $disk = config('autochain.documents.disk', 'local');

        if (! Storage::disk($disk)->exists($document->file_path)) {
            return false;
        }

        $absolutePath = Storage::disk($disk)->path($document->file_path);

        return hash_equals($document->file_hash, hash_file('sha256', $absolutePath));
    }

    public function delete(Document $document): void
    {
        $disk = config('autochain.documents.disk', 'local');
        Storage::disk($disk)->delete($document->file_path);
        $document->delete();
    }
}
