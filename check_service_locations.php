<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$locations = DB::table('service_locations')->select('id', 'name')->limit(10)->get();

if ($locations->isEmpty()) {
    echo "No service locations found. Creating a default one...\n";
    
    // Get first country
    $country = DB::table('countries')->first();
    if ($country) {
        $id = DB::table('service_locations')->insertGetId([
            'name' => 'Nigeria',
            'country' => $country->id,
            'description' => 'Main service location',
            'currency' => 'NGN',
            'currency_symbol' => '₦',
            'timezone' => 'Africa/Lagos',
            'is_default' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "Created service location with ID: $id\n";
    }
} else {
    echo "Service Locations found:\n";
    foreach($locations as $loc) {
        echo "ID: {$loc->id} - Name: {$loc->name}\n";
    }
}
