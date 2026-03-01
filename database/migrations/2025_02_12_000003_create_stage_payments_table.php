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
        Schema::create('stage_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id');
            $table->foreignId('order_stage_id')->onDelete('cascade');
            $table->foreignId('leg_payment_id')->nullable()->onDelete('set null');
            
            // Stage linkage
            $table->integer('stage_number');
            $table->string('stage_code');
            
            // Payment details
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('NGN');
            $table->enum('payment_type', ['stage_unlock', 'additional_charge', 'refund', 'adjustment'])->default('stage_unlock');
            
            // Payment status
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'refunded'])->default('pending');
            
            // Gateway details
            $table->string('gateway')->nullable(); // paystack, flutterwave, etc.
            $table->string('transaction_reference')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->json('gateway_response')->nullable();
            
            // Timestamps
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            
            // Who initiated
            $table->foreignId('initiated_by')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['request_id', 'stage_number']);
            $table->index(['request_id', 'status']);
            $table->index('transaction_reference');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stage_payments');
    }
};

