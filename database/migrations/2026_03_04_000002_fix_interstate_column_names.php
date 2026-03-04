<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            // Add missing columns used by the interstate V2 flow
            if (!Schema::hasColumn('requests', 'approval_status')) {
                $table->string('approval_status', 30)->nullable()->after('inspection_status');
            }
            if (!Schema::hasColumn('requests', 'rerouting_requested_at')) {
                $table->timestamp('rerouting_requested_at')->nullable();
            }
            if (!Schema::hasColumn('requests', 'approved_by_user_id')) {
                $table->unsignedBigInteger('approved_by_user_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'rerouting_requested_at', 'approved_by_user_id']);
        });
    }
};
