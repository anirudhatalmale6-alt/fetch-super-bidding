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
        Schema::create('tracking_updates', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id');
            $table->foreignId('request_package_id')->nullable();
            
            // Update content
            $table->text('message');
            $table->enum('update_type', [
                'status_change',
                'location_update',
                'inspection_note',
                'delay_notification',
                'general_update',
                'hub_arrival',
                'hub_departure',
                'checkpoint_passed'
            ])->default('general_update');
            
            // Location (optional)
            $table->string('location_name')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Media attachment
            $table->string('image_url')->nullable();
            $table->json('attachments')->nullable(); // Multiple images/documents
            
            // Who created this update
            $table->enum('created_by_type', ['system', 'trucking_company', 'driver', 'admin', 'user'])->default('system');
            $table->foreignId('created_by_id')->nullable();
            $table->string('created_by_name')->nullable(); // For display purposes
            
            // Status before and after (for status change updates)
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            
            // Notification tracking
            $table->timestamp('user_notified_at')->nullable();
            $table->boolean('is_customer_visible')->default(true);
            
            // Metadata
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('request_id');
            $table->index(['request_id', 'created_at']);
            $table->index(['request_package_id', 'created_at']);
            $table->index('update_type');
            $table->index('created_by_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_updates');
    }
};

