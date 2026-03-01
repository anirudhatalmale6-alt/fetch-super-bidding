<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_packages', function (Blueprint $table) {
            // User estimated values (for initial bidding)
            if (!Schema::hasColumn('request_packages', 'estimated_weight_kg')) {
                $table->decimal('estimated_weight_kg', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('request_packages', 'estimated_length_cm')) {
                $table->decimal('estimated_length_cm', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('request_packages', 'estimated_width_cm')) {
                $table->decimal('estimated_width_cm', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('request_packages', 'estimated_height_cm')) {
                $table->decimal('estimated_height_cm', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('request_packages', 'estimated_declared_value')) {
                $table->decimal('estimated_declared_value', 12, 2)->nullable();
            }
            // actual_weight_kg, length_cm, width_cm, height_cm already created
            // by create_interstate_core_tables migration — skip adding them
            if (!Schema::hasColumn('request_packages', 'actual_length_cm')) {
                $table->decimal('actual_length_cm', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('request_packages', 'actual_width_cm')) {
                $table->decimal('actual_width_cm', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('request_packages', 'actual_height_cm')) {
                $table->decimal('actual_height_cm', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('request_packages', 'actual_declared_value')) {
                $table->decimal('actual_declared_value', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('request_packages', 'weight_confirmed')) {
                $table->boolean('weight_confirmed')->default(false);
            }
            if (!Schema::hasColumn('request_packages', 'weight_discrepancy')) {
                $table->decimal('weight_discrepancy', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('request_packages', 'discrepancy_reason')) {
                $table->text('discrepancy_reason')->nullable();
            }
            if (!Schema::hasColumn('request_packages', 'adjustment_approved')) {
                $table->boolean('adjustment_approved')->nullable();
            }
            if (!Schema::hasColumn('request_packages', 'adjustment_approved_at')) {
                $table->timestamp('adjustment_approved_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('request_packages', function (Blueprint $table) {
            $table->dropColumn([
                'estimated_weight_kg', 'estimated_length_cm', 'estimated_width_cm',
                'estimated_height_cm', 'estimated_declared_value',
                'actual_length_cm', 'actual_width_cm', 'actual_height_cm', 'actual_declared_value',
                'weight_confirmed', 'weight_discrepancy', 'discrepancy_reason',
                'adjustment_approved', 'adjustment_approved_at',
            ]);
        });
    }
};
