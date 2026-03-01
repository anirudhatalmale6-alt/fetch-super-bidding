<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompanyTypeAndFeatures extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Add company_type to trucking_companies
        Schema::table('trucking_companies', function (Blueprint $table) {
            $table->enum('company_type', ['interstate_trucking', 'last_mile_dispatch', 'both'])
                ->default('interstate_trucking')
                ->after('status');
            $table->text('banner_media')->nullable()->after('company_type'); // JSON for banner images/videos
            $table->string('banner_title')->nullable()->after('banner_media');
            $table->text('banner_description')->nullable()->after('banner_title');
            $table->boolean('show_shop_section')->default(true)->after('banner_description');
        });

        // 2. Create company banners table for multiple banners
        Schema::create('company_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trucking_company_id')->onDelete('cascade');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('media_type'); // 'image' or 'video'
            $table->string('media_url');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('trucking_company_id');
            $table->index('is_active');
        });

        // 3. Add sender/recipient info to requests table
        Schema::table('requests', function (Blueprint $table) {
            $table->string('sender_phone')->nullable()->after('user_id');
            $table->string('sender_name')->nullable()->after('sender_phone');
            $table->string('recipient_phone')->nullable()->after('sender_name');
            $table->string('recipient_name')->nullable()->after('recipient_phone');
            $table->string('pickup_state')->nullable()->after('recipient_name');
            $table->string('destination_state')->nullable()->after('pickup_state');
            $table->enum('delivery_type', ['metro', 'interstate'])->default('metro')->after('destination_state');
        });

        // 4. Create goods status updates table
        Schema::create('goods_status_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_item_id')->onDelete('cascade');
            $table->foreignId('trucking_company_id')->onDelete('cascade');
            $table->string('status_type'); // 'location_update', 'departure', 'arrival', 'custom'
            $table->text('message');
            $table->json('location_data')->nullable(); // lat, lng, address
            $table->timestamp('update_timestamp');
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            
            $table->index('goods_item_id');
            $table->index('trucking_company_id');
            $table->index('update_timestamp');
        });

        // 5. Add pricing fields to trucking_goods_items (already created in previous migration)
        // Ensure fields exist - only add if not present
        if (!Schema::hasColumn('trucking_goods_items', 'transportation_service_fee')) {
            Schema::table('trucking_goods_items', function (Blueprint $table) {
                $table->decimal('transportation_service_fee', 10, 2)->nullable()->after('company_total_price');
                $table->decimal('insurance_fee', 10, 2)->nullable()->after('transportation_service_fee');
                $table->decimal('total_service_fee', 10, 2)->nullable()->after('insurance_fee');
                $table->json('fee_breakdown')->nullable()->after('total_service_fee');
                $table->timestamp('fee_added_at')->nullable()->after('fee_breakdown');
            });
        }

        // 6. Add notification tracking for fee updates
        Schema::create('goods_fee_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_item_id')->onDelete('cascade');
            $table->foreignId('user_id'); // User who was notified
            $table->decimal('transportation_fee', 10, 2);
            $table->decimal('insurance_fee', 10, 2);
            $table->timestamp('notified_at');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index('goods_item_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('goods_fee_notifications');
        Schema::dropIfExists('goods_status_updates');
        Schema::dropIfExists('company_banners');

        Schema::table('trucking_companies', function (Blueprint $table) {
            $table->dropColumn(['company_type', 'banner_media', 'banner_title', 'banner_description', 'show_shop_section']);
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['sender_phone', 'sender_name', 'recipient_phone', 'recipient_name', 'pickup_state', 'destination_state', 'delivery_type']);
        });

        Schema::table('trucking_goods_items', function (Blueprint $table) {
            $table->dropColumn(['transportation_service_fee', 'insurance_fee', 'total_service_fee', 'fee_breakdown', 'fee_added_at']);
        });
    }
}

