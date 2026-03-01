<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Checking User Roles ===\n\n";

$users = DB::table('users')
    ->whereIn('email', ['emmaabel2@gmail.com', 'abc@gmail.com'])
    ->get(['id', 'email']);

foreach ($users as $user) {
    echo "User: " . $user->email . " (ID: " . $user->id . ")\n";
    
    // Get roles from role_user table
    $roles = DB::table('role_user')
        ->where('user_id', $user->id)
        ->join('roles', 'role_user.role_id', '=', 'roles.id')
        ->select('roles.name', 'roles.slug')
        ->get();
    
    if (count($roles) > 0) {
        echo "  Roles:\n";
        foreach ($roles as $role) {
            echo "    - " . $role->name . " (slug: " . $role->slug . ")\n";
        }
    } else {
        echo "  ERROR: No roles found!\n";
    }
    echo "\n";
}
