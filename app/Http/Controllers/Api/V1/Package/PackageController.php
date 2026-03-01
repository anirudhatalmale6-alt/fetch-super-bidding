<?php

namespace App\Http\Controllers\Api\V1\Package;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Company\Package;
use App\Models\Company\PackageTracking;
use App\Models\Company\PackagePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Package API Controller
 * 
 * Handles package management endpoints for mobile app users.
 * This allows users to track their packages, view costs, and make payments.
 */
class PackageController extends BaseController
{
    /**
     * List all packages for the authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->respondUnauthorized('Authentication required');
        }

        $query = Package::forUser($user->id)
            ->with(['company', 'trackingLogs', 'payments'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by goods_id
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('goods_id', 'like', "%{$search}%")
                  ->orWhere('origin', 'like', "%{$search}%")
                  ->orWhere('destination', 'like', "%{$search}%");
            });
        }

        $packages = $query->paginate($request->per_page ?? 15);

        return $this->respondSuccess([
            'packages' => $packages->map(function ($package) {
                return $this->formatPackageForResponse($package);
            }),
            'pagination' => [
                'current_page' => $packages->currentPage(),
                'last_page' => $packages->lastPage(),
                'per_page' => $packages->perPage(),
                'total' => $packages->total(),
            ],
            'summary' => [
                'total_packages' => Package::forUser($user->id)->count(),
                'active_packages' => Package::forUser($user->id)->pending()->count(),
                'completed_packages' => Package::forUser($user->id)->completed()->count(),
            ]
        ]);
    }

    /**
     * Get details of a specific package
     *
     * @param string $goodsId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($goodsId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->respondUnauthorized('Authentication required');
        }

        $package = Package::forUser($user->id)
            ->byGoodsId($goodsId)
            ->with(['company', 'trackingLogs' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }, 'payments'])
            ->first();

        if (!$package) {
            return $this->respondNotFound('Package not found');
        }

        return $this->respondSuccess([
            'package' => $this->formatPackageForResponse($package, true)
        ]);
    }

    /**
     * Get tracking timeline for a package
     *
     * @param string $goodsId
     * @return \Illuminate\Http\JsonResponse
     */
    public function tracking($goodsId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->respondUnauthorized('Authentication required');
        }

        $package = Package::forUser($user->id)
            ->byGoodsId($goodsId)
            ->first();

        if (!$package) {
            return $this->respondNotFound('Package not found');
        }

