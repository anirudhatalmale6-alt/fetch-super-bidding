<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // Add all columns that might be missing
            if (!Schema::hasColumn('products', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (!Schema::hasColumn('products', 'discount_price')) {
                $table->decimal('discount_price', 10, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('products', 'stock_quantity')) {
                $table->integer('stock_quantity')->default(0)->after('discount_price');
            }
            if (!Schema::hasColumn('products', 'sku')) {
                $table->string('sku')->unique()->nullable()->after('stock_quantity');
            }
            if (!Schema::hasColumn('products', 'category')) {
                $table->string('category')->nullable()->after('sku');
            }
            if (!Schema::hasColumn('products', 'images')) {
                $table->json('images')->nullable()->after('category');
            }
            if (!Schema::hasColumn('products', 'video_url')) {
                $table->string('video_url')->nullable()->after('images');
            }
            if (!Schema::hasColumn('products', 'banner_image')) {
                $table->string('banner_image')->nullable()->after('video_url');
            }
            if (!Schema::hasColumn('products', 'banner_video_url')) {
                $table->string('banner_video_url')->nullable()->after('banner_image');
            }
            if (!Schema::hasColumn('products', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('banner_video_url');
            }
            if (!Schema::hasColumn('products', 'status')) {
                $table->boolean('status')->default(true)->after('is_featured');
            }
            if (!Schema::hasColumn('products', 'target_audience')) {
                $table->string('target_audience')->default('all')->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
}
