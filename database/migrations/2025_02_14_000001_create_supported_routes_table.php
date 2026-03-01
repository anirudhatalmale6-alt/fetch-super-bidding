<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supported_routes', function (Blueprint $table) {
            $table->id();

            // Company that operates this route
            $table->foreignId('trucking_company_id')
                  
                  ->onDelete('cascade');

            // Hubs
            $table->foreignId('origin_hub_id')
                  
                  ->onDelete('restrict');

            $table->foreignId('destination_hub_id')
                  
                  ->onDelete('restrict');

            // Human-readable city/state names (redundant but fast for search)
            $table->string('origin_city', 100);
            $table->string('origin_state', 100)->nullable();
            $table->string('destination_city', 100);
            $table->string('destination_state', 100)->nullable();

            // Distance & timing
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->unsignedTinyInteger('estimated_days')->default(1);

            // Pricing
            $table->decimal('base_rate_per_kg', 12, 4)->default(0);
            $table->decimal('minimum_charge', 12, 2)->default(0);
            $table->decimal('express_multiplier', 6, 4)->default(1.5);
            $table->decimal('fragile_surcharge_percent', 6, 2)->default(10.00);
            $table->decimal('insurance_rate_percent', 6, 4)->default(0.5);

            // Limits
            $table->decimal('max_weight_kg', 10, 2)->nullable();
            $table->decimal('max_length_cm', 10, 2)->nullable();
            $table->decimal('max_width_cm', 10, 2)->nullable();
            $table->decimal('max_height_cm', 10, 2)->nullable();
            $table->unsignedSmallInteger('volumetric_divisor')->default(5000);

            // Commission
            $table->decimal('commission_rate', 6, 2)->default(10.00);

            // Flags
            $table->boolean('is_active')->default(true);
            $table->boolean('is_express_available')->default(false);

            // Meta
            $table->text('schedule_info')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('trucking_company_id');
            $table->index('origin_hub_id');
            $table->index('destination_hub_id');
            $table->index(['origin_city', 'destination_city']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supported_routes');
    }
};

