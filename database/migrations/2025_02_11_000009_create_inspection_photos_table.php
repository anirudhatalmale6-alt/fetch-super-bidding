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
        Schema::create('inspection_photos', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id');
            $table->foreignId('request_package_id');
            
            // Photo details
            $table->string('photo_url');
            $table->string('photo_type'); // 'weight_measurement', 'dimension_check', 'condition_check', 'label_scan', 'package_overview'
            $table->text('description')->nullable();
            
            // Measurement data (if applicable)
            $table->decimal('recorded_weight', 8, 2)->nullable();
            $table->decimal('recorded_length', 8, 2)->nullable();
            $table->decimal('recorded_width', 8, 2)->nullable();
            $table->decimal('recorded_height', 8, 2)->nullable();
            
            // Who took the photo
            $table->foreignId('taken_by_id');
            $table->string('taken_by_name');
            $table->foreignId('hub_id')->nullable();
            
            // Location data
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Timestamps
            $table->timestamp('taken_at');
            $table->timestamps();
            
            // Indexes
            $table->index('request_id');
            $table->index('request_package_id');
            $table->index('photo_type');
            $table->index('taken_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_photos');
    }
};

