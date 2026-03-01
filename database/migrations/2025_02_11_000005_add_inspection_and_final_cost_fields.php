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
            // Inspection status
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
            $table->decimal('final_total_amount', 12, 2)->nullable()->after('approval_status');
            $table->decimal('final_transportation_fee', 10, 2)->nullable();
            $table->decimal('final_insurance_fee', 10, 2)->nullable();
            $table->decimal('final_tax_amount', 10, 2)->nullable();
            $table->text('final_cost_notes')->nullable();
            $table->timestamp('final_cost_submitted_at')->nullable();
            $table->foreignId('final_cost_submitted_by')->nullable();
            
            // Initial bid vs final cost comparison
            $table->decimal('price_difference', 10, 2)->nullable();
            $table->decimal('price_difference_percent', 5, 2)->nullable();
            
            // Approval tracking
            $table->timestamp('user_approved_at')->nullable();
            $table->timestamp('approval_expires_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropForeign(['final_cost_submitted_by']);
            $table->dropForeign(['approved_by_user_id']);
            
            $table->dropColumn([
                'inspection_status',
                'approval_status',
                'final_total_amount',
                'final_transportation_fee',
                'final_insurance_fee',
                'final_tax_amount',
                'final_cost_notes',
                'final_cost_submitted_at',
                'final_cost_submitted_by',
                'price_difference',
                'price_difference_percent',
                'user_approved_at',
                'approval_expires_at',
                'approved_by_user_id',
            ]);
        });
    }
};

