<?php

namespace App\Http\Controllers\Web\Company;

use App\Http\Controllers\Web\BaseController;
use App\Models\Interstate\SupportedRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RouteController extends BaseController
{
    /**
     * Display list of routes
     */
    public function index()
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'Company not found.');
        }

        $routes = $company->routes()
            ->orderBy('origin_state')
            ->orderBy('destination_state')
            ->paginate(20);

        return view('company.routes.index', compact('routes', 'company'));
    }

    /**
     * Show create route form
     */
    public function create()
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'Company not found.');
        }

        $hubs = $company->hubs()->where('is_active', true)->orderBy('name')->get();

        return view('company.routes.create', compact('company', 'hubs'));
    }

    /**
     * Store new route
     */
    public function store(Request $request)
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        if (!$company) {
            return back()->with('error', 'Company not found.');
        }

        $validator = Validator::make($request->all(), [
            'origin_state' => 'required|string|max:100',
            'origin_city' => 'required|string|max:100',
            'destination_state' => 'required|string|max:100',
            'destination_city' => 'required|string|max:100',
            'origin_hub_id' => 'nullable|exists:trucking_hubs,id',
            'destination_hub_id' => 'nullable|exists:trucking_hubs,id',
            'base_rate_per_kg' => 'required|numeric|min:0',
            'minimum_charge' => 'required|numeric|min:0',
            'estimated_days' => 'required|integer|min:1',
            'distance_km' => 'nullable|numeric|min:0',
            'express_multiplier' => 'nullable|numeric|min:1',
            'fragile_surcharge_percent' => 'nullable|numeric|min:0|max:100',
            'insurance_rate_percent' => 'nullable|numeric|min:0|max:100',
            'max_weight_kg' => 'nullable|numeric|min:0',
            'is_express_available' => 'boolean',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $company->routes()->create(array_merge(
            $request->only([
                'origin_state', 'origin_city', 'destination_state', 'destination_city',
                'origin_hub_id', 'destination_hub_id', 'base_rate_per_kg', 'minimum_charge',
                'estimated_days', 'distance_km', 'express_multiplier', 'fragile_surcharge_percent',
                'insurance_rate_percent', 'max_weight_kg', 'notes',
            ]),
            [
                'is_active' => $request->boolean('is_active', true),
                'is_express_available' => $request->boolean('is_express_available'),
            ]
        ));

        return redirect()->route('company.routes.index')
            ->with('success', 'Route created successfully!');
    }

    /**
     * Show edit route form
     */
    public function edit($id)
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        $route = $company->routes()->findOrFail($id);

        $hubs = $company->hubs()->where('is_active', true)->orderBy('name')->get();

        return view('company.routes.edit', compact('route', 'company', 'hubs'));
    }

    /**
     * Update route
     */
    public function update(Request $request, $id)
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        $route = $company->routes()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'origin_state' => 'required|string|max:100',
            'origin_city' => 'required|string|max:100',
            'destination_state' => 'required|string|max:100',
            'destination_city' => 'required|string|max:100',
            'origin_hub_id' => 'nullable|exists:trucking_hubs,id',
            'destination_hub_id' => 'nullable|exists:trucking_hubs,id',
            'base_rate_per_kg' => 'required|numeric|min:0',
            'minimum_charge' => 'required|numeric|min:0',
            'estimated_days' => 'required|integer|min:1',
            'distance_km' => 'nullable|numeric|min:0',
            'express_multiplier' => 'nullable|numeric|min:1',
            'fragile_surcharge_percent' => 'nullable|numeric|min:0|max:100',
            'insurance_rate_percent' => 'nullable|numeric|min:0|max:100',
            'max_weight_kg' => 'nullable|numeric|min:0',
            'is_express_available' => 'boolean',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $route->update(array_merge(
            $request->only([
                'origin_state', 'origin_city', 'destination_state', 'destination_city',
                'origin_hub_id', 'destination_hub_id', 'base_rate_per_kg', 'minimum_charge',
                'estimated_days', 'distance_km', 'express_multiplier', 'fragile_surcharge_percent',
                'insurance_rate_percent', 'max_weight_kg', 'notes',
            ]),
            [
                'is_active' => $request->boolean('is_active', true),
                'is_express_available' => $request->boolean('is_express_available'),
            ]
        ));

        return redirect()->route('company.routes.index')
            ->with('success', 'Route updated successfully!');
    }

    /**
     * Delete route
     */
    public function destroy($id)
    {
        $owner = auth('web')->user()->owner;
        $company = $owner->truckingCompany;

        $route = $company->routes()->findOrFail($id);

        $route->delete();

        return redirect()->route('company.routes.index')
            ->with('success', 'Route deleted successfully!');
    }
}
