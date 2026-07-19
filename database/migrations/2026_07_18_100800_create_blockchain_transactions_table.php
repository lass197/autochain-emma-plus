<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blockchain_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->string('action_type');
            $table->string('payload_hash', 64);
            $table->json('payload')->nullable();
            $table->string('tx_hash')->nullable()->unique();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('signed_by_admin')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('signed_by_buyer')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('admin_signed_at')->nullable();
            $table->timestamp('buyer_signed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'action_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blockchain_transactions');
    }
};
