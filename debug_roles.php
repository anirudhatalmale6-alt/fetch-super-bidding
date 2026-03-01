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
    
    // Get roles from model_has_roles
    $roles = DB::table('model_has_roles')
        ->where('model_id', $user->id)
        ->where('model_type', 'App\Models\User')
        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
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

echo "=== Checking Route Middleware ===\n";
echo "Company routes require: trucking_company or fleet_owner role\n";
