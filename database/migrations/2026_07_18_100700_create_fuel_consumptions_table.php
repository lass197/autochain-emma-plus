<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('liters', 8, 2);
            $table->decimal('cost', 12, 2)->default(0);
            $table->unsignedBigInteger('mileage_at_fill');
            $table->timestamp('filled_at');
            $table->string('station')->nullable();
            $table->decimal('consumption_l_per_100km', 8, 2)->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'filled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_consumptions');
    }
};
