<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_stages', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id');
            $table->foreignId('request_leg_id')->nullable()->onDelete('set null');
            
            // Stage identification
            $table->integer('stage_number'); // 1-11 as per specification
            $table->string('stage_code'); // pending_pickup, picked_up, arrived_trucking_hub, etc.
            $table->string('stage_name'); // Human-readable name
            
            // Stage status
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled', 'skipped'])->default('pending');
            
            // Timestamps for each stage
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_minutes')->nullable(); // Calculated duration
            
            // Who triggered the stage
            $table->enum('triggered_by_type', ['system', 'user', 'company', 'driver', 'admin'])->default('system');
            $table->foreignId('triggered_by_id')->nullable();
            
            // Payment gating
            $table->boolean('requires_payment')->default(false);
            $table->foreignId('payment_id')->nullable();
            $table->timestamp('payment_completed_at')->nullable();
            
            // Stage metadata
            $table->json('metadata')->nullable(); // Additional stage-specific data
            $table->text('notes')->nullable();
            
            // For rerouting tracking
            $table->integer('rerouting_attempt')->default(0);
            $table->foreignId('previous_stage_id')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['request_id', 'stage_number']);
            $table->index(['request_id', 'stage_code']);
            $table->index(['request_id', 'status']);
            $table->index('started_at');
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_stages');
    }
};

