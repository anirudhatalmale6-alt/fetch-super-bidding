<?php

namespace App\Http\Controllers\Api\V1\Interstate;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Request\Request;
use App\Models\Interstate\RequestPackage;
use App\Models\Interstate\TrackingUpdate;
use App\Models\Interstate\InspectionPhoto;
use App\Services\Interstate\FinalCostCalculationService;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Contract\Database;
use App\Jobs\Notifications\SendPushNotification;

class InspectionController extends BaseController
{
    public function __construct(
        private Database $database,
        private FinalCostCalculationService $costService
    ) {}

    /**
     * Goods Intake - Search for order by ID, Goods ID, or Tracking ID
     * 
     * POST /api/v1/interstate/trucking/goods-intake/search
     */
    public function searchForIntake(HttpRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'required|string|min:3',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('You are not associated with a trucking company', 403);
        }

        $searchTerm = $request->input('search');

        // Search by request_number, package_number, or tracking ID
        $requests = Request::with(['packages', 'userDetail', 'originHub'])
            ->where('delivery_mode', 'interstate')
            ->where(function ($query) use ($searchTerm) {
                $query->where('request_number', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('id', 'LIKE', "%{$searchTerm}%")
                    ->orWhereHas('packages', function ($q) use ($searchTerm) {
                        $q->where('package_number', 'LIKE', "%{$searchTerm}%");
                    });
            })
            ->where('trucking_company_id', $company->id)
            ->whereIn('status', ['company_assigned', 'picked_up', 'at_origin_hub'])
            ->limit(10)
            ->get();

        return $this->respondSuccess([
            'results' => $requests->map(fn($req) => [
                'request_id' => $req->id,
                'request_number' => $req->request_number,
                'status' => $req->status,
                'inspection_status' => $req->inspection_status,
                'customer_name' => $req->userDetail->name ?? 'Unknown',
                'customer_phone' => $req->userDetail->phone ?? null,
                'origin_hub' => $req->originHub->hub_name ?? null,
                'package_count' => $req->packages->count(),
                'estimated_details' => $req->packages->map(fn($pkg) => [
                    'package_number' => $pkg->package_number,
                    'estimated_weight' => $pkg->estimated_weight_kg,
                    'estimated_dimensions' => "{$pkg->estimated_length_cm} × {$pkg->estimated_width_cm} × {$pkg->estimated_height_cm}",
                ]),
            ]),
        ]);
    }

    /**
     * Start Inspection Process
     * 
     * POST /api/v1/interstate/trucking/inspection/start/{requestId}
     */
    public function startInspection(string $requestId)
    {
        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('Unauthorized', 403);
        }

        $interstateRequest = Request::where('id', $requestId)
            ->where('trucking_company_id', $company->id)
            ->where('delivery_mode', 'interstate')
            ->first();

        if (!$interstateRequest) {
            return $this->respondError('Request not found', 404);
        }

        if (!in_array($interstateRequest->inspection_status, ['not_required', 'awaiting_inspection'])) {
            return $this->respondError('Inspection cannot be started for this request', 422);
        }

