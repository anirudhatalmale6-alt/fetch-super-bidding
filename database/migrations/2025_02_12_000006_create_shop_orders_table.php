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
        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            
            // Buyer (Company only)
            $table->foreignId('company_id');
            $table->foreignId('user_id'); // User who placed order
            
            // Order totals
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            
            // Payment info
            $table->enum('payment_method', ['flutterwave', 'bank_transfer']);
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->string('transaction_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            // Bank transfer details (if applicable)
            $table->text('bank_transfer_proof')->nullable(); // Image path or notes
            $table->timestamp('bank_transfer_submitted_at')->nullable();
            
            // Order status
            $table->enum('status', [
                'pending',
                'processing',
                'shipped',
                'delivered',
                'cancelled'
            ])->default('pending');
            
            // Delivery info
            $table->string('delivery_contact_name');
            $table->string('delivery_contact_phone');
            $table->text('delivery_address');
            $table->text('delivery_notes')->nullable();
            
            // Admin notes (for manual delivery tracking)
            $table->text('admin_notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index('order_number');
            $table->index('company_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_orders');
    }
};

