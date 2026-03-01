<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixRequestIdTypeInPackagesAndLegsTables extends Migration
{
    /**
     * Fix request_id column type to match requests.id (char(36) UUID)
     */
    public function up()
    {
        // request_packages.request_id was bigint unsigned, needs to be char(36) for UUID FK
        DB::statement('ALTER TABLE request_packages MODIFY COLUMN request_id CHAR(36) NOT NULL');

        // request_legs.request_id was bigint unsigned, needs to be char(36) for UUID FK
        DB::statement('ALTER TABLE request_legs MODIFY COLUMN request_id CHAR(36) NOT NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE request_packages MODIFY COLUMN request_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE request_legs MODIFY COLUMN request_id BIGINT UNSIGNED NOT NULL');
    }
}
