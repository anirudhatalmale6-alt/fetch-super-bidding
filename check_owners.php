<?php
// Quick script to check owner accounts
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$owners = DB::table('users')
    ->join('owners', 'users.id', '=', 'owners.user_id')
    ->select('users.id', 'users.email', 'users.name', 'owners.company_name', 'owners.phone')
    ->limit(10)
    ->get();

echo "=== OWNER/ACCOUNTS ===\n\n";

foreach ($owners as $owner) {
    echo "ID: {$owner->id}\n";
    echo "Email: {$owner->email}\n";
    echo "Name: {$owner->name}\n";
    echo "Company: {$owner->company_name}\n";
    echo "Phone: {$owner->phone}\n";
    echo "------------------------\n";
}

// Check if there's a default password pattern
echo "\n=== DEFAULT PASSWORD ===\n";
echo "Common passwords to try: 123456789, password, 123456, owner123\n";
