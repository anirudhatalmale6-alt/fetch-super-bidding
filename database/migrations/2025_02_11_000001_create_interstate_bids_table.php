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
        Schema::create('interstate_bids', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id');
            $table->foreignId('trucking_company_id');
            $table->decimal('transportation_fee', 12, 2);
            $table->decimal('insurance_fee', 12, 2)->default(0);
            $table->integer('estimated_delivery_hours');
            $table->decimal('total_bid_amount', 12, 2);
            $table->enum('status', ['pending', 'accepted', 'rejected', 'withdrawn', 'expired'])->default('pending');
            $table->text('bid_notes')->nullable();
            $table->boolean('is_revised')->default(false);
            $table->foreignId('original_bid_id')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['request_id', 'status']);
            $table->index(['trucking_company_id', 'status']);
            $table->index('status');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interstate_bids');
    }
};

