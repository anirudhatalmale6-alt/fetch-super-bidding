<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompanyPackagesTables extends Migration
{
    public function up()
    {
        // Packages table - using bigInteger without foreign key (multi-tenant issue)
        Schema::create('company_packages', function (Blueprint $table) {
            $table->id();
            $table->string('goods_id')->unique()->index();
            $table->bigInteger('user_id')->index();
            $table->bigInteger('company_id')->index();
            $table->bigInteger('driver_id')->nullable();
            $table->string('origin')->nullable();
            $table->string('destination')->nullable();
            $table->string('origin_address')->nullable();
            $table->string('destination_address')->nullable();
            $table->string('status')->default('awaiting_pickup')->index();
            $table->decimal('insurance_cost', 12, 2)->nullable();
            $table->decimal('transportation_cost', 12, 2)->nullable();
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->string('dimensions')->nullable();
            $table->json('tracking_notes')->nullable();
            $table->timestamps();
        });

        // Package tracking table
        Schema::create('company_package_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('goods_id')->index();
            $table->bigInteger('company_id')->index();
            $table->text('note');
            $table->decimal('cost_added', 12, 2)->nullable();
            $table->decimal('insurance_added', 12, 2)->nullable();
            $table->bigInteger('created_by_admin_id')->nullable();
            $table->timestamps();
        });

        // Package payments table
        Schema::create('company_package_payments', function (Blueprint $table) {
            $table->id();
            $table->string('goods_id')->index();
            $table->bigInteger('user_id')->index();
            $table->bigInteger('company_id')->index();
            $table->string('cost_type');
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending')->index();
            $table->string('payment_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('company_package_payments');
        Schema::dropIfExists('company_package_tracking');
        Schema::dropIfExists('company_packages');
    }
}
