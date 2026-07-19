<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $fillable = [
        'vehicle_id',
        'uploaded_by',
        'type',
        'title',
        'file_path',
        'original_name',
        'file_hash',
        'ipfs_cid',
        'mime_type',
        'size',
        'is_public',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'is_public' => 'boolean',
            'expires_at' => 'date',
            'size' => 'integer',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
