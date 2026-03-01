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
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'Company not found.');
        }

        $routes = $company->supportedRoutes()
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
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'Company not found.');
        }

        return view('company.routes.create', compact('company'));
    }

    /**
     * Store new route
     */
    public function store(Request $request)
    {
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;

        if (!$company) {
            return back()->with('error', 'Company not found.');
        }

        $validator = Validator::make($request->all(), [
            'origin_state' => 'required|string|max:100',
            'origin_city' => 'required|string|max:100',
            'destination_state' => 'required|string|max:100',
            'destination_city' => 'required|string|max:100',
            'route_code' => 'required|string|max:50|unique:supported_routes,route_code',
            'base_price' => 'required|numeric|min:0',
            'price_per_km' => 'nullable|numeric|min:0',
            'estimated_days' => 'required|integer|min:1',
            'distance_km' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $company->supportedRoutes()->create($request->all());

        return redirect()->route('company.routes.index')
            ->with('success', 'Route created successfully!');
    }

    /**
     * Show edit route form
     */
    public function edit($id)
    {
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;

        $route = $company->supportedRoutes()->findOrFail($id);

        return view('company.routes.edit', compact('route', 'company'));
    }

    /**
     * Update route
     */
    public function update(Request $request, $id)
    {
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;

        $route = $company->supportedRoutes()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'origin_state' => 'required|string|max:100',
            'origin_city' => 'required|string|max:100',
            'destination_state' => 'required|string|max:100',
            'destination_city' => 'required|string|max:100',
            'route_code' => 'required|string|max:50|unique:supported_routes,route_code,' . $id,
            'base_price' => 'required|numeric|min:0',
            'price_per_km' => 'nullable|numeric|min:0',
            'estimated_days' => 'required|integer|min:1',
            'distance_km' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $route->update($request->all());

        return redirect()->route('company.routes.index')
            ->with('success', 'Route updated successfully!');
    }

    /**
     * Delete route
     */
    public function destroy($id)
    {
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;

        $route = $company->supportedRoutes()->findOrFail($id);

        $route->delete();

        return redirect()->route('company.routes.index')
            ->with('success', 'Route deleted successfully!');
    }
}
