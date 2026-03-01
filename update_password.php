<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'abc@gmail.com')->first();

if ($user) {
    $user->update(['password' => Hash::make('12345678iO')]);
    echo "Updated password for abc@gmail.com to: 12345678iO\n";
} else {
    echo "User not found!\n";
}
