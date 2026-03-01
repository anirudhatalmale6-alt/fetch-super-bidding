<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInterstateFreightTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. TRUCKING COMPANIES with dimensional pricing config
        Schema::create('trucking_companies', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('slug')->unique();
            $table->string('registration_number')->unique();
            $table->string('email');
            $table->string('phone');
            $table->foreignId('user_id'); // Login account
            $table->string('logo')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
            $table->decimal('commission_rate', 5, 2)->default(15.00);
            $table->integer('fleet_size')->default(0);
            $table->json('service_types')->nullable(); // ['general', 'perishable', 'hazmat']
            $table->json('operating_states')->nullable();
            $table->decimal('rating', 2, 1)->default(5.0);
            
            // Dimensional pricing defaults
            $table->integer('default_volumetric_divisor')->default(5000); // Standard: 5000, Air: 6000
            $table->decimal('default_minimum_charge', 10, 2)->default(5000.00);
            $table->decimal('max_weight_per_package', 8, 2)->default(1000.00); // kg
            $table->json('max_dimensions_cm')->nullable(); // {"length":200,"width":150,"height":150}
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('status');
        });

        // 2. TRUCKING HUBS
        Schema::create('trucking_hubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trucking_company_id')->onDelete('cascade');
            $table->string('hub_name');
            $table->string('hub_code')->unique();
            $table->enum('hub_type', ['origin', 'destination', 'both', 'transit'])->default('both');
            $table->text('address');
            $table->string('city');
            $table->string('state');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('phone');
            $table->json('operating_hours')->nullable();
            $table->integer('daily_capacity')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['city', 'state']);
            $table->index(['latitude', 'longitude']);
        });

        // 3. SUPPORTED ROUTES with per-route pricing
        Schema::create('supported_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trucking_company_id')->onDelete('cascade');
            $table->foreignId('origin_hub_id');
            $table->foreignId('destination_hub_id');
            $table->string('route_code')->unique();
            $table->decimal('distance_km', 10, 2);
            $table->integer('estimated_duration_hours');
            
            // Dimensional pricing per route
            $table->integer('volumetric_divisor')->default(5000); // Override company default
            $table->decimal('price_per_kg', 8, 2); // Price per chargeable kg
            $table->decimal('minimum_charge', 10, 2)->default(5000.00);
            $table->decimal('minimum_chargeable_weight', 8, 2)->default(10.00); // Min 10kg charge
            
            // Limits per route
            $table->decimal('max_weight_per_package', 8, 2)->nullable(); // Override company default
            $table->json('max_dimensions_cm')->nullable();
            
            // Surcharges
            $table->decimal('express_surcharge_percent', 5, 2)->default(50.00);
            $table->decimal('fragile_surcharge_percent', 5, 2)->default(20.00);
            $table->decimal('insurance_rate_percent', 5, 2)->default(1.00); // % of declared value
            
            // Capacity
            $table->integer('max_daily_capacity')->default(50);
            $table->json('departure_slots')->nullable();
            
            // SLA
            $table->integer('standard_sla_hours')->default(72);
            $table->integer('express_sla_hours')->default(48);
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['trucking_company_id', 'origin_hub_id', 'destination_hub_id']);
            $table->index(['origin_hub_id', 'destination_hub_id']);
            $table->index('is_active');
        });

        // 4. PACKAGE SPECIFICATIONS (separate table for flexible package handling)
        Schema::create('request_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->onDelete('cascade');
            
            // Package identification
            $table->string('package_number')->unique(); // PKG-12345-001
            $table->integer('package_index')->default(1); // 1, 2, 3 for multi-package orders
            
            // Physical specifications
            $table->string('description')->nullable();
            $table->decimal('actual_weight_kg', 8, 2);
            $table->decimal('length_cm', 8, 2);
            $table->decimal('width_cm', 8, 2);
            $table->decimal('height_cm', 8, 2);
            $table->integer('quantity')->default(1);
            
            // Computed values
            $table->decimal('volumetric_weight_kg', 8, 2);
            $table->decimal('chargeable_weight_kg', 8, 2);
            $table->integer('volumetric_divisor_used')->default(5000);
            
            // Package value
            $table->decimal('declared_value', 12, 2)->nullable();
            $table->boolean('is_fragile')->default(false);
            $table->boolean('requires_insurance')->default(false);
            
            // Special handling
            $table->json('special_instructions')->nullable();
            
            $table->timestamps();
            
            $table->index('request_id');
            $table->index('package_number');
        });

        // 5. REQUEST LEGS with dimensional pricing
        Schema::create('request_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->onDelete('cascade');
            $table->integer('leg_number');
            $table->enum('leg_type', [
                'local_pickup',
                'hub_dropoff',
                'interstate_transport',
                'hub_pickup',
                'local_delivery'
            ]);
            
            // Provider assignment
            $table->morphs('provider');
            $table->string('provider_name')->nullable();
            $table->string('provider_phone')->nullable();
            
            // Locations
            $table->json('pickup_location');
            $table->json('drop_location');
            
            // Weight and pricing details
            $table->decimal('total_actual_weight', 8, 2)->default(0);
            $table->decimal('total_volumetric_weight', 8, 2)->default(0);
            $table->decimal('total_chargeable_weight', 8, 2)->default(0);
            
            // Financial breakdown
            $table->decimal('base_fare', 10, 2)->default(0);
            $table->decimal('minimum_charge_applied', 10, 2)->default(0);
            $table->decimal('express_surcharge', 10, 2)->default(0);
            $table->decimal('fragile_surcharge', 10, 2)->default(0);
            $table->decimal('insurance_charge', 10, 2)->default(0);
            $table->decimal('other_surcharges', 10, 2)->default(0);
            $table->decimal('final_fare', 10, 2)->default(0);
            $table->decimal('provider_earnings', 10, 2)->default(0);
            
            // Pricing reference
            $table->foreignId('supported_route_id')->nullable();
            $table->json('pricing_breakdown')->nullable(); // Detailed calculation steps
            
            // Status
            $table->enum('status', [
                'pending',
                'accepted',
                'driver_arrived',
                'picked_up',
                'in_transit',
                'completed',
                'cancelled'
            ])->default('pending');
            
            // Timestamps
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Tracking
            $table->decimal('current_lat', 10, 8)->nullable();
            $table->decimal('current_lng', 11, 8)->nullable();
            
            // Proof
            $table->json('pickup_proof')->nullable();
            $table->json('delivery_proof')->nullable();
            
            $table->timestamps();
            
            $table->unique(['request_id', 'leg_number']);
            $table->index(['provider_type', 'provider_id', 'status']);
        });

        // 6. HUB INVENTORY
        Schema::create('hub_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hub_id');
            $table->foreignId('request_id');
            $table->foreignId('request_leg_id');
            $table->string('storage_location')->nullable();
            $table->enum('status', ['received', 'stored', 'ready', 'dispatched'])->default('received');
            $table->timestamp('received_at');
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('received_by')->nullable();
            $table->foreignId('dispatched_by')->nullable();
            $table->timestamps();
            
            $table->index(['hub_id', 'status']);
            $table->index('request_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hub_inventory');
        Schema::dropIfExists('request_legs');
        Schema::dropIfExists('request_packages');
        Schema::dropIfExists('supported_routes');
        Schema::dropIfExists('trucking_hubs');
        Schema::dropIfExists('trucking_companies');
    }
}

