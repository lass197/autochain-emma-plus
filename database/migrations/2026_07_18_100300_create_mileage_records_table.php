<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mileage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('mileage');
            $table->unsignedBigInteger('previous_mileage')->default(0);
            $table->timestamp('recorded_at');
            $table->string('blockchain_tx_hash')->nullable();
            $table->boolean('is_certified')->default(false);
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mileage_records');
    }
};
