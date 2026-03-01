<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVideoSupportToBanners extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('banners', function (Blueprint $table) {
            // Add video upload support
            if (!Schema::hasColumn('banners', 'video')) {
                $table->string('video')->nullable()->after('image');
            }
            if (!Schema::hasColumn('banners', 'media_type')) {
                $table->enum('media_type', ['image', 'video'])->default('image')->after('video');
            }
            
            // Update position enum to include company_dashboard
            DB::statement("ALTER TABLE `banners` MODIFY COLUMN `position` 
                ENUM('shop', 'company_store', 'company_dashboard', 'both', 'all') 
                NOT NULL DEFAULT 'shop'");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('banners', function (Blueprint $table) {
            if (Schema::hasColumn('banners', 'video')) {
                $table->dropColumn('video');
            }
            if (Schema::hasColumn('banners', 'media_type')) {
                $table->dropColumn('media_type');
            }
        });
    }
}
