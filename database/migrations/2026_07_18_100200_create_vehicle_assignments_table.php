<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('mileage_at_start')->default(0);
            $table->unsignedBigInteger('mileage_at_end')->nullable();
            $table->boolean('driver_acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status']);
            $table->index(['driver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_assignments');
    }
};
