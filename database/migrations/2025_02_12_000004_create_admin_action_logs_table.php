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
        Schema::create('admin_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id');
            
            // Action target
            $table->uuid('target_id')->nullable(); // request_id, company_id, etc.
            $table->string('target_type')->nullable(); // request, company, route, hub
            
            // Action details
            $table->string('action'); // stage_override, company_blacklist, fee_adjustment, etc.
            $table->string('action_category'); // order, company, route, hub, payment
            
            // Before/After for audit
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            
            // Change summary
            $table->text('description');
            $table->text('reason')->nullable(); // Admin-provided reason
            
            // IP and user agent for security
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            // Risk assessment
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->boolean('requires_review')->default(false);
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['admin_id', 'created_at']);
            $table->index(['target_type', 'target_id']);
            $table->index('action');
            $table->index('action_category');
            $table->index('risk_level');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_action_logs');
    }
};

