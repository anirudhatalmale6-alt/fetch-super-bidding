<?php

namespace App\Http\Controllers\Web\Company;

use App\Http\Controllers\Web\BaseController;
use App\Models\Interstate\InterstateBid;
use App\Models\Interstate\TruckingCompany;
use App\Models\Request\Request as DeliveryRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompanyBidController extends BaseController
{
    /**
     * List all bids for the company (index page)
     */
    public function index()
    {
        $user = auth('web')->user();
        $company = TruckingCompany::where('user_id', $user->id)->first();

        if (!$company) {
            $bids = collect();
            $company = null;
        } else {
            $bids = InterstateBid::where('trucking_company_id', $company->id)
                ->with(['request'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        }

        return view('company.bids.index', compact('bids', 'company'));
    }

    /**
     * Display available interstate requests for bidding
     */
    public function available()
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        if (!$company || !in_array($company->company_type, ['interstate_trucking', 'both'])) {
            return redirect()->route('company.dashboard')
                ->with('error', 'Your company type does not support bidding.');
        }

        // Get company's supported routes
        $supportedRoutes = $company->supportedRoutes()->pluck('route_code')->toArray();

        // Get available requests that match company routes
        $requests = DeliveryRequest::where('delivery_type', 'interstate')
            ->where('interstate_status', 'pending')
            ->whereDoesntHave('bids', function($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->when(!empty($supportedRoutes), function($query) use ($supportedRoutes) {
                return $query->whereIn('route_code', $supportedRoutes);
            })
            ->with(['packages', 'pickupLocation', 'dropLocation'])
            ->latest()
            ->paginate(20);

        return view('company.bids.available', compact('requests', 'company'));
    }

    /**
     * Show bid submission form
     */
    public function create($requestId)
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        if (!$company || !in_array($company->company_type, ['interstate_trucking', 'both'])) {
            return redirect()->route('company.dashboard')
                ->with('error', 'Your company type does not support bidding.');
        }

        $deliveryRequest = DeliveryRequest::where('id', $requestId)
            ->where('delivery_type', 'interstate')
            ->where('interstate_status', 'pending')
            ->with(['packages', 'pickupLocation', 'dropLocation'])
            ->firstOrFail();

        // Check if already bid
        $existingBid = InterstateBid::where('request_id', $requestId)
            ->where('company_id', $company->id)
            ->first();

        if ($existingBid) {
            return redirect()->route('company.bids.history')
                ->with('info', 'You have already placed a bid on this request.');
        }

        return view('company.bids.create', compact('deliveryRequest', 'company'));
    }

    /**
     * Submit a bid
     */
    public function submit(Request $request)
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        if (!$company || !in_array($company->company_type, ['interstate_trucking', 'both'])) {
            return back()->with('error', 'Your company type does not support bidding.');
        }

        $validator = Validator::make($request->all(), [
            'request_id' => 'required|exists:requests,id',
            'transport_fee' => 'required|numeric|min:0',
            'insurance_fee' => 'required|numeric|min:0',
            'estimated_delivery_days' => 'required|integer|min:1|max:30',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $deliveryRequest = DeliveryRequest::findOrFail($request->request_id);

        // Check if request is still pending
        if ($deliveryRequest->interstate_status !== 'pending') {
            return back()->with('error', 'This request is no longer accepting bids.');
        }

        // Check if already bid
        $existingBid = InterstateBid::where('request_id', $request->request_id)
            ->where('company_id', $company->id)
            ->first();

        if ($existingBid) {
            return back()->with('error', 'You have already placed a bid on this request.');
        }

        // Calculate bid amount
        $transportFee = $request->transport_fee;
        $insuranceFee = $request->insurance_fee;
        $bidAmount = $transportFee + $insuranceFee;

        // Create bid
        $bid = InterstateBid::create([
            'request_id' => $request->request_id,
            'company_id' => $company->id,
            'bid_amount' => $bidAmount,
            'transport_fee' => $transportFee,
            'insurance_fee' => $insuranceFee,
            'estimated_delivery_days' => $request->estimated_delivery_days,
            'notes' => $request->notes,
            'status' => 'pending',
        ]);

        // Fire event
        event(new \App\Events\Interstate\BidPlaced($bid));

        return redirect()->route('company.bids.history')
            ->with('success', 'Bid submitted successfully!');
    }

    /**
     * Show bid history
     */
    public function history()
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'Company not found.');
        }

        $bids = InterstateBid::where('company_id', $company->id)
            ->with(['request', 'request.packages'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('company.bids.history', compact('bids', 'company'));
    }

    /**
     * Show bid detail
     */
    public function show($id)
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        $bid = InterstateBid::where('id', $id)
            ->where('company_id', $company->id)
            ->with(['request', 'request.packages', 'request.pickupLocation', 'request.dropLocation'])
            ->firstOrFail();

        return view('company.bids.show', compact('bid'));
    }

    /**
     * Edit bid form
     */
    public function edit($id)
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        $bid = InterstateBid::where('id', $id)
            ->where('company_id', $company->id)
            ->where('status', 'pending')
            ->with(['request', 'request.packages'])
            ->firstOrFail();

        return view('company.bids.edit', compact('bid'));
    }

    /**
     * Update bid
     */
    public function update(Request $request, $id)
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        $bid = InterstateBid::where('id', $id)
            ->where('company_id', $company->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'transport_fee' => 'required|numeric|min:0',
            'insurance_fee' => 'required|numeric|min:0',
            'estimated_delivery_days' => 'required|integer|min:1|max:30',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $transportFee = $request->transport_fee;
        $insuranceFee = $request->insurance_fee;
        $bidAmount = $transportFee + $insuranceFee;

        $bid->update([
            'bid_amount' => $bidAmount,
            'transport_fee' => $transportFee,
            'insurance_fee' => $insuranceFee,
            'estimated_delivery_days' => $request->estimated_delivery_days,
            'notes' => $request->notes,
        ]);

        event(new \App\Events\Interstate\BidPlaced($bid));

        return redirect()->route('company.bids.history')
            ->with('success', 'Bid updated successfully!');
    }

    /**
     * Withdraw bid
     */
    public function withdraw($id)
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        $bid = InterstateBid::where('id', $id)
            ->where('company_id', $company->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $bid->update(['status' => 'withdrawn']);

        return redirect()->route('company.bids.history')
            ->with('success', 'Bid withdrawn successfully.');
    }
}