        try {
            DB::transaction(function () use ($interstateRequest) {
                $interstateRequest->update([
                    'inspection_status' => 'inspection_in_progress',
                    'inspection_started_at' => now(),
                ]);

                // Create tracking update
                TrackingUpdate::createStatusChange(
                    requestId: $interstateRequest->id,
                    previousStatus: $interstateRequest->inspection_status,
                    newStatus: 'inspection_in_progress',
                    message: 'Physical inspection started at hub',
                    createdById: auth()->id(),
                    createdByType: 'trucking_company'
                );
            });

            return $this->respondSuccess([
                'request_id' => $interstateRequest->id,
                'inspection_status' => 'inspection_in_progress',
                'started_at' => now(),
            ], 'Inspection started successfully');

        } catch (\Exception $e) {
            return $this->respondError('Failed to start inspection: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload Inspection Photo with Measurements
     * 
     * POST /api/v1/interstate/trucking/inspection/photo
     */
    public function uploadInspectionPhoto(HttpRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required|exists:requests,id',
            'package_id' => 'required|exists:request_packages,id',
            'photo' => 'required|image|max:10240', // Max 10MB
            'photo_type' => 'required|in:weight_measurement,dimension_check,condition_check,label_scan,package_overview',
            'description' => 'nullable|string|max:500',
            'recorded_weight' => 'nullable|numeric|min:0',
            'recorded_length' => 'nullable|numeric|min:0',
            'recorded_width' => 'nullable|numeric|min:0',
            'recorded_height' => 'nullable|numeric|min:0',
            'hub_id' => 'nullable|exists:trucking_hubs,id',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('Unauthorized', 403);
        }

        $interstateRequest = Request::where('id', $request->input('request_id'))
            ->where('trucking_company_id', $company->id)
            ->first();

        if (!$interstateRequest) {
            return $this->respondError('Request not found', 404);
        }

        try {
            // Store photo
            $photoPath = $request->file('photo')->store(
                "inspections/{$interstateRequest->id}",
                'public'
            );

            $photoUrl = asset('storage/' . $photoPath);

            // Create inspection photo record
            $photo = InspectionPhoto::recordMeasurement(
                requestId: $interstateRequest->id,
                packageId: $request->input('package_id'),
                photoUrl: $photoUrl,
                photoType: $request->input('photo_type'),
                measurements: [
                    'weight' => $request->input('recorded_weight'),
                    'length' => $request->input('recorded_length'),
                    'width' => $request->input('recorded_width'),
                    'height' => $request->input('recorded_height'),
                ],
                takenById: auth()->id(),
                takenByName: auth()->user()->name,
                hubId: $request->input('hub_id'),
                description: $request->input('description')
            );

            return $this->respondSuccess([
                'photo_id' => $photo->id,
                'photo_url' => $photoUrl,
                'photo_type' => $photo->photo_type,
            ], 'Photo uploaded successfully');

        } catch (\Exception $e) {
            return $this->respondError('Failed to upload photo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Submit Final Measurements
     * 
     * POST /api/v1/interstate/trucking/inspection/measurements
     */
    public function submitFinalMeasurements(HttpRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required|exists:requests,id',
            'packages' => 'required|array|min:1',
            'packages.*.package_id' => 'required|exists:request_packages,id',
            'packages.*.final_weight_kg' => 'required|numeric|min:0.1',
            'packages.*.final_length_cm' => 'required|numeric|min:1',
            'packages.*.final_width_cm' => 'required|numeric|min:1',
            'packages.*.final_height_cm' => 'required|numeric|min:1',
            'packages.*.final_declared_value' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('Unauthorized', 403);
        }

        $interstateRequest = Request::with('packages')
            ->where('id', $request->input('request_id'))
            ->where('trucking_company_id', $company->id)
            ->first();

        if (!$interstateRequest) {
            return $this->respondError('Request not found', 404);
        }

        try {
            DB::transaction(function () use ($interstateRequest, $request) {
                foreach ($request->input('packages') as $pkgData) {
                    $package = $interstateRequest->packages
                        ->where('id', $pkgData['package_id'])
                        ->first();

                    if (!$package) {
                        throw new \Exception("Package {$pkgData['package_id']} not found in request");
                    }

                    // Calculate discrepancy
                    $estimatedWeight = $package->estimated_weight_kg ?? $package->actual_weight_kg;
                    $weightDiscrepancyPercent = $estimatedWeight > 0 
                        ? (($pkgData['final_weight_kg'] - $estimatedWeight) / $estimatedWeight) * 100 
                        : 0;

                    // Calculate final volumetric weight
                    $volumetricWeight = $this->calculateVolumetricWeight(
                        $pkgData['final_length_cm'],
                        $pkgData['final_width_cm'],
                        $pkgData['final_height_cm']
                    );

                    $chargeableWeight = max($pkgData['final_weight_kg'], $volumetricWeight);

                    // Update package
                    $package->update([
                        'final_weight_kg' => $pkgData['final_weight_kg'],
                        'final_length_cm' => $pkgData['final_length_cm'],
                        'final_width_cm' => $pkgData['final_width_cm'],
                        'final_height_cm' => $pkgData['final_height_cm'],
                        'final_declared_value' => $pkgData['final_declared_value'] ?? $package->declared_value,
                        'final_volumetric_weight_kg' => $volumetricWeight,
                        'final_chargeable_weight_kg' => $chargeableWeight,
                        'weight_discrepancy_percent' => $weightDiscrepancyPercent,
                    ]);
                }

                $interstateRequest->update([
                    'inspection_completed_at' => now(),
                ]);
            });

            return $this->respondSuccess([
                'request_id' => $interstateRequest->id,
                'packages_updated' => count($request->input('packages')),
                'next_step' => 'submit_final_cost',
            ], 'Measurements recorded successfully');

        } catch (\Exception $e) {
            return $this->respondError('Failed to save measurements: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Submit Final Cost
     * 
     * POST /api/v1/interstate/trucking/inspection/final-cost
     */
    public function submitFinalCost(HttpRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required|exists:requests,id',
            'final_transportation_fee' => 'required|numeric|min:0',
            'final_insurance_fee' => 'nullable|numeric|min:0',
            'estimated_delivery_hours' => 'required|integer|min:1|max:720',
            'remarks' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('Unauthorized', 403);
        }

        $interstateRequest = Request::with(['packages', 'acceptedBid'])
            ->where('id', $request->input('request_id'))
            ->where('trucking_company_id', $company->id)
            ->first();

        if (!$interstateRequest) {
            return $this->respondError('Request not found', 404);
        }

        $transportationFee = $request->input('final_transportation_fee');
        $insuranceFee = $request->input('final_insurance_fee', 0);
        $finalTotal = $transportationFee + $insuranceFee;

        try {
            DB::transaction(function () use ($interstateRequest, $request, $transportationFee, $insuranceFee, $finalTotal) {
                // Get initial bid amount
                $initialBidAmount = $interstateRequest->acceptedBid?->total_bid_amount ?? 0;

                // Calculate price difference
                $priceDifference = $finalTotal - $initialBidAmount;
                $priceDifferencePercent = $initialBidAmount > 0 
                    ? ($priceDifference / $initialBidAmount) * 100 
                    : 0;

                // Update request
                $interstateRequest->update([
                    'inspection_status' => 'awaiting_user_approval',
                    'approval_status' => 'pending',
                    'final_transportation_fee' => $transportationFee,
                    'final_insurance_fee' => $insuranceFee,
                    'final_total_amount' => $finalTotal,
                    'initial_bid_amount' => $initialBidAmount,
                    'price_difference' => $priceDifference,
                    'price_difference_percent' => $priceDifferencePercent,
                    'final_cost_remarks' => $request->input('remarks'),
                    'final_cost_submitted_at' => now(),
                    'user_approval_deadline' => now()->addHours(48), // 48 hours to approve
                ]);

                // Create tracking update
                TrackingUpdate::createStatusChange(
                    requestId: $interstateRequest->id,
                    previousStatus: 'inspection_in_progress',
                    newStatus: 'awaiting_user_approval',
                    message: 'Final cost submitted. Awaiting customer approval.',
                    createdById: auth()->id(),
                    createdByType: 'trucking_company'
                );

                // Sync to Firebase
                $this->syncFinalCostToFirebase($interstateRequest);
            });

            // Notify user
            $this->notifyUserOfFinalCost($interstateRequest);

            return $this->respondSuccess([
                'request_id' => $interstateRequest->id,
                'status' => 'awaiting_user_approval',
                'final_total' => $finalTotal,
                'price_difference' => $interstateRequest->price_difference,
                'approval_deadline' => $interstateRequest->user_approval_deadline,
            ], 'Final cost submitted successfully. Awaiting user approval.');

        } catch (\Exception $e) {
            return $this->respondError('Failed to submit final cost: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get Inspection Details
     * 
     * GET /api/v1/interstate/trucking/inspection/{requestId}
     */
    public function getInspectionDetails(string $requestId)
    {
        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('Unauthorized', 403);
        }

        $interstateRequest = Request::with(['packages', 'inspectionPhotos', 'trackingUpdates'])
            ->where('id', $requestId)
            ->where('trucking_company_id', $company->id)
            ->first();

        if (!$interstateRequest) {
            return $this->respondError('Request not found', 404);
        }

        return $this->respondSuccess([
            'request_id' => $interstateRequest->id,
            'inspection_status' => $interstateRequest->inspection_status,
            'approval_status' => $interstateRequest->approval_status,
            'packages' => $interstateRequest->packages->map(fn($pkg) => [
                'package_id' => $pkg->id,
                'package_number' => $pkg->package_number,
                'estimated' => [
                    'weight' => $pkg->estimated_weight_kg,
                    'length' => $pkg->estimated_length_cm,
                    'width' => $pkg->estimated_width_cm,
                    'height' => $pkg->estimated_height_cm,
                    'declared_value' => $pkg->estimated_declared_value,
                ],
                'final' => [
                    'weight' => $pkg->final_weight_kg,
                    'length' => $pkg->final_length_cm,
                    'width' => $pkg->final_width_cm,
                    'height' => $pkg->final_height_cm,
                    'declared_value' => $pkg->final_declared_value,
                    'chargeable_weight' => $pkg->final_chargeable_weight_kg,
                ],
                'discrepancy_percent' => $pkg->weight_discrepancy_percent,
            ]),
            'photos' => $interstateRequest->inspectionPhotos->map(fn($photo) => [
                'photo_id' => $photo->id,
                'photo_url' => $photo->photo_url,
                'photo_type' => $photo->photo_type,
                'description' => $photo->description,
                'measurements' => $photo->dimensions,
                'taken_at' => $photo->taken_at,
            ]),
            'final_cost' => [
                'initial_bid' => $interstateRequest->initial_bid_amount,
                'final_transportation' => $interstateRequest->final_transportation_fee,
                'final_insurance' => $interstateRequest->final_insurance_fee,
                'final_total' => $interstateRequest->final_total_amount,
                'difference' => $interstateRequest->price_difference,
                'difference_percent' => $interstateRequest->price_difference_percent,
                'remarks' => $interstateRequest->final_cost_remarks,
            ],
        ]);
    }

    /**
     * Calculate volumetric weight
     */
    private function calculateVolumetricWeight(float $length, float $width, float $height): float
    {
        $divisor = config('interstate.volumetric_divisor', 5000);
        return ($length * $width * $height) / $divisor;
    }

    /**
     * Sync final cost to Firebase
     */
    private function syncFinalCostToFirebase(Request $interstateRequest): void
    {
        $this->database
            ->getReference("interstate-requests/{$interstateRequest->id}/final_cost")
            ->set([
                'status' => 'awaiting_user_approval',
                'final_total' => $interstateRequest->final_total_amount,
                'initial_bid' => $interstateRequest->initial_bid_amount,
                'difference' => $interstateRequest->price_difference,
                'difference_percent' => $interstateRequest->price_difference_percent,
                'submitted_at' => now()->timestamp * 1000,
                'approval_deadline' => $interstateRequest->user_approval_deadline->timestamp * 1000,
            ]);
    }

    /**
     * Notify user of final cost
     */
    private function notifyUserOfFinalCost(Request $interstateRequest): void
    {
        $user = $interstateRequest->userDetail;
        
        $title = trans('push_notifications.final_cost_ready_title', [], $user->lang);
        $body = trans('push_notifications.final_cost_ready_body', [
            'request_number' => $interstateRequest->request_number,
            'amount' => number_format($interstateRequest->final_total_amount, 2),
        ], $user->lang);

        $pushData = [
            'type' => 'final_cost_ready',
            'request_id' => $interstateRequest->id,
            'final_amount' => $interstateRequest->final_total_amount,
        ];

        dispatch(new SendPushNotification($user, $title, $body, $pushData));
    }

    protected function respondWithValidationErrors($validator)
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }
}
