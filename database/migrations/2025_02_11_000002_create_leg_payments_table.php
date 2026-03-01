<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLegPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('leg_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->onDelete('cascade');
            $table->foreignId('request_leg_id')->onDelete('cascade');
            $table->integer('leg_number');
            $table->string('leg_type');
            
            // Amount tracking
            $table->decimal('original_amount', 10, 2);
            $table->decimal('adjusted_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->decimal('balance_due', 10, 2)->default(0);
            
            // Payment status
            $table->enum('payment_status', [
                'pending',
                'awaiting_leg_completion',
                'additional_payment_required',
                'paid',
                'refund_pending',
                'refund_processed',
                'refund_failed',
                'finalized',
            ])->default('pending');
            
            // Payment details
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('transaction_id')->nullable();
            $table->json('payment_details')->nullable();
            
            // Adjustment tracking
            $table->string('adjustment_reason')->nullable();
            $table->json('adjustment_details')->nullable();
            
            // Refund tracking
            $table->string('refund_status')->nullable();
            $table->string('refund_reference')->nullable();
            $table->text('refund_failure_reason')->nullable();
            
            // Currency
            $table->string('currency', 3)->default('NGN');
            
            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refund_processed_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['request_id', 'leg_number']);
            $table->index('payment_status');
            $table->index('payment_reference');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leg_payments');
    }
}

