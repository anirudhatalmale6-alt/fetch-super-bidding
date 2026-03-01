<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            // Add company_type to distinguish between regular fleet and trucking
            $table->enum('company_type', ['fleet', 'trucking', 'both'])
                  ->default('fleet')
                  ->after('transport_type')
                  ->comment('fleet=regular taxi/delivery, trucking=interstate logistics, both=all services');
            
            // Link to trucking_companies table
            $table->foreignId('trucking_company_id')
                  ->nullable()
                  ->after('company_type')
                  
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropForeign(['trucking_company_id']);
            $table->dropColumn(['company_type', 'trucking_company_id']);
        });
    }
};

