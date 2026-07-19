<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('type')->default('autre');
            $table->string('title');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('file_hash', 64);
            $table->string('ipfs_cid')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('is_public')->default(false);
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
