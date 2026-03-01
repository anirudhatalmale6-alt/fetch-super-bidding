<?php

namespace App\Http\Controllers\Api\V1\Interstate;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Interstate\InterstateBid;
use App\Models\Interstate\TruckingCompany;
use App\Models\Request\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Contract\Database;
use App\Jobs\Notifications\SendPushNotification;
use App\Base\Constants\Masters\PushEnums;

class InterstateBiddingController extends BaseController
{
    private ?Database $database;

    public function __construct(?Database $database = null)
    {
        $this->database = $database;
    }

    /**
     * Submit a bid for an interstate request (Company Side)
     * 
     * POST /api/v1/interstate/bids/submit
     */
    public function submitBid(HttpRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required|exists:requests,id',
            'transportation_fee' => 'required|numeric|min:0',
            'insurance_fee' => 'nullable|numeric|min:0',
            'estimated_delivery_hours' => 'required|integer|min:1|max:720',
            'bid_notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('You are not associated with a trucking company', 403);
        }

        $interstateRequest = Request::where('id', $request->input('request_id'))
            ->where('delivery_mode', 'interstate')
            ->where('is_completed', false)
            ->where('is_cancelled', false)
            ->first();

        if (!$interstateRequest) {
            return $this->respondError('Request not found or not available for bidding', 404);
        }

        // Check for existing bid from this company
        $existingBid = InterstateBid::forRequest($interstateRequest->id)
            ->byCompany($company->id)
            ->pending()
            ->first();

        if ($existingBid) {
            return $this->respondError('You already have an active bid for this request. Please update your existing bid.', 422);
        }

