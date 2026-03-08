<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddWhatsappSettings extends Migration
{
    public function up()
    {
        // WhatsApp number for floating chat button
        DB::table('settings')->insertOrIgnore([
            [
                'name' => 'whatsapp_number',
                'field' => 'text',
                'category' => 'general',
                'value' => '',
                'option_value' => null,
                'group_name' => 'whatsapp',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'whatsapp_message',
                'field' => 'text',
                'category' => 'general',
                'value' => 'Hello! I need help with my delivery.',
                'option_value' => null,
                'group_name' => 'whatsapp',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down()
    {
        DB::table('settings')->whereIn('name', ['whatsapp_number', 'whatsapp_message'])->delete();
    }
}
