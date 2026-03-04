<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // request_packages.request_id needs to be VARCHAR(36) for UUIDs
        DB::statement("ALTER TABLE request_packages MODIFY COLUMN request_id VARCHAR(36) NOT NULL");

        // Add missing inspection workflow columns to requests
        Schema::table('requests', function (Blueprint $table) {
            if (!Schema::hasColumn('requests', 'inspection_started_at')) {
                $table->timestamp('inspection_started_at')->nullable()->after('inspection_remarks');
            }
            if (!Schema::hasColumn('requests', 'inspection_completed_at')) {
                $table->timestamp('inspection_completed_at')->nullable()->after('inspection_started_at');
            }
            if (!Schema::hasColumn('requests', 'initial_bid_amount')) {
                $table->decimal('initial_bid_amount', 12, 2)->nullable()->default(0)->after('final_total_amount');
            }
            if (!Schema::hasColumn('requests', 'price_difference')) {
                $table->decimal('price_difference', 12, 2)->nullable()->after('initial_bid_amount');
            }
            if (!Schema::hasColumn('requests', 'price_difference_percent')) {
                $table->decimal('price_difference_percent', 8, 2)->nullable()->after('price_difference');
            }
        });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE request_packages MODIFY COLUMN request_id BIGINT UNSIGNED NOT NULL");

        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn([
                'inspection_started_at', 'inspection_completed_at',
                'initial_bid_amount', 'price_difference', 'price_difference_percent'
            ]);
        });
    }
};
