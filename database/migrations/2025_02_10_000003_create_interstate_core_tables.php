<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REPLACES: 2025_02_11_000001_create_interstate_freight_tables
 *
 * Creates all interstate core tables BEFORE interstate_bids.
 * supported_routes is handled by 2025_02_14_000001_create_supported_routes_table.
 * All creates are guarded with Schema::hasTable() to be idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. TRUCKING COMPANIES
        if (!Schema::hasTable('trucking_companies')) {
            Schema::create('trucking_companies', function (Blueprint $table) {
                $table->id();
                $table->string('company_name');
                $table->string('slug')->unique();
                $table->string('registration_number')->unique();
                $table->string('email');
                $table->string('phone')->nullable();
                $table->string('address')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('logo')->nullable();
                $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
                $table->decimal('commission_rate', 5, 2)->default(15.00);
                $table->integer('fleet_size')->default(0);
                $table->json('service_types')->nullable();
                $table->json('operating_states')->nullable();
                $table->decimal('rating', 3, 1)->default(5.0);

                // Dimensional pricing defaults
                $table->integer('default_volumetric_divisor')->default(5000);
                $table->decimal('default_minimum_charge', 10, 2)->default(5000.00);
                $table->decimal('default_price_per_kg', 10, 2)->default(1000.00);
                $table->decimal('insurance_rate_percent', 6, 4)->default(1.00);
                $table->decimal('max_weight_per_package', 8, 2)->default(1000.00);
                $table->json('max_dimensions_cm')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index('status');
            });
        }

        // 2. TRUCKING HUBS
        if (!Schema::hasTable('trucking_hubs')) {
            Schema::create('trucking_hubs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trucking_company_id')->index();
                $table->string('hub_name');
                $table->string('name')->nullable();
                $table->string('hub_code')->unique()->nullable();
                $table->enum('hub_type', ['origin', 'destination', 'both', 'transit'])->default('both');
                $table->text('address')->nullable();
                $table->string('city');
                $table->string('state')->nullable();
                $table->string('country')->nullable()->default('Nigeria');
                $table->string('postal_code')->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->json('operating_hours')->nullable();
                $table->integer('daily_capacity')->default(100);
                $table->boolean('is_primary')->default(false);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['city', 'state']);
                $table->index('is_active');
            });
        }

        // 3. REQUEST PACKAGES
        if (!Schema::hasTable('request_packages')) {
            Schema::create('request_packages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id')->index();

                $table->string('package_number')->unique()->nullable();
                $table->integer('package_index')->default(1);

                $table->string('description')->nullable();
                $table->decimal('actual_weight_kg', 8, 2);
                $table->decimal('length_cm', 8, 2)->nullable();
                $table->decimal('width_cm', 8, 2)->nullable();
                $table->decimal('height_cm', 8, 2)->nullable();
                $table->integer('quantity')->default(1);

                $table->decimal('volumetric_weight_kg', 8, 2)->nullable();
                $table->decimal('chargeable_weight_kg', 8, 2)->nullable();
                $table->integer('volumetric_divisor_used')->default(5000);

                $table->decimal('declared_value', 12, 2)->nullable();
                $table->boolean('is_fragile')->default(false);
                $table->boolean('requires_insurance')->default(false);

                // Final cost fields
                $table->decimal('final_chargeable_weight_kg', 8, 2)->nullable();
                $table->decimal('final_base_price', 12, 2)->nullable();
                $table->decimal('final_insurance_fee', 12, 2)->nullable();
                $table->decimal('final_fragile_surcharge', 12, 2)->nullable();
                $table->decimal('final_total_price', 12, 2)->nullable();
                $table->json('final_pricing_breakdown')->nullable();
                $table->timestamp('final_price_set_at')->nullable();

                $table->json('special_instructions')->nullable();

                $table->timestamps();

                // Note: request_id index already added via ->index() on column above
                $table->index('package_number');
            });
        }

        // 4. REQUEST LEGS
        if (!Schema::hasTable('request_legs')) {
            Schema::create('request_legs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id');
                $table->integer('leg_number');
                $table->enum('leg_type', [
                    'local_pickup',
                    'hub_dropoff',
                    'interstate_transport',
                    'hub_pickup',
                    'local_delivery',
                ]);

                // Provider (polymorphic)
                $table->string('provider_type')->nullable();
                $table->unsignedBigInteger('provider_id')->nullable();
                $table->string('provider_name')->nullable();
                $table->string('provider_phone')->nullable();

                // Hub references (nullable FKs, set later)
                $table->unsignedBigInteger('origin_hub_id')->nullable();
                $table->unsignedBigInteger('destination_hub_id')->nullable();
                $table->unsignedBigInteger('supported_route_id')->nullable();

                // Locations
                $table->json('pickup_location')->nullable();
                $table->json('drop_location')->nullable();

                // Weight
                $table->decimal('total_actual_weight', 8, 2)->default(0);
                $table->decimal('total_volumetric_weight', 8, 2)->default(0);
                $table->decimal('total_chargeable_weight', 8, 2)->default(0);

                // Financial breakdown
                $table->decimal('base_fare', 12, 2)->default(0);
                $table->decimal('provider_base_price', 12, 2)->default(0);
                $table->decimal('minimum_charge_applied', 12, 2)->default(0);
                $table->decimal('express_surcharge', 12, 2)->default(0);
                $table->decimal('fragile_surcharge', 12, 2)->default(0);
                $table->decimal('insurance_fee', 12, 2)->default(0);
                $table->decimal('insurance_charge', 12, 2)->default(0);
                $table->decimal('other_surcharges', 12, 2)->default(0);
                $table->decimal('final_fare', 12, 2)->default(0);
                $table->decimal('total_leg_price', 12, 2)->default(0);
                $table->decimal('platform_commission', 12, 2)->default(0);
                $table->decimal('provider_earnings', 12, 2)->default(0);
                $table->decimal('provider_payout_amount', 12, 2)->default(0);

                // Pricing reference
                $table->json('pricing_breakdown')->nullable();
                $table->json('handoff_details')->nullable();

                // Status
                $table->enum('status', [
                    'pending',
                    'accepted',
                    'en_route_pickup',
                    'picked_up',
                    'arrived_at_hub',
                    'collected_from_hub',
                    'in_transit',
                    'en_route_delivery',
                    'completed',
                    'cancelled',
                ])->default('pending');

                // Timestamps
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('picked_up_at')->nullable();
                $table->timestamp('arrived_at_hub_at')->nullable();
                $table->timestamp('collected_at')->nullable();
                $table->timestamp('completed_at')->nullable();

                // Live tracking
                $table->decimal('last_known_lat', 10, 7)->nullable();
                $table->decimal('last_known_lng', 10, 7)->nullable();
                $table->timestamp('last_location_updated_at')->nullable();

                // Proof
                $table->json('pickup_proof')->nullable();
                $table->json('delivery_proof')->nullable();

                $table->timestamps();

                $table->index('request_id');
                $table->unique(['request_id', 'leg_number']);
                $table->index(['provider_type', 'provider_id', 'status']);
                $table->index('status');
            });
        }

        // 5. HUB INVENTORY
        if (!Schema::hasTable('hub_inventory')) {
            Schema::create('hub_inventory', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('hub_id')->index();
                $table->unsignedBigInteger('request_id')->index();
                $table->unsignedBigInteger('request_leg_id')->index();
                $table->string('storage_location')->nullable();
                $table->enum('status', ['received', 'stored', 'ready', 'dispatched'])->default('received');
                $table->timestamp('received_at')->nullable();
                $table->timestamp('dispatched_at')->nullable();
                $table->unsignedBigInteger('received_by')->nullable()->index();
                $table->unsignedBigInteger('dispatched_by')->nullable()->index();
                $table->timestamps();

                $table->index(['hub_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_inventory');
        Schema::dropIfExists('request_legs');
        Schema::dropIfExists('request_packages');
        Schema::dropIfExists('trucking_hubs');
        Schema::dropIfExists('trucking_companies');
    }
};
