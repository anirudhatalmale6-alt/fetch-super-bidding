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
        Schema::create('rejected_providers', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id');
            $table->morphs('provider'); // Can be TruckingCompany or Driver
            
            // Rejection details
            $table->enum('rejection_type', ['bid_rejected', 'user_declined', 'timeout', 'performance_issue', 'admin_removed'])->default('bid_rejected');
            $table->text('rejection_reason')->nullable();
            
            // Who rejected
            $table->enum('rejected_by_type', ['user', 'system', 'admin'])->default('user');
            $table->foreignId('rejected_by_id')->nullable();
            
            // Stage at which rejection occurred
            $table->string('stage_code_at_rejection')->nullable();
            $table->integer('rerouting_attempt_number')->default(0);
            
            // Timestamps
            $table->timestamp('rejected_at');
            $table->timestamps();
            
            // Soft delete for audit trail
            $table->softDeletes();
            
            // Indexes
            $table->index(['request_id', 'provider_type', 'provider_id']);
            $table->index(['provider_type', 'provider_id', 'rejected_at']);
            $table->index('rejection_type');
            $table->index('rejected_at');
            
            // Prevent duplicate entries
            $table->unique(['request_id', 'provider_type', 'provider_id', 'rerouting_attempt_number'], 'unique_rejection_per_attempt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rejected_providers');
    }
};
