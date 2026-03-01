<?php

namespace App\Http\Controllers\Web\Company;

use App\Http\Controllers\Web\BaseController;
use App\Models\Interstate\TruckingHub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HubController extends BaseController
{
    /**
     * Display list of hubs
     */
    public function index()
    {
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'Company not found.');
        }

        $hubs = $company->hubs()
            ->orderBy('is_primary', 'desc')
            ->orderBy('name')
            ->paginate(20);

        return view('company.hubs.index', compact('hubs', 'company'));
    }

    /**
     * Show create hub form
     */
    public function create()
    {
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'Company not found.');
        }

        return view('company.hubs.create', compact('company'));
    }

    /**
     * Store new hub
     */
    public function store(Request $request)
    {
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;

        if (!$company) {
            return back()->with('error', 'Company not found.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $company->hubs()->create($request->all());

        return redirect()->route('company.hubs.index')
            ->with('success', 'Hub created successfully!');
    }

    /**
     * Show edit hub form
     */
    public function edit($id)
    {
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;

        $hub = $company->hubs()->findOrFail($id);

        return view('company.hubs.edit', compact('hub', 'company'));
    }

    /**
     * Update hub
     */
    public function update(Request $request, $id)
    {
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;

        $hub = $company->hubs()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $hub->update($request->all());

        return redirect()->route('company.hubs.index')
            ->with('success', 'Hub updated successfully!');
    }

    /**
     * Delete hub
     */
    public function destroy($id)
    {
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;

        $hub = $company->hubs()->findOrFail($id);

        // Check if hub is primary
        if ($hub->is_primary) {
            return back()->with('error', 'Cannot delete primary hub. Set another hub as primary first.');
        }

        $hub->delete();

        return redirect()->route('company.hubs.index')
            ->with('success', 'Hub deleted successfully!');
    }
}
