<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Get the fleet_owner role ID
$fleetOwnerRole = DB::table('roles')
    ->where('slug', 'fleet_owner')
    ->first();

if (!$fleetOwnerRole) {
    echo "ERROR: fleet_owner role not found!\n";
    exit(1);
}

echo "Fleet Owner Role ID: " . $fleetOwnerRole->id . "\n";

// Get user abc@gmail.com
$user = DB::table('users')
    ->where('email', 'abc@gmail.com')
    ->first();

if (!$user) {
    echo "ERROR: User not found!\n";
    exit(1);
}

echo "User: " . $user->email . " (ID: " . $user->id . ")\n";

// Remove existing owner role
DB::table('role_user')
    ->where('user_id', $user->id)
    ->delete();

echo "Removed old roles.\n";

// Add fleet_owner role
DB::table('role_user')->insert([
    'user_id' => $user->id,
    'role_id' => $fleetOwnerRole->id
]);

echo "Added fleet_owner role to user.\n";

// Update password to 12345678iO
$hashedPassword = password_hash('12345678iO', PASSWORD_DEFAULT);
DB::table('users')
    ->where('email', 'abc@gmail.com')
    ->update(['password' => $hashedPassword]);

echo "Password updated to: 12345678iO\n";

echo "\n=== SUCCESS ===\n";
echo "abc@gmail.com is now a fleet_owner with password: 12345678iO\n";
