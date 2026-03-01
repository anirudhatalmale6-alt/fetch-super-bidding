<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$roles = DB::table('roles')->get(['id', 'name', 'slug']);

echo "=== Available Roles ===\n";
foreach ($roles as $role) {
    echo "$role->id: $role->name (slug: $role->slug)\n";
}