        $trackingLogs = PackageTracking::where('goods_id', $goodsId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'note' => $log->note,
                    'cost_added' => $log->cost_added,
                    'insurance_added' => $log->insurance_added,
                    'created_at' => $log->created_at->toIso8601String(),
                    'created_by' => $log->createdBy ? $log->createdBy->name : 'System',
                ];
            });

        return $this->respondSuccess([
            'goods_id' => $goodsId,
            'current_status' => $package->status,
            'current_status_label' => $package->getStatusLabel(),
            'timeline' => $trackingLogs,
        ]);
    }

    /**
     * Get payment summary for a package
     *
     * @param string $goodsId
     * @return \Illuminate\Http\JsonResponse
     */
    public function paymentSummary($goodsId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->respondUnauthorized('Authentication required');
        }

        $package = Package::forUser($user->id)
            ->byGoodsId($goodsId)
            ->with('payments')
            ->first();

        if (!$package) {
            return $this->respondNotFound('Package not found');
        }

        $payments = $package->payments;
        
        $insurancePayments = $payments->where('cost_type', 'insurance');
        $transportPayments = $payments->where('cost_type', 'transportation');

        return $this->respondSuccess([
            'goods_id' => $goodsId,
            'summary' => [
                'insurance_cost' => $package->insurance_cost ?? 0,
                'transportation_cost' => $package->transportation_cost ?? 0,
                'total_cost' => $package->total_cost ?? 0,
                'total_paid' => $payments->where('status', 'paid')->sum('amount'),
                'balance_due' => max(0, ($package->total_cost ?? 0) - $payments->where('status', 'paid')->sum('amount')),
                'requires_payment' => $package->requiresPayment() && !$package->isFullyPaid(),
                'is_fully_paid' => $package->isFullyPaid(),
            ],
            'insurance' => [
                'total' => $package->insurance_cost ?? 0,
                'paid' => $insurancePayments->where('status', 'paid')->sum('amount'),
                'pending' => $insurancePayments->where('status', 'pending')->sum('amount'),
                'payments' => $insurancePayments->map(function ($payment) {
                    return $this->formatPaymentForResponse($payment);
                }),
            ],
            'transportation' => [
                'total' => $package->transportation_cost ?? 0,
                'paid' => $transportPayments->where('status', 'paid')->sum('amount'),
                'pending' => $transportPayments->where('status', 'pending')->sum('amount'),
                'payments' => $transportPayments->map(function ($payment) {
                    return $this->formatPaymentForResponse($payment);
                }),
            ],
        ]);
    }

    /**
     * Initiate payment for a package
     *
     * @param Request $request
     * @param string $goodsId
     * @return \Illuminate\Http\JsonResponse
     */
    public function initiatePayment(Request $request, $goodsId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->respondUnauthorized('Authentication required');
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:card,bank_transfer,wallet',
            'cost_type' => 'required|in:insurance,transportation,both',
        ]);

        if ($validator->fails()) {
            return $this->respondValidationErrors($validator);
        }

        $package = Package::forUser($user->id)
            ->byGoodsId($goodsId)
            ->with('payments')
            ->first();

        if (!$package) {
            return $this->respondNotFound('Package not found');
        }

        // Calculate amount to pay based on cost_type
        $amount = 0;
        $paymentsToProcess = [];

        if (in_array($request->cost_type, ['insurance', 'both'])) {
            $insurancePending = $package->payments
                ->where('cost_type', 'insurance')
                ->where('status', 'pending')
                ->sum('amount');
            $amount += $insurancePending;
            $paymentsToProcess = array_merge($paymentsToProcess, 
                $package->payments
                    ->where('cost_type', 'insurance')
                    ->where('status', 'pending')
                    ->pluck('id')
                    ->toArray()
            );
        }

        if (in_array($request->cost_type, ['transportation', 'both'])) {
            $transportPending = $package->payments
                ->where('cost_type', 'transportation')
                ->where('status', 'pending')
                ->sum('amount');
            $amount += $transportPending;
            $paymentsToProcess = array_merge($paymentsToProcess,
                $package->payments
                    ->where('cost_type', 'transportation')
                    ->where('status', 'pending')
                    ->pluck('id')
                    ->toArray()
            );
        }

        if ($amount <= 0) {
            return $this->respondError('No pending payments for this package');
        }

        // Here you would integrate with your payment gateway
        // For now, return the payment details for the app to process
        return $this->respondSuccess([
            'goods_id' => $goodsId,
            'amount' => $amount,
            'currency' => 'NGN',
            'payment_method' => $request->payment_method,
            'cost_type' => $request->cost_type,
            'payment_reference' => 'PKG-' . $goodsId . '-' . time(),
            'payments_to_process' => $paymentsToProcess,
            'package_details' => [
                'origin' => $package->origin,
                'destination' => $package->destination,
                'status' => $package->status,
            ],
        ], 'Payment initiated. Complete payment through your selected method.');
    }

    /**
     * Confirm payment for a package
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmPayment(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->respondUnauthorized('Authentication required');
        }

        $validator = Validator::make($request->all(), [
            'goods_id' => 'required|string',
            'payment_reference' => 'required|string',
            'transaction_id' => 'required|string',
            'payment_method' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->respondValidationErrors($validator);
        }

        $package = Package::forUser($user->id)
            ->byGoodsId($request->goods_id)
            ->with('payments')
            ->first();

        if (!$package) {
            return $this->respondNotFound('Package not found');
        }

        // Update pending payments to paid
        $pendingPayments = $package->payments->where('status', 'pending');
        
        foreach ($pendingPayments as $payment) {
            $payment->markAsPaid($request->payment_method, $request->transaction_id);
        }

        return $this->respondSuccess([
            'goods_id' => $request->goods_id,
            'payment_status' => 'paid',
            'total_paid' => $package->fresh()->payments->where('status', 'paid')->sum('amount'),
            'is_fully_paid' => $package->fresh()->isFullyPaid(),
        ], 'Payment confirmed successfully');
    }

    /**
     * Get package statistics for the user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics()
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->respondUnauthorized('Authentication required');
        }

        $baseQuery = Package::forUser($user->id);

        $stats = [
            'total' => $baseQuery->count(),
            'awaiting_pickup' => (clone $baseQuery)->where('status', 'awaiting_pickup')->count(),
            'in_transit' => (clone $baseQuery)->whereIn('status', ['picked_up', 'in_transit', 'out_for_delivery'])->count(),
            'delivered' => (clone $baseQuery)->where('status', 'delivered')->count(),
            'cancelled' => (clone $baseQuery)->where('status', 'cancelled')->count(),
            'pending_payment' => (clone $baseQuery)
                ->whereRaw('total_cost > 0')
                ->whereHas('payments', function ($q) {
                    $q->where('status', 'pending');
                })->count(),
            'total_spent' => PackagePayment::whereHas('package', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })->where('status', 'paid')->sum('amount'),
        ];

        // Recent activity
        $recentActivity = $baseQuery
            ->with(['trackingLogs' => function ($q) {
                $q->latest()->limit(1);
            }])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($package) {
                $latestUpdate = $package->trackingLogs->first();
                return [
                    'goods_id' => $package->goods_id,
                    'status' => $package->status,
                    'status_label' => $package->getStatusLabel(),
                    'latest_update' => $latestUpdate ? $latestUpdate->note : 'Package created',
                    'updated_at' => $latestUpdate ? $latestUpdate->created_at->toIso8601String() : $package->created_at->toIso8601String(),
                ];
            });

        return $this->respondSuccess([
            'statistics' => $stats,
            'recent_activity' => $recentActivity,
        ]);
    }

    /**
     * Format package for API response
     *
     * @param Package $package
     * @param bool $includeDetails
     * @return array
     */
    private function formatPackageForResponse(Package $package, bool $includeDetails = false): array
    {
        $data = [
            'id' => $package->id,
            'goods_id' => $package->goods_id,
            'origin' => $package->origin,
            'destination' => $package->destination,
            'origin_address' => $package->origin_address,
            'destination_address' => $package->destination_address,
            'status' => $package->status,
            'status_label' => $package->getStatusLabel(),
            'status_badge_class' => $package->getStatusBadgeClass(),
            'insurance_cost' => $package->insurance_cost ?? 0,
            'transportation_cost' => $package->transportation_cost ?? 0,
            'total_cost' => $package->total_cost ?? 0,
            'requires_payment' => $package->requiresPayment() && !$package->isFullyPaid(),
            'is_fully_paid' => $package->isFullyPaid(),
            'company' => $package->company ? [
                'id' => $package->company->id,
                'name' => $package->company->company_name ?? $package->company->name,
            ] : null,
            'created_at' => $package->created_at->toIso8601String(),
            'updated_at' => $package->updated_at->toIso8601String(),
        ];

        if ($includeDetails) {
            $data['description'] = $package->description;
            $data['weight_kg'] = $package->weight_kg;
            $data['dimensions'] = $package->dimensions;
            $data['tracking_logs'] = $package->trackingLogs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'note' => $log->note,
                    'cost_added' => $log->cost_added,
                    'created_at' => $log->created_at->toIso8601String(),
                ];
            });
            $data['payments'] = $package->payments->map(function ($payment) {
                return $this->formatPaymentForResponse($payment);
            });
        }

        return $data;
    }

    /**
     * Format payment for API response
     *
     * @param PackagePayment $payment
     * @return array
     */
    private function formatPaymentForResponse(PackagePayment $payment): array
    {
        return [
            'id' => $payment->id,
            'cost_type' => $payment->cost_type,
            'cost_type_label' => $payment->cost_type === 'insurance' ? 'Insurance' : 'Transportation',
            'amount' => $payment->amount,
            'status' => $payment->status,
            'payment_method' => $payment->payment_method,
            'transaction_id' => $payment->transaction_id,
            'paid_at' => $payment->paid_at ? $payment->paid_at->toIso8601String() : null,
            'created_at' => $payment->created_at->toIso8601String(),
        ];
    }
}