        try {
            $bid = InterstateBid::create([
                'request_id' => $interstateRequest->id,
                'trucking_company_id' => $company->id,
                'transportation_fee' => $request->input('transportation_fee'),
                'insurance_fee' => $request->input('insurance_fee', 0),
                'estimated_delivery_hours' => $request->input('estimated_delivery_hours'),
                'bid_notes' => $request->input('bid_notes'),
                'status' => 'pending',
                'expires_at' => now()->addHours(24),
            ]);

            // Update Firebase for real-time updates (non-critical)
            try {
                $this->syncBidToFirebase($bid);
            } catch (\Exception $e) {
                \Log::warning('Firebase sync failed for bid submission: ' . $e->getMessage());
            }

            // Notify user about new bid (non-critical)
            try {
                $this->notifyUserOfNewBid($interstateRequest, $bid);
            } catch (\Exception $e) {
                \Log::warning('Push notification failed for new bid: ' . $e->getMessage());
            }

            return $this->respondSuccess([
                'bid_id' => $bid->id,
                'total_amount' => $bid->total_bid_amount,
                'status' => $bid->status,
                'expires_at' => $bid->expires_at,
            ], 'Bid submitted successfully');

        } catch (\Exception $e) {
            return $this->respondError('Failed to submit bid: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an existing bid (Company Side)
     * 
     * POST /api/v1/interstate/bids/update/{bidId}
     */
    public function updateBid(string $bidId, HttpRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'transportation_fee' => 'required|numeric|min:0',
            'insurance_fee' => 'nullable|numeric|min:0',
            'estimated_delivery_hours' => 'required|integer|min:1|max:720',
            'bid_notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('You are not associated with a trucking company', 403);
        }

        $bid = InterstateBid::byCompany($company->id)
            ->pending()
            ->find($bidId);

        if (!$bid) {
            return $this->respondError('Bid not found or cannot be updated', 404);
        }

        if (!$bid->canBeRevised()) {
            return $this->respondError('This bid cannot be revised', 422);
        }

        try {
            // Mark current bid as revised and create new version
            $bid->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);

            $newBid = InterstateBid::create([
                'request_id' => $bid->request_id,
                'trucking_company_id' => $company->id,
                'transportation_fee' => $request->input('transportation_fee'),
                'insurance_fee' => $request->input('insurance_fee', 0),
                'estimated_delivery_hours' => $request->input('estimated_delivery_hours'),
                'bid_notes' => $request->input('bid_notes'),
                'status' => 'pending',
                'is_revised' => true,
                'original_bid_id' => $bid->original_bid_id ?? $bid->id,
                'expires_at' => now()->addHours(24),
            ]);

            // Update Firebase
            $this->syncBidToFirebase($newBid);
            $this->removeBidFromFirebase($bid);

            // Notify user about bid update
            $this->notifyUserOfBidUpdate($bid->request, $newBid);

            return $this->respondSuccess([
                'bid_id' => $newBid->id,
                'total_amount' => $newBid->total_bid_amount,
                'status' => $newBid->status,
                'is_revised' => true,
            ], 'Bid updated successfully');

        } catch (\Exception $e) {
            return $this->respondError('Failed to update bid: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Withdraw a bid (Company Side)
     * 
     * POST /api/v1/interstate/bids/withdraw/{bidId}
     */
    public function withdrawBid(string $bidId)
    {
        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('You are not associated with a trucking company', 403);
        }

        $bid = InterstateBid::byCompany($company->id)
            ->pending()
            ->find($bidId);

        if (!$bid) {
            return $this->respondError('Bid not found', 404);
        }

        if (!$bid->canBeWithdrawn()) {
            return $this->respondError('This bid cannot be withdrawn', 422);
        }

        try {
            $bid->update([
                'status' => 'withdrawn',
                'withdrawn_at' => now(),
            ]);

            // Remove from Firebase
            $this->removeBidFromFirebase($bid);

            return $this->respondSuccess([], 'Bid withdrawn successfully');

        } catch (\Exception $e) {
            return $this->respondError('Failed to withdraw bid: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all bids for a request (User Side)
     * 
     * GET /api/v1/interstate/bids/request/{requestId}
     */
    public function getBidsForRequest(string $requestId)
    {
        $interstateRequest = Request::where('id', $requestId)
            ->where('user_id', auth()->id())
            ->where('delivery_mode', 'interstate')
            ->first();

        if (!$interstateRequest) {
            return $this->respondError('Request not found', 404);
        }

        $bids = InterstateBid::forRequest($requestId)
            ->with('truckingCompany')
            ->active()
            ->get()
            ->map(function ($bid) {
                return [
                    'bid_id' => $bid->id,
                    'company' => [
                        'id' => $bid->truckingCompany->id,
                        'name' => $bid->truckingCompany->company_name,
                        'logo' => $bid->truckingCompany->logo,
                        'rating' => $bid->truckingCompany->rating,
                    ],
                    'transportation_fee' => $bid->transportation_fee,
                    'insurance_fee' => $bid->insurance_fee,
                    'total_cost' => $bid->total_bid_amount,
                    'estimated_delivery_hours' => $bid->estimated_delivery_hours,
                    'estimated_delivery_time' => $bid->getFormattedDeliveryTime(),
                    'bid_notes' => $bid->bid_notes,
                    'is_revised' => $bid->is_revised,
                    'created_at' => $bid->created_at,
                    'expires_at' => $bid->expires_at,
                ];
            });

        return $this->respondSuccess([
            'request_id' => $requestId,
            'total_bids' => $bids->count(),
            'bids' => $bids,
        ]);
    }

    /**
     * Accept a bid (User Side)
     * 
     * POST /api/v1/interstate/bids/accept/{bidId}
     */
    public function acceptBid(string $bidId)
    {
        $bid = InterstateBid::with(['request', 'truckingCompany'])
            ->active()
            ->find($bidId);

        if (!$bid) {
            return $this->respondError('Bid not found or expired', 404);
        }

        // Verify user owns the request
        if ($bid->request->user_id !== auth()->id()) {
            return $this->respondError('Unauthorized', 403);
        }

        try {
            // Accept this bid
            $bid->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            // Reject all other pending bids for this request
            InterstateBid::forRequest($bid->request_id)
                ->pending()
                ->where('id', '!=', $bidId)
                ->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                ]);

            // Update request with accepted bid info
            $bid->request->update([
                'trucking_company_id' => $bid->trucking_company_id,
                'accepted_ride_fare' => $bid->total_bid_amount,
                'accepted_at' => now(),
            ]);

            // Try to update Firebase (non-critical, don't fail if Firebase is unavailable)
            try {
                $this->syncBidAcceptanceToFirebase($bid);
            } catch (\Exception $e) {
                \Log::warning('Firebase sync failed for bid acceptance: ' . $e->getMessage());
            }

            // Notify company (non-critical)
            try {
                $this->notifyCompanyOfBidAcceptance($bid);
            } catch (\Exception $e) {
                \Log::warning('Push notification failed for bid acceptance: ' . $e->getMessage());
            }

            return $this->respondSuccess([
                'bid_id' => $bid->id,
                'company_name' => $bid->truckingCompany->company_name,
                'total_amount' => $bid->total_bid_amount,
                'next_step' => 'payment',
                'tracking_status' => 'awaiting_pickup',
            ], 'Bid accepted successfully');

        } catch (\Exception $e) {
            return $this->respondError('Failed to accept bid: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get company's bids (Company Side)
     * 
     * GET /api/v1/interstate/bids/company
     */
    public function getCompanyBids(HttpRequest $request)
    {
        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('You are not associated with a trucking company', 403);
        }

        $status = $request->input('status', 'pending');
        $perPage = $request->input('per_page', 10);

        $query = InterstateBid::byCompany($company->id)
            ->with('request');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $bids = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->respondSuccess([
            'data' => $bids->map(function ($bid) {
                return [
                    'bid_id' => $bid->id,
                    'request' => [
                        'id' => $bid->request->id,
                        'request_number' => $bid->request->request_number,
                        'origin' => $bid->request->originHub->city ?? 'Unknown',
                        'destination' => $bid->request->destinationHub->city ?? 'Unknown',
                    ],
                    'total_amount' => $bid->total_bid_amount,
                    'status' => $bid->status,
                    'created_at' => $bid->created_at,
                    'expires_at' => $bid->expires_at,
                ];
            }),
            'pagination' => [
                'current_page' => $bids->currentPage(),
                'last_page' => $bids->lastPage(),
                'per_page' => $bids->perPage(),
                'total' => $bids->total(),
            ],
        ]);
    }

    /**
     * Sync bid to Firebase for real-time updates
     */
    private function syncBidToFirebase(InterstateBid $bid)
    {
        if (!$this->database) return;

        $bidData = [
            'bid_id' => $bid->id,
            'company_id' => $bid->trucking_company_id,
            'company_name' => $bid->truckingCompany->company_name,
            'company_rating' => $bid->truckingCompany->rating,
            'transportation_fee' => $bid->transportation_fee,
            'insurance_fee' => $bid->insurance_fee,
            'total_amount' => $bid->total_bid_amount,
            'estimated_delivery_hours' => $bid->estimated_delivery_hours,
            'status' => $bid->status,
            'created_at' => $bid->created_at->timestamp * 1000,
            'updated_at' => now()->timestamp * 1000,
        ];

        $this->database
            ->getReference("interstate-bids/{$bid->request_id}/bids/company_{$bid->trucking_company_id}")
            ->set($bidData);
    }

    /**
     * Remove bid from Firebase
     */
    private function removeBidFromFirebase(InterstateBid $bid)
    {
        if (!$this->database) return;

        $this->database
            ->getReference("interstate-bids/{$bid->request_id}/bids/company_{$bid->trucking_company_id}")
            ->remove();
    }

    /**
     * Sync bid acceptance to Firebase
     */
    private function syncBidAcceptanceToFirebase(InterstateBid $bid, ?string $packageId = null)
    {
        if (!$this->database) return;

        $updateData = [
            'accepted_bid_id' => $bid->id,
            'status' => 'accepted',
            'company_id' => $bid->trucking_company_id,
            'updated_at' => now()->timestamp * 1000,
        ];

        if ($packageId) {
            $updateData['package_id'] = $packageId;
            $updateData['package_goods_id'] = $packageId;
        }

        $this->database
            ->getReference("interstate-bids/{$bid->request_id}")
            ->update($updateData);

        // Also update the request reference with package info for real-time tracking
        if ($packageId) {
            $this->database
                ->getReference("requests/{$bid->request_id}")
                ->update([
                    'package_id' => $packageId,
                    'tracking_status' => 'awaiting_pickup',
                    'company_assigned_at' => now()->timestamp * 1000,
                ]);
        }
    }

    /**
     * Notify user of new bid
     */
    private function notifyUserOfNewBid(Request $request, InterstateBid $bid)
    {
        $user = $request->userDetail;
        
        $title = trans('push_notifications.new_interstate_bid_title', [], $user->lang);
        $body = trans('push_notifications.new_interstate_bid_body', [
            'company' => $bid->truckingCompany->company_name,
            'amount' => $bid->total_bid_amount,
        ], $user->lang);

        dispatch(new SendPushNotification($user, $title, $body));
    }

    /**
     * Notify user of bid update
     */
    private function notifyUserOfBidUpdate(Request $request, InterstateBid $bid)
    {
        $user = $request->userDetail;
        
        $title = trans('push_notifications.interstate_bid_updated_title', [], $user->lang);
        $body = trans('push_notifications.interstate_bid_updated_body', [
            'company' => $bid->truckingCompany->company_name,
        ], $user->lang);

        dispatch(new SendPushNotification($user, $title, $body));
    }

    /**
     * Notify company of bid acceptance
     */
    private function notifyCompanyOfBidAcceptance(InterstateBid $bid)
    {
        $company = $bid->truckingCompany;
        $user = $company->user;
        
        $title = trans('push_notifications.bid_accepted_title', [], $user->lang);
        $body = trans('push_notifications.bid_accepted_body', [
            'request_number' => $bid->request->request_number,
        ], $user->lang);

        dispatch(new SendPushNotification($user, $title, $body));
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
