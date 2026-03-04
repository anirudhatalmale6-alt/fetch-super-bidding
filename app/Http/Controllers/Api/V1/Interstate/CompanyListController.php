<?php

namespace App\Http\Controllers\Api\V1\Interstate;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Interstate\TruckingCompany;
use App\Models\Interstate\TruckingHub;
use Illuminate\Http\Request;

class CompanyListController extends BaseController
{
    /**
     * List available trucking companies for an interstate route.
     *
     * GET /api/v1/interstate/companies?origin_state=Lagos&destination_state=Abuja
     *
     * User selects a company from this list before creating the request.
     */
    public function getAvailableCompanies(Request $request)
    {
        $request->validate([
            'origin_state' => 'required|string|max:100',
            'destination_state' => 'required|string|max:100',
        ]);

        $originState = trim($request->origin_state);
        $destinationState = trim($request->destination_state);

        if (strtolower($originState) === strtolower($destinationState)) {
            return $this->respondError('Origin and destination must be different states.', 422);
        }

        // Find companies that have hubs in both origin and destination states
        $companies = TruckingCompany::active()
            ->whereHas('hubs', function ($q) use ($originState) {
                $q->where('is_active', true)
                  ->where(function ($q2) use ($originState) {
                      $q2->where('state', 'LIKE', "%{$originState}%")
                         ->orWhere('city', 'LIKE', "%{$originState}%");
                  });
            })
            ->whereHas('hubs', function ($q) use ($destinationState) {
                $q->where('is_active', true)
                  ->where(function ($q2) use ($destinationState) {
                      $q2->where('state', 'LIKE', "%{$destinationState}%")
                         ->orWhere('city', 'LIKE', "%{$destinationState}%");
                  });
            })
            ->with(['hubs' => function ($q) use ($originState, $destinationState) {
                $q->where('is_active', true)
                  ->where(function ($q2) use ($originState, $destinationState) {
                      $q2->where('state', 'LIKE', "%{$originState}%")
                         ->orWhere('state', 'LIKE', "%{$destinationState}%")
                         ->orWhere('city', 'LIKE', "%{$originState}%")
                         ->orWhere('city', 'LIKE', "%{$destinationState}%");
                  });
            }])
            ->get();

        // Also include companies that have active routes matching these states
        $routeCompanyIds = \App\Models\Interstate\SupportedRoute::where('is_active', true)
            ->where(function ($q) use ($originState) {
                $q->where('origin_state', 'LIKE', "%{$originState}%")
                  ->orWhere('origin_city', 'LIKE', "%{$originState}%");
            })
            ->where(function ($q) use ($destinationState) {
                $q->where('destination_state', 'LIKE', "%{$destinationState}%")
                  ->orWhere('destination_city', 'LIKE', "%{$destinationState}%");
            })
            ->pluck('trucking_company_id');

        if ($routeCompanyIds->isNotEmpty()) {
            $routeCompanies = TruckingCompany::active()
                ->whereIn('id', $routeCompanyIds)
                ->whereNotIn('id', $companies->pluck('id'))
                ->with('hubs')
                ->get();
            $companies = $companies->merge($routeCompanies);
        }

        if ($companies->isEmpty()) {
            return $this->respondSuccess([
                'companies' => [],
                'message' => 'No trucking companies available for this route.',
            ]);
        }

        return $this->respondSuccess([
            'origin_state' => $originState,
            'destination_state' => $destinationState,
            'total_companies' => $companies->count(),
            'companies' => $companies->map(function ($company) use ($originState, $destinationState) {
                // Find the origin and destination hubs
                $originHub = $company->hubs->first(function ($hub) use ($originState) {
                    return stripos($hub->state, $originState) !== false
                        || stripos($hub->city, $originState) !== false;
                });
                $destHub = $company->hubs->first(function ($hub) use ($destinationState) {
                    return stripos($hub->state, $destinationState) !== false
                        || stripos($hub->city, $destinationState) !== false;
                });

                return [
                    'id' => $company->id,
                    'company_name' => $company->company_name,
                    'logo' => $company->logo,
                    'rating' => $company->rating,
                    'phone' => $company->phone,
                    'insurance_rate_percent' => $company->insurance_rate_percent,
                    'origin_hub' => $originHub ? [
                        'id' => $originHub->id,
                        'name' => $originHub->hub_name,
                        'address' => $originHub->address,
                        'city' => $originHub->city,
                        'state' => $originHub->state,
                        'lat' => $originHub->latitude,
                        'lng' => $originHub->longitude,
                    ] : null,
                    'destination_hub' => $destHub ? [
                        'id' => $destHub->id,
                        'name' => $destHub->hub_name,
                        'address' => $destHub->address,
                        'city' => $destHub->city,
                        'state' => $destHub->state,
                        'lat' => $destHub->latitude,
                        'lng' => $destHub->longitude,
                    ] : null,
                ];
            })->values(),
        ]);
    }
}
