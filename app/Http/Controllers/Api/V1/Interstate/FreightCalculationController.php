<?php

namespace App\Http\Controllers\Api\V1\Interstate;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\Interstate\DimensionalPricingService;
use App\Models\Interstate\SupportedRoute;
use App\Models\Interstate\TruckingHub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FreightCalculationController extends BaseController
{
    public function __construct(
        private DimensionalPricingService $pricingService
    ) {}

    /**
     * Calculate freight quote with dimensional pricing
     * 
     * POST /api/v1/interstate/freight/quote
     */
    public function calculateQuote(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'origin_city' => 'required|string',
            'destination_city' => 'required|string',
            'packages' => 'required|array|min:1',
            'packages.*.actual_weight_kg' => 'required|numeric|min:0.1',
            'packages.*.length_cm' => 'required|numeric|min:1',
            'packages.*.width_cm' => 'required|numeric|min:1',
            'packages.*.height_cm' => 'required|numeric|min:1',
            'packages.*.quantity' => 'integer|min:1|max:100',
            'packages.*.is_fragile' => 'boolean',
            'packages.*.declared_value' => 'numeric|min:0',
            'service_type' => 'in:standard,express',
            'requires_insurance' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        try {
            $packages = $request->input('packages');
            $originCity = $request->input('origin_city');
            $destinationCity = $request->input('destination_city');
            $serviceType = $request->input('service_type', 'standard');
            $requiresInsurance = $request->input('requires_insurance', false);

            // Find available routes
            $routes = $this->findAvailableRoutes($originCity, $destinationCity);

            if ($routes->isEmpty()) {
                return $this->respondError(
                    'No interstate service available for this route',
                    404
                );
            }

            // Validate packages against routes and calculate pricing
            $results = [];
            $eligibleRoutes = collect();

            foreach ($routes as $route) {
                // Validate all packages against route limits
                $validationErrors = [];
                $allValid = true;

                foreach ($packages as $index => $package) {
                    $validation = $this->pricingService->validatePackageAgainstRoute($package, $route);
                    if (!$validation->isValid) {
                        $allValid = false;
                        $validationErrors[] = [
                            'package_index' => $index + 1,
                            'errors' => $validation->errors
                        ];
                    }
                }

                if (!$allValid) {
                    $results[] = [
                        'route' => $this->formatRouteSummary($route),
                        'eligible' => false,
                        'validation_errors' => $validationErrors
                    ];
                    continue;
                }

                // Calculate freight for this route
                $options = [
                    'is_express' => $serviceType === 'express',
                    'is_fragile' => collect($packages)->contains('is_fragile', true),
                    'requires_insurance' => $requiresInsurance,
                    'declared_value' => collect($packages)->sum('declared_value'),
                ];

                // Process packages with route's volumetric divisor
                $processedPackages = collect($packages)->map(function ($pkg) use ($route) {
                    return $this->pricingService->processPackage($pkg, $route->getVolumetricDivisor());
                });

                $pricing = $this->pricingService->calculateTotalFreight(
                    $processedPackages,
                    $route,
                    $options
                );

                $eligibleRoutes->push([
                    'route' => $route,
                    'pricing' => $pricing,
                    'processed_packages' => $processedPackages,
                ]);

                $results[] = [
                    'route' => $this->formatRouteSummary($route),
                    'eligible' => true,
                    'pricing' => $pricing->getSummary(),
                    'breakdown' => $pricing->breakdown,
                ];
            }

            // Sort by price (lowest first)
            $eligibleRoutes = $eligibleRoutes->sortBy('pricing.totalPrice');
            $results = collect($results)->sortBy(function ($item) {
                return $item['eligible'] ? $item['pricing']['total'] : PHP_FLOAT_MAX;
            })->values();

            // Get best option
            $bestOption = $eligibleRoutes->first();

            return $this->respondSuccess([
                'origin_city' => $originCity,
                'destination_city' => $destinationCity,
                'service_type' => $serviceType,
                'packages_count' => count($packages),
                'options' => $results,
                'recommended' => $bestOption ? [
                    'route_id' => $bestOption['route']->id,
                    'trucking_company' => [
                        'id' => $bestOption['route']->truckingCompany->id,
                        'name' => $bestOption['route']->truckingCompany->company_name,
                        'rating' => $bestOption['route']->truckingCompany->rating,
                    ],
                    'route' => $this->formatRouteSummary($bestOption['route']),
                    'pricing' => $bestOption['pricing']->getSummary(),
                    'eta_hours' => $serviceType === 'express' 
                        ? $bestOption['route']->express_sla_hours 
                        : $bestOption['route']->standard_sla_hours,
                ] : null,
                'has_eligible_routes' => $eligibleRoutes->isNotEmpty(),
            ]);

        } catch (\Exception $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }

    /**
     * Validate package dimensions and weight against route limits
     * 
     * POST /api/v1/interstate/freight/validate
     */
    public function validatePackages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'origin_city' => 'required|string',
            'destination_city' => 'required|string',
            'packages' => 'required|array|min:1',
            'packages.*.actual_weight_kg' => 'required|numeric|min:0.1',
            'packages.*.length_cm' => 'required|numeric|min:1',
            'packages.*.width_cm' => 'required|numeric|min:1',
            'packages.*.height_cm' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $packages = $request->input('packages');
        $originCity = $request->input('origin_city');
        $destinationCity = $request->input('destination_city');

        // Find available routes
        $routes = $this->findAvailableRoutes($originCity, $destinationCity);

        if ($routes->isEmpty()) {
            return $this->respondError('No routes available for this corridor', 404);
        }

        $validationResults = [];

        foreach ($routes as $route) {
            $routeValidation = [
                'route' => $this->formatRouteSummary($route),
                'is_eligible' => true,
                'packages' => [],
            ];

            foreach ($packages as $index => $package) {
                $validation = $this->pricingService->validatePackageAgainstRoute($package, $route);
                
                $routeValidation['packages'][] = [
                    'package_index' => $index + 1,
                    'is_valid' => $validation->isValid,
                    'errors' => $validation->errors,
                ];

                if (!$validation->isValid) {
                    $routeValidation['is_eligible'] = false;
                }
            }

            $validationResults[] = $routeValidation;
        }

        // Check if any route is fully eligible
        $hasEligibleRoute = collect($validationResults)->contains('is_eligible', true);

        return $this->respondSuccess([
            'origin_city' => $originCity,
            'destination_city' => $destinationCity,
            'packages_count' => count($packages),
            'routes_checked' => $routes->count(),
            'has_eligible_route' => $hasEligibleRoute,
            'validation_results' => $validationResults,
        ]);
    }

    /**
     * Get dimensional weight calculation explanation
     * 
     * POST /api/v1/interstate/freight/calculate-volumetric
     */
    public function calculateVolumetric(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'length_cm' => 'required|numeric|min:0.1',
            'width_cm' => 'required|numeric|min:0.1',
            'height_cm' => 'required|numeric|min:0.1',
            'actual_weight_kg' => 'required|numeric|min:0.1',
            'volumetric_divisor' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $length = $request->input('length_cm');
        $width = $request->input('width_cm');
        $height = $request->input('height_cm');
        $actualWeight = $request->input('actual_weight_kg');
        $divisor = $request->input('volumetric_divisor', DimensionalPricingService::DIVISOR_STANDARD);

        $volumetricWeight = $this->pricingService->calculateVolumetricWeight(
            $length,
            $width,
            $height,
            $divisor
        );

        $chargeableWeight = $this->pricingService->calculateChargeableWeight(
            $actualWeight,
            $volumetricWeight
        );

        $volume = $length * $width * $height;

        return $this->respondSuccess([
            'input' => [
                'dimensions' => [
                    'length' => $length,
                    'width' => $width,
                    'height' => $height,
                ],
                'actual_weight_kg' => $actualWeight,
                'volumetric_divisor' => $divisor,
            ],
            'calculations' => [
                'volume_cubic_cm' => $volume,
                'formula' => "({$length} × {$width} × {$height}) / {$divisor}",
                'volumetric_weight_kg' => $volumetricWeight,
                'chargeable_weight_kg' => $chargeableWeight,
                'weight_type_used' => $actualWeight >= $volumetricWeight ? 'actual' : 'volumetric',
            ],
            'explanation' => [
                'volumetric_weight' => 'The weight calculated based on package dimensions',
                'chargeable_weight' => 'The greater of actual weight or volumetric weight',
                'divisor_note' => $divisor == 5000 
                    ? 'Standard road freight divisor (5000)' 
                    : ($divisor == 6000 ? 'Air freight divisor (6000)' : 'Custom divisor'),
            ],
        ]);
    }

    /**
     * Get available routes between cities
     * 
     * GET /api/v1/interstate/freight/routes
     */
    public function getAvailableRoutes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'origin_city' => 'required|string',
            'destination_city' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $routes = $this->findAvailableRoutes(
            $request->input('origin_city'),
            $request->input('destination_city')
        );

        return $this->respondSuccess([
            'origin_city' => $request->input('origin_city'),
            'destination_city' => $request->input('destination_city'),
            'routes_count' => $routes->count(),
            'routes' => $routes->map(function ($route) {
                return [
                    'id' => $route->id,
                    'route_code' => $route->route_code,
                    'trucking_company' => [
                        'id' => $route->truckingCompany->id,
                        'name' => $route->truckingCompany->company_name,
                        'rating' => $route->truckingCompany->rating,
                    ],
                    'origin_hub' => [
                        'id' => $route->originHub->id,
                        'name' => $route->originHub->hub_name,
                        'city' => $route->originHub->city,
                    ],
                    'destination_hub' => [
                        'id' => $route->destinationHub->id,
                        'name' => $route->destinationHub->hub_name,
                        'city' => $route->destinationHub->city,
                    ],
                    'distance_km' => $route->distance_km,
                    'estimated_duration_hours' => $route->estimated_duration_hours,
                    'pricing' => [
                        'price_per_kg' => $route->price_per_kg,
                        'minimum_charge' => $route->minimum_charge,
                        'volumetric_divisor' => $route->getVolumetricDivisor(),
                    ],
                    'limits' => [
                        'max_weight_kg' => $route->getMaxWeight(),
                        'max_dimensions_cm' => $route->getMaxDimensionsArray(),
                    ],
                    'sla' => [
                        'standard_hours' => $route->standard_sla_hours,
                        'express_hours' => $route->express_sla_hours,
                    ],
                ];
            }),
        ]);
    }

    /**
     * Find available routes between cities
     */
    private function findAvailableRoutes(string $originCity, string $destinationCity)
    {
        return SupportedRoute::with(['truckingCompany', 'originHub', 'destinationHub'])
            ->where('is_active', true)
            ->whereHas('originHub', function ($query) use ($originCity) {
                $query->where('city', 'LIKE', "%{$originCity}%")
                    ->where('is_active', true);
            })
            ->whereHas('destinationHub', function ($query) use ($destinationCity) {
                $query->where('city', 'LIKE', "%{$destinationCity}%")
                    ->where('is_active', true);
            })
            ->whereHas('truckingCompany', function ($query) {
                $query->where('status', 'active');
            })
            ->get();
    }

    /**
     * Format route for response
     */
    private function formatRouteSummary(SupportedRoute $route): array
    {
        return [
            'id' => $route->id,
            'route_code' => $route->route_code,
            'display' => $route->getRouteDisplayAttribute(),
            'origin' => [
                'hub_name' => $route->originHub->hub_name,
                'city' => $route->originHub->city,
            ],
            'destination' => [
                'hub_name' => $route->destinationHub->hub_name,
                'city' => $route->destinationHub->city,
            ],
            'trucking_company' => $route->truckingCompany->company_name,
            'distance_km' => $route->distance_km,
        ];
    }

    /**
     * Respond with validation errors
     */
    protected function respondWithValidationErrors($validator)
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }
}
