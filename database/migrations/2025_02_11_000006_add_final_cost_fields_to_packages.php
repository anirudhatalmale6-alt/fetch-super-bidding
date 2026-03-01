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
        // Add final cost fields to request_packages table
        Schema::table('request_packages', function (Blueprint $table) {
            // Final measured values (company input after inspection)
            $table->decimal('final_weight_kg', 8, 2)->nullable()->after('actual_declared_value');
            $table->decimal('final_length_cm', 8, 2)->nullable()->after('final_weight_kg');
            $table->decimal('final_width_cm', 8, 2)->nullable()->after('final_length_cm');
            $table->decimal('final_height_cm', 8, 2)->nullable()->after('final_width_cm');
            $table->decimal('final_declared_value', 12, 2)->nullable()->after('final_height_cm');
            
            // Final chargeable weight calculated after inspection
            $table->decimal('final_volumetric_weight_kg', 8, 2)->nullable()->after('final_declared_value');
            $table->decimal('final_chargeable_weight_kg', 8, 2)->nullable()->after('final_volumetric_weight_kg');
            
            // Discrepancy tracking
            $table->decimal('weight_discrepancy_percent', 5, 2)->nullable()->after('final_chargeable_weight_kg');
            $table->text('discrepancy_notes')->nullable()->after('weight_discrepancy_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_packages', function (Blueprint $table) {
            $table->dropColumn([
                'final_weight_kg',
                'final_length_cm',
                'final_width_cm',
                'final_height_cm',
                'final_declared_value',
                'final_volumetric_weight_kg',
                'final_chargeable_weight_kg',
                'weight_discrepancy_percent',
                'discrepancy_notes',
            ]);
        });
    }
};
