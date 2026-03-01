<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modify enum using raw SQL (Doctrine DBAL cannot introspect enum columns)
        DB::statement("ALTER TABLE `banners` MODIFY COLUMN `position`
            ENUM('homepage','shop','company_dashboard','both')
            NOT NULL DEFAULT 'homepage'");

        Schema::table('banners', function (Blueprint $table) {
            if (!Schema::hasColumn('banners', 'display_duration')) {
                $table->integer('display_duration')->default(5);
            }
            if (!Schema::hasColumn('banners', 'transition_effect')) {
                $table->enum('transition_effect', ['slide', 'fade', 'zoom'])->default('slide');
            }
            if (!Schema::hasColumn('banners', 'auto_play')) {
                $table->boolean('auto_play')->default(true);
            }
            if (!Schema::hasColumn('banners', 'background_color')) {
                $table->string('background_color')->nullable();
            }
            if (!Schema::hasColumn('banners', 'text_position')) {
                $table->enum('text_position', ['left', 'center', 'right'])->default('center');
            }
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $cols = ['display_duration', 'transition_effect', 'auto_play', 'background_color', 'text_position'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('banners', $c));
            if ($existing) { $table->dropColumn(array_values($existing)); }
        });
    }
};
