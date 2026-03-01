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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            
            // Company-based cart (not user-based)
            $table->foreignId('company_id');
            $table->foreignId('user_id'); // User who added item
            
            $table->foreignId('product_id');
            $table->integer('quantity')->default(1);
            
            // Snapshot pricing
            $table->decimal('unit_price', 12, 2);
            
            $table->timestamps();

            // Unique constraint: one product per company cart
            $table->unique(['company_id', 'product_id']);
            
            $table->index('company_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};

