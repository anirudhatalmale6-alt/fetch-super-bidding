<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add goods_id, handler chain, and origin/destination to goods items
        Schema::table('trucking_goods_items', function (Blueprint $table) {
            if (!Schema::hasColumn('trucking_goods_items', 'goods_id')) {
                $table->string('goods_id', 40)->nullable()->unique()->after('id')
                    ->comment('Globally unique immutable goods identifier (GDS-YYYYMMDD-XXXX)');
            }
            if (!Schema::hasColumn('trucking_goods_items', 'current_handler_type')) {
                $table->string('current_handler_type', 50)->nullable()
                    ->comment('dispatch_rider|trucking_company|hub|delivered')
                    ->after('trucking_company_id');
            }
            if (!Schema::hasColumn('trucking_goods_items', 'current_handler_id')) {
                $table->unsignedBigInteger('current_handler_id')->nullable()
                    ->after('current_handler_type');
            }
            if (!Schema::hasColumn('trucking_goods_items', 'current_handler_name')) {
                $table->string('current_handler_name', 255)->nullable()
                    ->after('current_handler_id');
            }
            if (!Schema::hasColumn('trucking_goods_items', 'handover_chain')) {
                $table->json('handover_chain')->nullable()
                    ->comment('Immutable append-only log of all handlers')
                    ->after('current_handler_name');
            }
            if (!Schema::hasColumn('trucking_goods_items', 'origin_address')) {
                $table->string('origin_address', 500)->nullable()
                    ->after('handover_chain');
            }
            if (!Schema::hasColumn('trucking_goods_items', 'destination_address')) {
                $table->string('destination_address', 500)->nullable()
                    ->after('origin_address');
            }
            if (!Schema::hasColumn('trucking_goods_items', 'picked_up_at')) {
                $table->timestamp('picked_up_at')->nullable();
            }
            if (!Schema::hasColumn('trucking_goods_items', 'received_by_company_at')) {
                $table->timestamp('received_by_company_at')->nullable();
            }
            if (!Schema::hasColumn('trucking_goods_items', 'dispatched_at')) {
                $table->timestamp('dispatched_at')->nullable();
            }
            if (!Schema::hasColumn('trucking_goods_items', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable();
            }
            if (!Schema::hasColumn('trucking_goods_items', 'tracking_notes')) {
                $table->text('tracking_notes')->nullable()
                    ->comment('Latest tracking note from company');
            }
            if (!Schema::hasColumn('trucking_goods_items', 'payment_status')) {
                $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid')->nullable();
            }
            if (!Schema::hasColumn('trucking_goods_items', 'amount_paid')) {
                $table->decimal('amount_paid', 10, 2)->default(0)->nullable();
            }
        });

        // 2. Back-fill goods_id for existing rows
        DB::statement("
            UPDATE trucking_goods_items
            SET goods_id = CONCAT('GDS-', DATE_FORMAT(created_at, '%Y%m%d'), '-', UPPER(SUBSTR(MD5(id), 1, 8)))
            WHERE goods_id IS NULL
        ");

        // 3. Add goods_item_id to tracking_updates so updates can link to a specific goods item
        Schema::table('tracking_updates', function (Blueprint $table) {
            if (!Schema::hasColumn('tracking_updates', 'goods_item_id')) {
                $table->unsignedBigInteger('goods_item_id')->nullable()->index()
                    ->after('request_id');
            }
            if (!Schema::hasColumn('tracking_updates', 'is_cost_update')) {
                $table->boolean('is_cost_update')->default(false)->nullable();
            }
            if (!Schema::hasColumn('tracking_updates', 'cost_type')) {
                $table->string('cost_type', 50)->nullable()
                    ->comment('transport|insurance|handling|other');
            }
            if (!Schema::hasColumn('tracking_updates', 'cost_amount')) {
                $table->decimal('cost_amount', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('tracking_updates', 'is_handover')) {
                $table->boolean('is_handover')->default(false)->nullable();
            }
            if (!Schema::hasColumn('tracking_updates', 'handover_from_type')) {
                $table->string('handover_from_type', 50)->nullable();
            }
            if (!Schema::hasColumn('tracking_updates', 'handover_to_type')) {
                $table->string('handover_to_type', 50)->nullable();
            }
            if (!Schema::hasColumn('tracking_updates', 'handover_to_id')) {
                $table->unsignedBigInteger('handover_to_id')->nullable();
            }
        });

        // 4. Create goods_payment_legs table for staged payment architecture
        if (!Schema::hasTable('goods_payment_legs')) {
            Schema::create('goods_payment_legs', function (Blueprint $table) {
                $table->id();
                $table->string('goods_id', 40)->index()
                    ->comment('References trucking_goods_items.goods_id');
                $table->unsignedBigInteger('goods_item_id')->index();
                $table->unsignedBigInteger('request_id')->index();
                $table->unsignedBigInteger('payer_user_id')->index();

                // Handler this leg belongs to
                $table->string('handler_type', 50)->nullable()
                    ->comment('dispatch_rider|trucking_company|final_delivery');
                $table->unsignedBigInteger('handler_id')->nullable()->index();
                $table->string('handler_name', 255)->nullable();

                // Payment details
                $table->string('leg_type', 50)
                    ->comment('pickup_fee|interstate_transport|insurance|handling|final_delivery');
                $table->decimal('amount', 10, 2);
                $table->decimal('amount_paid', 10, 2)->default(0);
                $table->enum('payment_status', ['pending', 'partial', 'paid', 'waived', 'refunded'])->default('pending');
                $table->string('payment_reference', 100)->nullable();
                $table->string('payment_channel', 50)->nullable()
                    ->comment('paystack|flutterwave|bank_transfer|cash|wallet');
                $table->json('payment_metadata')->nullable();
                $table->timestamp('payment_confirmed_at')->nullable();
                $table->unsignedBigInteger('confirmed_by')->nullable();

                // Flags
                $table->boolean('is_unlocked')->default(true)
                    ->comment('False = next leg locked until this is paid');
                $table->boolean('notified_user')->default(false);
                $table->timestamp('user_notified_at')->nullable();

                $table->timestamps();
                $table->index(['goods_id', 'payment_status']);
                $table->index(['handler_type', 'handler_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_payment_legs');

        Schema::table('tracking_updates', function (Blueprint $table) {
            $cols = ['goods_item_id', 'is_cost_update', 'cost_type', 'cost_amount',
                     'is_handover', 'handover_from_type', 'handover_to_type', 'handover_to_id'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('tracking_updates', $c));
            if ($existing) { $table->dropColumn(array_values($existing)); }
        });

        Schema::table('trucking_goods_items', function (Blueprint $table) {
            $cols = ['goods_id', 'current_handler_type', 'current_handler_id', 'current_handler_name',
                     'handover_chain', 'origin_address', 'destination_address',
                     'picked_up_at', 'received_by_company_at', 'dispatched_at', 'delivered_at',
                     'tracking_notes', 'payment_status', 'amount_paid'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('trucking_goods_items', $c));
            if ($existing) { $table->dropColumn(array_values($existing)); }
        });
    }
};
