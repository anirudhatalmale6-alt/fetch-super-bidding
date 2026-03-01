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
        // Add final cost and inspection fields to requests table
        Schema::table('requests', function (Blueprint $table) {
            // Inspection status workflow
            $table->enum('inspection_status', [
                'not_required',
                'awaiting_inspection',
                'inspection_in_progress',
                'awaiting_user_approval',
                'approved_by_user',
                'rejected_by_user',
                'rerouting_requested',
                'completed'
            ])->default('not_required')->after('status');

            // Approval status
            $table->enum('approval_status', [
                'pending',
                'approved',
                'rejected',
                'expired',
                'cancelled'
            ])->nullable()->after('inspection_status');

            // Final cost fields (after physical inspection)
            $table->decimal('final_transportation_fee', 12, 2)->nullable()->after('interstate_transport_fee');
            $table->decimal('final_insurance_fee', 12, 2)->nullable()->after('final_transportation_fee');
            $table->decimal('final_total_amount', 12, 2)->nullable()->after('final_insurance_fee');
            
            // Pricing comparison fields
            $table->decimal('initial_bid_amount', 12, 2)->nullable()->after('final_total_amount');
            $table->decimal('price_difference', 12, 2)->nullable()->after('initial_bid_amount');
            $table->decimal('price_difference_percent', 5, 2)->nullable()->after('price_difference');
            
            // Company remarks for final cost
            $table->text('final_cost_remarks')->nullable()->after('price_difference_percent');
            
            // Timestamps for approval workflow
            $table->timestamp('inspection_started_at')->nullable()->after('final_cost_remarks');
            $table->timestamp('inspection_completed_at')->nullable()->after('inspection_started_at');
            $table->timestamp('final_cost_submitted_at')->nullable()->after('inspection_completed_at');
            $table->timestamp('user_approval_deadline')->nullable()->after('final_cost_submitted_at');
            $table->timestamp('user_approved_at')->nullable()->after('user_approval_deadline');
            $table->timestamp('user_rejected_at')->nullable()->after('user_approved_at');
            $table->timestamp('rerouting_requested_at')->nullable()->after('user_rejected_at');
            
            // User who performed the approval/rejection
            $table->foreignId('approved_by_user_id')->nullable()->after('rerouting_requested_at');
            
            // Re-routing tracking
            $table->integer('rerouting_attempt_count')->default(0)->after('approved_by_user_id');
            $table->foreignId('previous_company_id')->nullable()->after('rerouting_attempt_count');
            
            // Indexes for performance
            $table->index(['inspection_status', 'approval_status']);
            $table->index('user_approval_deadline');
            $table->index(['delivery_mode', 'inspection_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropForeign(['approved_by_user_id']);
            $table->dropForeign(['previous_company_id']);
            
            $table->dropIndex(['inspection_status', 'approval_status']);
            $table->dropIndex('user_approval_deadline');
            $table->dropIndex(['delivery_mode', 'inspection_status']);
            
            $table->dropColumn([
                'inspection_status',
                'approval_status',
                'final_transportation_fee',
                'final_insurance_fee',
                'final_total_amount',
                'initial_bid_amount',
                'price_difference',
                'price_difference_percent',
                'final_cost_remarks',
                'inspection_started_at',
                'inspection_completed_at',
                'final_cost_submitted_at',
                'user_approval_deadline',
                'user_approved_at',
                'user_rejected_at',
                'rerouting_requested_at',
                'approved_by_user_id',
                'rerouting_attempt_count',
                'previous_company_id',
            ]);
        });
    }
};

