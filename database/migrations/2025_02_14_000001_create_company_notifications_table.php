<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompanyNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('company_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('trucking_company_id')->nullable();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('type', 50)->default('general');
            $table->boolean('is_read')->default(false);
            $table->string('link', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // FK removed for compatibility

            // FK removed for compatibility

            $table->index(['owner_id', 'is_read']);
            $table->index(['trucking_company_id']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('company_notifications');
    }
}

