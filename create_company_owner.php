<?php
/**
 * Script to create a company owner account with full trucking company setup
 * Run: php create_company_owner.php
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

echo "Starting to create company owner account...\n";

try {
    DB::beginTransaction();

    // 1. Check if user already exists
    $email = 'emmaabel2@gmail.com';
    $existingUser = User::where('email', $email)->first();

    if ($existingUser) {
        echo "User with email $email already exists. Updating...\n";
        $user = $existingUser;
    } else {
        // 2. Create the user
        $user = User::create([
            'name' => 'Emma Abel',
            'email' => $email,
            'password' => Hash::make('12345678'),
            'mobile' => '+2348000000000',
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
            'company_name' => 'Emma Abel Transport',
            'slug' => 'emma-abel-transport',
            'registration_number' => 'EA/TG/2026/' . rand(10000, 99999),
            'email' => $email,
            'phone' => '+2348000000000',
            'user_id' => $user->id,
            'status' => 'active',
            'company_type' => 'interstate_trucking',
            'commission_rate' => 15.00,
            'fleet_size' => 5,
            'service_types' => json_encode(['interstate', 'local_delivery']),
            'operating_states' => json_encode(['Lagos', 'Ogun', 'Oyo', 'Kwara', 'Abuja']),
            'rating' => 5.0,
            'default_volumetric_divisor' => 5000,
            'default_minimum_charge' => 5000.00,
            'max_weight_per_package' => 1000.00,
            'max_dimensions_cm' => json_encode(['length' => 200, 'width' => 150, 'height' => 150]),
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
            'company_name' => 'Emma Abel Transport',
            'owner_name' => 'Emma Abel',
            'name' => 'Emma',
            'surname' => 'Abel',
            'email' => $email,
            'mobile' => '+2348000000000',
            'phone' => '+2348000000000',
            'address' => 'Nigeria',
            'city' => 'Lagos',
            'active' => 1,
            'approve' => 1,
            'company_type' => 'trucking',
        ]);
        echo "Created owner record ID: {$owner->id}\n";
    }

    DB::commit();
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ SUCCESS! Company owner account created.\n";
    echo str_repeat("=", 50) . "\n";
    echo "Email: $email\n";
    echo "Password: 12345678\n";
    echo "Company Name: Emma Abel Transport\n";
    echo "Company Type: Interstate Trucking\n";
    echo "Login URL: /login\n";
    echo "Company Dashboard: /company/dashboard\n";
    echo str_repeat("=", 50) . "\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
