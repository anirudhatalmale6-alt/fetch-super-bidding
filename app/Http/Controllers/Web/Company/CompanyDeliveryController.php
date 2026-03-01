<?php

namespace App\Http\Controllers\Web\Company;

use App\Http\Controllers\Web\BaseController;
use App\Models\Interstate\RequestLeg;
use App\Models\Interstate\TruckingCompany;
use Illuminate\Http\Request;

class CompanyDeliveryController extends BaseController
{
    /**
     * Dashboard overview of all delivery legs
     */
    public function index()
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'Trucking company not found.');
        }

        $companyType = 'App\Models\Interstate\TruckingCompany';

        // Stats
        $pendingCount = RequestLeg::where('provider_type', $companyType)
            ->where('provider_id', $company->id)
            ->where('status', 'pending')->count();

        $activeCount = RequestLeg::where('provider_type', $companyType)
            ->where('provider_id', $company->id)
            ->whereIn('status', ['accepted', 'en_route_pickup', 'picked_up', 'arrived_at_hub', 'collected_from_hub', 'in_transit', 'en_route_delivery'])->count();

        $completedCount = RequestLeg::where('provider_type', $companyType)
            ->where('provider_id', $company->id)
            ->where('status', 'completed')->count();

        // Get legs filtered by status tab
        $status = request('status', 'all');
        $query = RequestLeg::with(['request'])
            ->where('provider_type', $companyType)
            ->where('provider_id', $company->id);

        switch ($status) {
            case 'pending':
                $query->where('status', 'pending');
                break;
            case 'active':
                $query->whereIn('status', ['accepted', 'en_route_pickup', 'picked_up', 'arrived_at_hub', 'collected_from_hub', 'in_transit', 'en_route_delivery']);
                break;
            case 'completed':
                $query->where('status', 'completed');
                break;
        }

        $legs = $query->orderByDesc('created_at')->paginate(20);

        return view('company.deliveries.index', compact(
            'legs', 'company', 'pendingCount', 'activeCount', 'completedCount', 'status'
        ));
    }

    /**
     * Show leg details
     */
    public function show($id)
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        $leg = RequestLeg::with(['request', 'request.packages', 'supportedRoute'])
            ->where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->where('provider_id', $company->id)
            ->findOrFail($id);

        // Get all legs for this request to show the full pipeline
        $allLegs = RequestLeg::where('request_id', $leg->request_id)
            ->orderBy('leg_number')
            ->get();

        return view('company.deliveries.show', compact('leg', 'allLegs', 'company'));
    }

    /**
     * Accept a leg
     */
    public function accept($id)
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        $leg = RequestLeg::where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->where('provider_id', $company->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $leg->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return back()->with('success', 'Delivery leg accepted! Mark as picked up when you collect the package.');
    }

    /**
     * Update leg status
     */
    public function updateStatus(Request $request, $id)
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        $leg = RequestLeg::where('provider_type', 'App\Models\Interstate\TruckingCompany')
            ->where('provider_id', $company->id)
            ->findOrFail($id);

        $newStatus = $request->input('status');
        $allowedTransitions = [
            'accepted' => ['en_route_pickup', 'picked_up'],
            'en_route_pickup' => ['picked_up'],
            'picked_up' => ['arrived_at_hub', 'in_transit'],
            'arrived_at_hub' => ['collected_from_hub'],
            'collected_from_hub' => ['in_transit'],
            'in_transit' => ['en_route_delivery', 'completed'],
            'en_route_delivery' => ['completed'],
        ];

        $allowed = $allowedTransitions[$leg->status] ?? [];
        if (!in_array($newStatus, $allowed)) {
            return back()->with('error', "Cannot change status from '{$leg->status}' to '{$newStatus}'.");
        }

        $updateData = ['status' => $newStatus];
        if ($newStatus === 'picked_up') $updateData['picked_up_at'] = now();
        if ($newStatus === 'arrived_at_hub') $updateData['arrived_at_hub_at'] = now();
        if ($newStatus === 'collected_from_hub') $updateData['collected_at'] = now();
        if ($newStatus === 'completed') $updateData['completed_at'] = now();

        $leg->update($updateData);

        $statusLabels = [
            'en_route_pickup' => 'En Route to Pickup',
            'picked_up' => 'Marked as Picked Up',
            'arrived_at_hub' => 'Arrived at Hub',
            'collected_from_hub' => 'Collected from Hub',
            'in_transit' => 'Marked as In Transit',
            'en_route_delivery' => 'En Route for Delivery',
            'completed' => 'Delivery Completed',
        ];

        return back()->with('success', $statusLabels[$newStatus] ?? 'Status updated.');
    }
}
