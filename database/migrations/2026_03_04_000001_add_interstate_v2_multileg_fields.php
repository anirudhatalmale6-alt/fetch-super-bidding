<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Interstate V2: Company-selection flow (no upfront bidding)
     *
     * Flow: User selects company → Leg 1 (local pickup to hub) →
     *       Company weighs/prices → User approves → Leg 2 (hub to recipient)
     */
    public function up(): void
    {
        // Add parent-child linking for interstate leg requests
        Schema::table('requests', function (Blueprint $table) {
            // Links child bid ride requests to parent interstate request
            if (!Schema::hasColumn('requests', 'interstate_parent_id')) {
                $table->uuid('interstate_parent_id')->nullable()->index();
            }
            // Which leg this child request represents (1=pickup, 2=delivery, 3+=reroute)
            if (!Schema::hasColumn('requests', 'interstate_leg_number')) {
                $table->integer('interstate_leg_number')->nullable();
            }
            // Type: local_pickup, local_delivery, reroute_transfer
            if (!Schema::hasColumn('requests', 'interstate_leg_type')) {
                $table->string('interstate_leg_type', 30)->nullable();
            }
            // Sender/recipient fields (if not already present)
            if (!Schema::hasColumn('requests', 'sender_phone')) {
                $table->string('sender_phone', 20)->nullable();
            }
            if (!Schema::hasColumn('requests', 'sender_name')) {
                $table->string('sender_name', 100)->nullable();
            }
            if (!Schema::hasColumn('requests', 'recipient_phone')) {
                $table->string('recipient_phone', 20)->nullable();
            }
            if (!Schema::hasColumn('requests', 'recipient_name')) {
                $table->string('recipient_name', 100)->nullable();
            }
            if (!Schema::hasColumn('requests', 'pickup_state')) {
                $table->string('pickup_state', 100)->nullable();
            }
            if (!Schema::hasColumn('requests', 'destination_state')) {
                $table->string('destination_state', 100)->nullable();
            }
        });

        // Add bid_request_id to request_legs for linking legs to child bid rides
        Schema::table('request_legs', function (Blueprint $table) {
            if (!Schema::hasColumn('request_legs', 'bid_request_id')) {
                $table->uuid('bid_request_id')->nullable()->index()
                    ->comment('Links to the child bid ride request for this leg');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $columns = ['interstate_parent_id', 'interstate_leg_number', 'interstate_leg_type'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('request_legs', function (Blueprint $table) {
            if (Schema::hasColumn('request_legs', 'bid_request_id')) {
                $table->dropColumn('bid_request_id');
            }
        });
    }
};
