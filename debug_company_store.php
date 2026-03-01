<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Checking Users and Companies ===\n\n";

// Check users
$users = DB::table('users')
    ->whereIn('email', ['emmaabel2@gmail.com', 'abc@gmail.com'])
    ->get(['id', 'email', 'name']);

foreach ($users as $user) {
    echo "User: " . $user->email . " (ID: " . $user->id . ")\n";
    
    $company = DB::table('trucking_companies')
        ->where('user_id', $user->id)
        ->first();
    
    if ($company) {
        echo "  Company: " . $company->company_name . " (ID: " . $company->id . ")\n";
    } else {
        echo "  ERROR: No company record found!\n";
    }
    echo "\n";
}

echo "=== Checking Products ===\n\n";

$products = DB::table('products')
    ->where('status', 1)
    ->get(['id', 'name', 'price', 'target_audience']);

echo "Total active products: " . count($products) . "\n";

$companyProducts = DB::table('products')
    ->where('status', 1)
    ->whereIn('target_audience', ['company', 'all'])
    ->get();

echo "Products for companies: " . count($companyProducts) . "\n";

foreach ($companyProducts->take(5) as $product) {
    echo "  - " . $product->name . " (" . $product->target_audience . ")\n";
}
