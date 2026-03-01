<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

DB::table('trucking_companies')
    ->where('id', 2)
    ->update(['company_type' => 'last_mile_dispatch']);

echo "Updated ABC Logistics company type to last_mile_dispatch (Metro Services)\n";
