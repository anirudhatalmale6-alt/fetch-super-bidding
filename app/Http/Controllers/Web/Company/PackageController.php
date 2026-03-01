<?php

namespace App\Http\Controllers\Web\Company;

use App\Http\Controllers\Controller;
use App\Models\Company\Package;
use App\Models\Company\PackageTracking;
use App\Models\Company\PackagePayment;
use App\Models\Interstate\TruckingCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PackageController extends Controller
{
    /**
     * Get authenticated company
     */
    private function getAuthenticatedCompany(): ?TruckingCompany
    {
        $user = Auth::user();
        if (!$user) return null;
        return TruckingCompany::where('user_id', $user->id)->first();
    }

    /**
     * Display package list for company
     */
    public function index()
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return redirect()->back()->with('error', 'Company not found');
        }

        $packages = Package::forCompany($company->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $stats = [
            'awaiting_pickup' => Package::forCompany($company->id)->where('status', 'awaiting_pickup')->count(),
            'in_transit' => Package::forCompany($company->id)->where('status', 'in_transit')->count(),
            'delivered' => Package::forCompany($company->id)->where('status', 'delivered')->count(),
            'pending_payment' => Package::forCompany($company->id)->whereRaw('total_cost > 0 AND id NOT IN (SELECT DISTINCT goods_id FROM company_package_payments WHERE status = "paid")')->count(),
        ];

        return view('company.packages.index', compact('packages', 'company', 'stats'));
    }

    /**
     * Display package details
     */
    public function show($id)
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return redirect()->back()->with('error', 'Company not found');
        }

        $package = Package::forCompany($company->id)->findOrFail($id);
        
        $trackingLogs = PackageTracking::where('goods_id', $package->goods_id)
            ->orderBy('created_at', 'desc')
            ->get();
            
        $payments = PackagePayment::where('goods_id', $package->goods_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('company.packages.show', compact('package', 'company', 'trackingLogs', 'payments'));
    }

    /**
     * Update package (add notes, costs, status)
     */
    public function update(Request $request, $id)
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return redirect()->back()->with('error', 'Company not found');
        }

        $package = Package::forCompany($company->id)->findOrFail($id);
        
        $validated = $request->validate([
            'note' => 'nullable|string|max:1000',
            'insurance_cost' => 'nullable|numeric|min:0',
            'transportation_cost' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:awaiting_pickup,picked_up,in_transit,out_for_delivery,delivered,cancelled',
        ]);

        try {
            DB::beginTransaction();

            // Add note if provided
            if (!empty($validated['note'])) {
                $package->addNote($validated['note'], Auth::id());
                
                PackageTracking::create([
                    'goods_id' => $package->goods_id,
                    'company_id' => $company->id,
                    'note' => $validated['note'],
                    'created_by_admin_id' => Auth::id(),
                ]);
            }

            // Update costs if provided
            if (isset($validated['insurance_cost']) || isset($validated['transportation_cost'])) {
                $package->updateCosts(
                    $validated['insurance_cost'] ?? null,
                    $validated['transportation_cost'] ?? null,
                    Auth::id()
                );

                // Create payment records for pending costs
                if (isset($validated['insurance_cost']) && $validated['insurance_cost'] > 0) {
                    PackagePayment::firstOrCreate(
                        [
                            'goods_id' => $package->goods_id,
                            'cost_type' => 'insurance',
                        ],
                        [
                            'user_id' => $package->user_id,
                            'company_id' => $company->id,
                            'amount' => $validated['insurance_cost'],
                            'status' => 'pending',
                        ]
                    );
                }

                if (isset($validated['transportation_cost']) && $validated['transportation_cost'] > 0) {
                    PackagePayment::firstOrCreate(
                        [
                            'goods_id' => $package->goods_id,
                            'cost_type' => 'transportation',
                        ],
                        [
                            'user_id' => $package->user_id,
                            'company_id' => $company->id,
                            'amount' => $validated['transportation_cost'],
                            'status' => 'pending',
                        ]
                    );
                }
            }

            // Update status if provided
            if (!empty($validated['status'])) {
                $oldStatus = $package->status;
                $package->status = $validated['status'];
                $package->save();

                PackageTracking::create([
                    'goods_id' => $package->goods_id,
                    'company_id' => $company->id,
                    'note' => "Status changed from {$oldStatus} to {$validated['status']}",
                    'created_by_admin_id' => Auth::id(),
                ]);
            }

            DB::commit();

            return redirect()->route('company.packages.show', $package->id)
                ->with('success', 'Package updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error updating package: ' . $e->getMessage());
        }
    }

    /**
     * Static method to create package from accepted bid
     * Call this when a driver bid is accepted
     */
    public static function createFromAcceptedBid($bid, $request, $company, $driverId = null): Package
    {
        return Package::create([
            'goods_id' => 'PKG-' . date('Ymd') . '-' . strtoupper(uniqid()),
            'user_id' => $request->user_id,
            'company_id' => $company->id,
            'driver_id' => $driverId,
            'origin' => $request->origin ?? 'Origin',
            'destination' => $request->destination ?? 'Destination',
            'origin_address' => $request->origin_address ?? null,
            'destination_address' => $request->destination_address ?? null,
            'status' => 'awaiting_pickup',
            'description' => $request->description ?? null,
            'weight_kg' => $request->weight_kg ?? null,
            'dimensions' => $request->dimensions ?? null,
        ]);
    }
}
