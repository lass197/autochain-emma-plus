<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('garage_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->default('revision');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('parts_changed')->nullable();
            $table->unsignedBigInteger('mileage_at_service');
            $table->decimal('cost', 12, 2)->default(0);
            $table->timestamp('performed_at');
            $table->string('blockchain_tx_hash')->nullable();
            $table->boolean('is_certified')->default(false);
            $table->string('status')->default('certified');
            $table->timestamps();

            $table->index(['vehicle_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
