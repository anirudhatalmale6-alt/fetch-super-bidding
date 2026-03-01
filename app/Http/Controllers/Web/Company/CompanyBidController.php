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
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'Company not found.');
        }

        $bids = InterstateBid::where('trucking_company_id', $company->id)
            ->with(['request'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

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

        // Get available interstate requests (not completed/cancelled, not already bid on)
        $requests = DeliveryRequest::where('delivery_type', 'interstate')
            ->where('is_completed', false)
            ->where('is_cancelled', false)
            ->whereDoesntHave('bids', function($query) use ($company) {
                $query->where('trucking_company_id', $company->id);
            })
            ->with(['packages', 'requestPlace', 'originHub', 'destinationHub'])
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
            ->where('is_completed', false)
            ->where('is_cancelled', false)
            ->with(['packages', 'requestPlace', 'originHub', 'destinationHub'])
            ->firstOrFail();

        // Check if already bid
        $existingBid = InterstateBid::where('request_id', $requestId)
            ->where('trucking_company_id', $company->id)
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
            'transportation_fee' => 'required|numeric|min:0',
            'insurance_fee' => 'required|numeric|min:0',
            'estimated_delivery_hours' => 'required|integer|min:1|max:720',
            'bid_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $deliveryRequest = DeliveryRequest::findOrFail($request->request_id);

        // Check if request is still open
        if ($deliveryRequest->is_completed || $deliveryRequest->is_cancelled) {
            return back()->with('error', 'This request is no longer accepting bids.');
        }

        // Check if already bid
        $existingBid = InterstateBid::where('request_id', $request->request_id)
            ->where('trucking_company_id', $company->id)
            ->first();

        if ($existingBid) {
            return back()->with('error', 'You have already placed a bid on this request.');
        }

        // Create bid (total_bid_amount auto-calculated in model boot)
        $bid = InterstateBid::create([
            'request_id' => $request->request_id,
            'trucking_company_id' => $company->id,
            'transportation_fee' => $request->transportation_fee,
            'insurance_fee' => $request->insurance_fee,
            'estimated_delivery_hours' => $request->estimated_delivery_hours,
            'bid_notes' => $request->bid_notes,
            'status' => 'pending',
        ]);

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

        $bids = InterstateBid::where('trucking_company_id', $company->id)
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
            ->where('trucking_company_id', $company->id)
            ->with(['request', 'request.packages', 'request.requestPlace', 'request.originHub', 'request.destinationHub'])
            ->firstOrFail();

        return view('company.bids.show', compact('bid', 'company'));
    }

    /**
     * Edit bid form
     */
    public function edit($id)
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        $bid = InterstateBid::where('id', $id)
            ->where('trucking_company_id', $company->id)
            ->where('status', 'pending')
            ->with(['request', 'request.packages'])
            ->firstOrFail();

        return view('company.bids.edit', compact('bid', 'company'));
    }

    /**
     * Update bid
     */
    public function update(Request $request, $id)
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        $bid = InterstateBid::where('id', $id)
            ->where('trucking_company_id', $company->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'transportation_fee' => 'required|numeric|min:0',
            'insurance_fee' => 'required|numeric|min:0',
            'estimated_delivery_hours' => 'required|integer|min:1|max:720',
            'bid_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $bid->update([
            'transportation_fee' => $request->transportation_fee,
            'insurance_fee' => $request->insurance_fee,
            'estimated_delivery_hours' => $request->estimated_delivery_hours,
            'bid_notes' => $request->bid_notes,
            'is_revised' => true,
        ]);

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
            ->where('trucking_company_id', $company->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $bid->update([
            'status' => 'withdrawn',
            'withdrawn_at' => now(),
        ]);

        return redirect()->route('company.bids.history')
            ->with('success', 'Bid withdrawn successfully.');
    }
}
