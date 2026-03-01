<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Part 1: Add inspection / approval / final-cost fields to requests ──
        Schema::table('requests', function (Blueprint $table) {
            if (!Schema::hasColumn('requests', 'inspection_status')) {
                $table->enum('inspection_status', [
                    'not_required', 'awaiting_inspection', 'inspection_in_progress',
                    'inspection_completed', 'awaiting_user_approval', 'user_approved',
                    'user_rejected', 'rerouting_requested', 'cancelled',
                ])->default('not_required')->nullable();
            }
            if (!Schema::hasColumn('requests', 'final_transport_fee')) {
                $table->decimal('final_transport_fee', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('requests', 'final_insurance_fee')) {
                $table->decimal('final_insurance_fee', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('requests', 'final_total_amount')) {
                $table->decimal('final_total_amount', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('requests', 'final_cost_submitted_at')) {
                $table->timestamp('final_cost_submitted_at')->nullable();
            }
            if (!Schema::hasColumn('requests', 'user_approved_at')) {
                $table->timestamp('user_approved_at')->nullable();
            }
            if (!Schema::hasColumn('requests', 'user_rejected_at')) {
                $table->timestamp('user_rejected_at')->nullable();
            }
            if (!Schema::hasColumn('requests', 'user_rejection_reason')) {
                $table->text('user_rejection_reason')->nullable();
            }
            if (!Schema::hasColumn('requests', 'reroute_attempts')) {
                $table->integer('reroute_attempts')->default(0);
            }
            if (!Schema::hasColumn('requests', 'previous_trucking_company_id')) {
                $table->unsignedBigInteger('previous_trucking_company_id')->nullable()->index();
            }
            if (!Schema::hasColumn('requests', 'rerouted_at')) {
                $table->timestamp('rerouted_at')->nullable();
            }
            if (!Schema::hasColumn('requests', 'company_remarks')) {
                $table->text('company_remarks')->nullable();
            }
            if (!Schema::hasColumn('requests', 'final_estimated_delivery_hours')) {
                $table->integer('final_estimated_delivery_hours')->nullable();
            }
            if (!Schema::hasColumn('requests', 'estimated_delivery_at')) {
                $table->timestamp('estimated_delivery_at')->nullable();
            }
            if (!Schema::hasColumn('requests', 'approval_deadline_at')) {
                $table->timestamp('approval_deadline_at')->nullable();
            }
            if (!Schema::hasColumn('requests', 'inspected_at')) {
                $table->timestamp('inspected_at')->nullable();
            }
            if (!Schema::hasColumn('requests', 'inspected_by')) {
                $table->unsignedBigInteger('inspected_by')->nullable()->index();
            }
            if (!Schema::hasColumn('requests', 'inspection_remarks')) {
                $table->text('inspection_remarks')->nullable();
            }
        });

        // ── Part 2: Create tracking_updates table ─────────────────────────────
        if (!Schema::hasTable('tracking_updates')) {
            Schema::create('tracking_updates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id')->index();
                $table->string('previous_status', 100)->nullable();
                $table->string('new_status', 100)->index();
                $table->text('message')->nullable();
                $table->string('created_by_type', 50)->nullable()
                      ->comment('driver|trucking_company|admin|system');
                $table->unsignedBigInteger('created_by_id')->nullable();
                $table->decimal('latitude',  10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('location_address', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['created_by_type', 'created_by_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_updates');
        Schema::table('requests', function (Blueprint $table) {
            $cols = [
                'inspection_status', 'final_transport_fee', 'final_insurance_fee',
                'final_total_amount', 'final_cost_submitted_at', 'user_approved_at',
                'user_rejected_at', 'user_rejection_reason', 'reroute_attempts',
                'previous_trucking_company_id', 'rerouted_at', 'company_remarks',
                'final_estimated_delivery_hours', 'estimated_delivery_at',
                'approval_deadline_at', 'inspected_at', 'inspected_by', 'inspection_remarks',
            ];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('requests', $c));
            if ($existing) { $table->dropColumn(array_values($existing)); }
        });
    }
};
