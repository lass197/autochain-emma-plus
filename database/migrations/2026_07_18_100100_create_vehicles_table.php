<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->uuid('technical_id')->unique();
            $table->string('vin', 17)->unique();
            $table->string('registration_number')->unique();
            $table->string('brand');
            $table->string('model');
            $table->unsignedSmallInteger('year');
            $table->string('color')->nullable();
            $table->unsignedBigInteger('current_mileage')->default(0);
            $table->string('status')->default('disponible');
            $table->string('fuel_type')->default('essence');
            $table->decimal('tank_capacity', 8, 2)->nullable();
            $table->date('insurance_expires_at')->nullable();
            $table->date('technical_control_expires_at')->nullable();
            $table->unsignedInteger('next_service_mileage')->nullable();
            $table->date('next_service_at')->nullable();
            $table->string('blockchain_hash')->nullable();
            $table->string('ipfs_cid')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
