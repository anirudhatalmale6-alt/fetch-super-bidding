<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add target_type field to banners table
 * 
 * This separates the display target (homepage/company_dashboard) from 
 * the position field which controls placement within a page.
 */
class AddTargetTypeToBanners extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('banners', function (Blueprint $table) {
            // Add target_type field if not exists
            if (!Schema::hasColumn('banners', 'target_type')) {
                $table->enum('target_type', ['homepage', 'company_dashboard', 'both'])
                    ->default('homepage')
                    ->after('position')
                    ->comment('Determines which dashboard this banner appears on');
            }
        });

        // Migrate existing data - set target_type based on position
        DB::statement("
            UPDATE banners 
            SET target_type = CASE 
                WHEN position = 'company_dashboard' THEN 'company_dashboard'
                WHEN position IN ('shop', 'company_store', 'both') THEN 'homepage'
                ELSE 'both'
            END
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('banners', function (Blueprint $table) {
            if (Schema::hasColumn('banners', 'target_type')) {
                $table->dropColumn('target_type');
            }
        });
    }
}
