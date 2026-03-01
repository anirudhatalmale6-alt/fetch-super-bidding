<?php
/**
 * Script to create ABC Logistics company account
 * Run: php create_abc_logistics.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Admin\Owner;
use App\Models\Access\Role;
use App\Models\Interstate\TruckingCompany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

echo "Starting to create ABC Logistics account...\n";

try {
    DB::beginTransaction();

    // 1. Check if user already exists
    $email = 'abc@gmail.com';
    $existingUser = User::where('email', $email)->first();

    if ($existingUser) {
        echo "User with email $email already exists. Updating...\n";
        $user = $existingUser;
    } else {
        // 2. Create the user
        $user = User::create([
            'name' => 'ABC Logistics',
            'email' => $email,
            'password' => Hash::make('1234567'),
            'mobile' => '+2349000000000',
            'country' => 1, // Nigeria
            'active' => 1,
            'email_confirmed' => 1,
            'mobile_confirmed' => 1,
        ]);
        echo "Created user ID: {$user->id}\n";
    }

    // 3. Get or create the owner role
    $ownerRole = Role::where('slug', 'owner')->first();
    if (!$ownerRole) {
        $ownerRole = Role::create([
            'slug' => 'owner',
            'name' => 'Owner',
            'description' => 'Fleet/Trucking Company Owner',
            'all' => 0,
        ]);
        echo "Created owner role\n";
    }

    // 4. Attach owner role to user
    if (!$user->roles()->where('role_id', $ownerRole->id)->exists()) {
        $user->roles()->attach($ownerRole->id);
        echo "Attached owner role to user\n";
    }

    // 5. Create or get Trucking Company
    $truckingCompany = TruckingCompany::where('user_id', $user->id)->first();
    
    if (!$truckingCompany) {
        $truckingCompany = TruckingCompany::create([
            'company_name' => 'ABC Logistics',
            'slug' => 'abc-logistics',
            'registration_number' => 'ABC/TG/2026/' . rand(10000, 99999),
            'email' => $email,
            'phone' => '+2349000000000',
            'user_id' => $user->id,
            'status' => 'active',
            'company_type' => 'interstate_trucking',
            'commission_rate' => 15.00,
            'fleet_size' => 10,
            'service_types' => json_encode(['interstate', 'local_delivery', 'last_mile']),
            'operating_states' => json_encode(['Lagos', 'Ogun']),
            'rating' => 5.0,
            'default_volumetric_divisor' => 5000,
            'default_minimum_charge' => 3000.00,
            'max_weight_per_package' => 500.00,
            'max_dimensions_cm' => json_encode(['length' => 150, 'width' => 100, 'height' => 100]),
        ]);
        echo "Created trucking company ID: {$truckingCompany->id}\n";
    } else {
        echo "Trucking company already exists: {$truckingCompany->id}\n";
    }

    // 6. Check if owner record exists
    $existingOwner = Owner::where('user_id', $user->id)->first();

    if ($existingOwner) {
        echo "Owner record already exists. Updating...\n";
        $owner = $existingOwner;
        // Update with trucking company ID
        $owner->update([
            'trucking_company_id' => $truckingCompany->id,
            'company_type' => 'trucking',
        ]);
    } else {
        // 7. Create owner record
        $owner = Owner::create([
            'user_id' => $user->id,
            'service_location_id' => 'e58c7073-dc9a-4b4e-93c3-286f59af12a6', // surulere
            'trucking_company_id' => $truckingCompany->id,
            'company_name' => 'ABC Logistics',
            'owner_name' => 'ABC Logistics Owner',
            'name' => 'ABC',
            'surname' => 'Logistics',
            'email' => $email,
            'mobile' => '+2349000000000',
            'phone' => '+2349000000000',
            'address' => 'Surulere, Lagos',
            'city' => 'Lagos',
            'active' => 1,
            'approve' => 1,
            'company_type' => 'trucking',
        ]);
        echo "Created owner record ID: {$owner->id}\n";
    }

    DB::commit();
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ SUCCESS! ABC Logistics account created.\n";
    echo str_repeat("=", 50) . "\n";
    echo "Email: $email\n";
    echo "Password: 1234567\n";
    echo "Company Name: ABC Logistics\n";
    echo "Service Area: Surulere, Lagos\n";
    echo "Company Type: Interstate Trucking\n";
    echo "Login URL: /login\n";
    echo "Company Dashboard: /company/dashboard\n";
    echo str_repeat("=", 50) . "\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
