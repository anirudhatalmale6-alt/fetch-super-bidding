<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Interstate\TruckingCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TruckingCompanyAdminController extends Controller
{
    /**
     * Show form to create new trucking company
     */
    public function create()
    {
        return view('admin.interstate.companies.create');
    }

    /**
     * Store new trucking company
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'registration_number' => 'required|string|unique:trucking_companies',
            'email' => 'required|email|unique:trucking_companies',
            'phone' => 'required|string',
            'company_type' => 'required|in:last_mile,interstate_trucking,both',
            'fleet_size' => 'nullable|integer|min:0',
            'service_types' => 'nullable|array',
            'operating_states' => 'nullable|array',
            'default_price_per_kg' => 'nullable|numeric|min:0',
            'insurance_rate_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Create trucking company with company_type
        $company = TruckingCompany::create([
            'company_name' => $request->input('company_name'),
            'registration_number' => $request->input('registration_number'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'company_type' => $request->input('company_type'),
            'fleet_size' => $request->input('fleet_size', 0),
            'service_types' => $request->input('service_types', []),
            'operating_states' => $request->input('operating_states', []),
            'default_price_per_kg' => $request->input('default_price_per_kg', 1000),
            'insurance_rate_percent' => $request->input('insurance_rate_percent', 1.0),
            'status' => 'pending', // Requires approval
            'slug' => \Str::slug($request->input('company_name') . '-' . uniqid()),
        ]);

        // Log admin action
        \App\Models\Admin\AdminActionLog::log(
            auth()->id(),
            'company_created',
            \App\Models\Admin\AdminActionLog::CATEGORY_COMPANY,
            "Company {$company->company_name} created with type: {$request->input('company_type')}",
            [
                'target_type' => 'company',
                'target_id' => $company->id,
                'new_state' => $company->toArray(),
            ]
        );

        return redirect()->route('admin.interstate.companies.index')
            ->with('success', 'Trucking company created successfully. Status: Pending Approval');
    }

    /**
     * Show form to edit trucking company
     */
    public function edit(int $id)
    {
        $company = TruckingCompany::findOrFail($id);
        return view('admin.interstate.companies.edit', compact('company'));
    }

    /**
     * Update trucking company
     */
    public function update(Request $request, int $id)
    {
        $company = TruckingCompany::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'registration_number' => 'required|string|unique:trucking_companies,registration_number,' . $id,
            'email' => 'required|email|unique:trucking_companies,email,' . $id,
            'phone' => 'required|string',
            'company_type' => 'required|in:last_mile,interstate_trucking,both',
            'fleet_size' => 'nullable|integer|min:0',
            'service_types' => 'nullable|array',
            'operating_states' => 'nullable|array',
            'status' => 'nullable|in:active,pending,suspended',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $oldType = $company->company_type;

        $company->update([
            'company_name' => $request->input('company_name'),
            'registration_number' => $request->input('registration_number'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'company_type' => $request->input('company_type'),
            'fleet_size' => $request->input('fleet_size', $company->fleet_size),
            'service_types' => $request->input('service_types', $company->service_types),
            'operating_states' => $request->input('operating_states', $company->operating_states),
            'status' => $request->input('status', $company->status),
        ]);

        // Log if company type changed
        if ($oldType !== $request->input('company_type')) {
            \App\Models\Admin\AdminActionLog::log(
                auth()->id(),
                'company_type_updated',
                \App\Models\Admin\AdminActionLog::CATEGORY_COMPANY,
                "Company type changed from {$oldType} to {$request->input('company_type')}",
                [
                    'target_type' => 'company',
                    'target_id' => $company->id,
                    'previous_state' => ['company_type' => $oldType],
                    'new_state' => ['company_type' => $request->input('company_type')],
                ]
            );
        }

        return back()->with('success', 'Company updated successfully');
    }

    /**
     * List all trucking companies
     */
    public function index(Request $request)
    {
        $query = TruckingCompany::withCount(['routes', 'hubs'])
            ->with('user')
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('company_type')) {
            $query->where('company_type', $request->input('company_type'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('registration_number', 'LIKE', "%{$search}%");
            });
        }

        $companies = $query->paginate(20);

        return view('admin.interstate.companies.index', compact('companies'));
    }

    /**
     * Show company details
     */
    public function show(int $id)
    {
        $company = TruckingCompany::with([
            'hubs',
            'routes.originHub',
            'routes.destinationHub',
            'user',
        ])->findOrFail($id);

        // Get company statistics
        $stats = [
            'total_orders' => \App\Models\Request\Request::where('trucking_company_id', $id)->count(),
            'completed_orders' => \App\Models\Request\Request::where('trucking_company_id', $id)
                ->where('status', 'completed')->count(),
            'active_orders' => \App\Models\Request\Request::where('trucking_company_id', $id)
                ->whereIn('status', ['pending', 'confirmed', 'in_transit'])->count(),
            'total_revenue' => \App\Models\Interstate\RequestLeg::where('provider_id', $id)
                ->where('provider_type', TruckingCompany::class)
                ->where('status', 'completed')
                ->sum('provider_earnings') ?? 0,
        ];

        return view('admin.interstate.companies.show', compact('company', 'stats'));
    }

    /**
     * Approve company
     */
    public function approve(Request $request, int $id)
    {
        $company = TruckingCompany::findOrFail($id);

        $company->update([
            'status' => 'active',
        ]);

        // Log admin action
        \App\Models\Admin\AdminActionLog::log(
            auth()->id(),
            'company_approved',
            \App\Models\Admin\AdminActionLog::CATEGORY_COMPANY,
            "Company {$company->company_name} approved",
            [
                'target_type' => 'company',
                'target_id' => $company->id,
            ]
        );

        return back()->with('success', 'Company approved successfully');
    }

    /**
     * Blacklist company
     */
    public function blacklist(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $company = TruckingCompany::findOrFail($id);

        $company->update([
            'status' => 'suspended',
        ]);

        // Log admin action
        \App\Models\Admin\AdminActionLog::logCompanyBlacklist(
            auth()->id(),
            $company->id,
            $request->input('reason')
        );

        return back()->with('success', 'Company blacklisted successfully');
    }

    /**
     * Remove from blacklist
     */
    public function unblacklist(Request $request, int $id)
    {
        $company = TruckingCompany::findOrFail($id);

        $company->update([
            'status' => 'active',
        ]);

        // Log admin action
        \App\Models\Admin\AdminActionLog::log(
            auth()->id(),
            'company_unblacklisted',
            \App\Models\Admin\AdminActionLog::CATEGORY_COMPANY,
            "Company {$company->company_name} removed from blacklist",
            [
                'target_type' => 'company',
                'target_id' => $company->id,
            ]
        );

        return back()->with('success', 'Company removed from blacklist');
    }

    /**
     * Update company commission rate
     */
    public function updateCommission(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'commission_rate' => 'required|numeric|min:0|max:100',
            'reason' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $company = TruckingCompany::findOrFail($id);
        $oldRate = $company->commission_rate;
        $newRate = $request->input('commission_rate');

        $company->update([
            'commission_rate' => $newRate,
        ]);

        // Log admin action
        \App\Models\Admin\AdminActionLog::log(
            auth()->id(),
            'commission_update',
            \App\Models\Admin\AdminActionLog::CATEGORY_COMPANY,
            "Commission rate updated from {$oldRate}% to {$newRate}%",
            [
                'target_type' => 'company',
                'target_id' => $company->id,
                'previous_state' => ['commission_rate' => $oldRate],
                'new_state' => ['commission_rate' => $newRate],
                'reason' => $request->input('reason'),
            ]
        );

        return back()->with('success', 'Commission rate updated successfully');
    }
}
