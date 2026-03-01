<?php

namespace App\Services\Interstate\Payment;

use App\Models\Request\Request;
use App\Models\Interstate\RequestLeg;
use App\Models\Interstate\LegPayment;
use App\Base\Payment\PaymentGateway\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling multi-leg payment collection
 * Manages payments per leg with weight verification adjustments
 */
class MultiLegPaymentService
{
    public function __construct(
        private PaymentGatewayManager $paymentManager
    ) {}

    /**
     * Initialize payment tracking for a new interstate request
     * Creates leg payment records for each leg
     */
    public function initializeRequestPayments(Request $request): void
    {
        DB::transaction(function () use ($request) {
            $legs = $request->legs()->orderBy('leg_number')->get();
            
            foreach ($legs as $leg) {
                // Skip legs with 0 fare (hub handoffs)
                if ($leg->final_fare <= 0) {
                    continue;
                }
                
                LegPayment::create([
                    'request_id' => $request->id,
                    'request_leg_id' => $leg->id,
                    'leg_number' => $leg->leg_number,
                    'leg_type' => $leg->leg_type,
                    'original_amount' => $leg->final_fare,
                    'adjusted_amount' => $leg->final_fare,
                    'paid_amount' => 0,
                    'balance_due' => $leg->final_fare,
                    'payment_status' => $leg->leg_number === 1 ? 'pending' : 'awaiting_leg_completion',
                    'currency' => 'NGN',
                ]);
            }
            
            Log::info("Payment tracking initialized for request {$request->request_number}");
        });
    }

    /**
     * Create an additional payment request after weight verification
     * When verified weight is higher than estimated
     */
    public function createAdditionalPaymentRequest(Request $request, RequestLeg $leg, float $additionalAmount): LegPayment
    {
        return DB::transaction(function () use ($request, $leg, $additionalAmount) {
            
            // Find existing leg payment
            $legPayment = LegPayment::where('request_leg_id', $leg->id)->first();
            
            if ($legPayment) {
                // Update existing payment record
                $legPayment->update([
                    'adjusted_amount' => $legPayment->original_amount + $additionalAmount,
                    'balance_due' => $additionalAmount,
                    'payment_status' => 'additional_payment_required',
                    'adjustment_reason' => 'weight_verification_increase',
                    'adjustment_details' => [
                        'original_amount' => $legPayment->original_amount,
                        'additional_amount' => $additionalAmount,
                        'new_total' => $legPayment->original_amount + $additionalAmount,
                    ],
                ]);
            } else {
                // Create new payment record
                $legPayment = LegPayment::create([
                    'request_id' => $request->id,
                    'request_leg_id' => $leg->id,
                    'leg_number' => $leg->leg_number,
                    'leg_type' => $leg->leg_type,
                    'original_amount' => $leg->final_fare,
                    'adjusted_amount' => $leg->final_fare,
                    'paid_amount' => 0,
                    'balance_due' => $additionalAmount,
                    'payment_status' => 'additional_payment_required',
                    'adjustment_reason' => 'weight_verification_increase',
                    'currency' => 'NGN',
                ]);
            }
            
            Log::info("Additional payment created for request {$request->request_number}, leg {$leg->leg_number}", [
                'additional_amount' => $additionalAmount,
                'total_adjusted' => $legPayment->adjusted_amount,
            ]);
            
            return $legPayment;
        });
    }

    /**
     * Create a refund record when verified weight is lower than estimated
     */
    public function createRefund(Request $request, RequestLeg $leg, float $refundAmount): LegPayment
    {
        return DB::transaction(function () use ($request, $leg, $refundAmount) {
            
            $legPayment = LegPayment::where('request_leg_id', $leg->id)->first();
            
            if ($legPayment) {
                $legPayment->update([
                    'adjusted_amount' => $legPayment->original_amount - $refundAmount,
                    'refund_amount' => $refundAmount,
                    'payment_status' => 'refund_pending',
                    'adjustment_reason' => 'weight_verification_decrease',
                    'adjustment_details' => [
                        'original_amount' => $legPayment->original_amount,
                        'refund_amount' => $refundAmount,
                        'new_total' => $legPayment->original_amount - $refundAmount,
                    ],
                ]);
            } else {
                $legPayment = LegPayment::create([
                    'request_id' => $request->id,
                    'request_leg_id' => $leg->id,
                    'leg_number' => $leg->leg_number,
                    'leg_type' => $leg->leg_type,
                    'original_amount' => $leg->final_fare,
                    'adjusted_amount' => $leg->final_fare - $refundAmount,
                    'paid_amount' => $leg->final_fare,
                    'refund_amount' => $refundAmount,
                    'balance_due' => 0,
                    'payment_status' => 'refund_pending',
                    'adjustment_reason' => 'weight_verification_decrease',
                    'currency' => 'NGN',
                ]);
            }
            
            // Initiate refund through payment gateway
            $this->processRefund($legPayment, $refundAmount);
            
            Log::info("Refund created for request {$request->request_number}, leg {$leg->leg_number}", [
                'refund_amount' => $refundAmount,
            ]);
            
            return $legPayment;
        });
    }

    /**
     * Process payment for a specific leg
     * Returns payment URL or confirmation
     */
    public function processLegPayment(Request $request, int $legNumber, string $paymentMethod, array $paymentData = []): array
    {
        $legPayment = LegPayment::where('request_id', $request->id)
            ->where('leg_number', $legNumber)
            ->firstOrFail();
        
        if (!in_array($legPayment->payment_status, ['pending', 'additional_payment_required'])) {
            throw new \InvalidArgumentException('Payment not required for this leg');
        }
        
        $amount = $legPayment->balance_due;
        
        // Create payment intent through gateway
        $paymentResult = $this->paymentManager->charge([
            'amount' => $amount,
            'currency' => 'NGN',
            'payment_method' => $paymentMethod,
            'metadata' => [
                'request_id' => $request->id,
                'request_number' => $request->request_number,
                'leg_number' => $legNumber,
                'leg_type' => $legPayment->leg_type,
                'payment_type' => $legPayment->payment_status,
            ],
        ], $paymentData);
        
        // Update leg payment record
        $legPayment->update([
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentResult['reference'] ?? null,
            'payment_status' => $paymentResult['status'] === 'success' ? 'paid' : 'processing',
            'transaction_id' => $paymentResult['transaction_id'] ?? null,
        ]);
        
        Log::info("Leg payment processed for request {$request->request_number}, leg {$legNumber}", [
            'amount' => $amount,
            'status' => $paymentResult['status'],
        ]);
        
        return $paymentResult;
    }

    /**
     * Confirm payment received (webhook callback)
     */
    public function confirmPayment(string $paymentReference, array $paymentDetails): void
    {
        $legPayment = LegPayment::where('payment_reference', $paymentReference)->first();
        
        if (!$legPayment) {
            Log::error("Leg payment not found for reference: {$paymentReference}");
            return;
        }
        
        DB::transaction(function () use ($legPayment, $paymentDetails) {
            $paidAmount = $paymentDetails['amount'] ?? $legPayment->balance_due;
            
            $legPayment->update([
                'paid_amount' => $legPayment->paid_amount + $paidAmount,
                'balance_due' => max(0, $legPayment->balance_due - $paidAmount),
                'payment_status' => 'paid',
                'paid_at' => now(),
                'payment_details' => array_merge($legPayment->payment_details ?? [], $paymentDetails),
            ]);
            
            // Update the leg status to allow progression
            $leg = $legPayment->requestLeg;
            if ($leg && $leg->status === 'pending_payment') {
                $leg->update(['status' => 'payment_received']);
            }
            
            Log::info("Payment confirmed for leg {$legPayment->leg_number}, request {$legPayment->request->request_number}");
            
            // Trigger next leg progression
            app(LegOrchestrationService::class)->processLegPaymentConfirmation(
                $legPayment->request, 
                $legPayment->requestLeg
            );
        });
    }

    /**
     * Process refund through payment gateway
     */
    private function processRefund(LegPayment $legPayment, float $amount): void
    {
        try {
            $refundResult = $this->paymentManager->refund([
                'amount' => $amount,
                'currency' => 'NGN',
                'original_transaction_id' => $legPayment->transaction_id,
                'reason' => 'Weight verification adjustment - lower than estimated',
            ]);
            
            $legPayment->update([
                'refund_status' => $refundResult['status'] ?? 'processing',
                'refund_reference' => $refundResult['reference'] ?? null,
                'refund_processed_at' => now(),
            ]);
            
            Log::info("Refund processed for leg {$legPayment->leg_number}", [
                'amount' => $amount,
                'status' => $refundResult['status'] ?? 'unknown',
            ]);
            
        } catch (\Exception $e) {
            Log::error("Refund failed for leg {$legPayment->leg_number}: " . $e->getMessage());
            
            $legPayment->update([
                'refund_status' => 'failed',
                'refund_failure_reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get total paid amount for a request
     */
    public function getPaidAmount(Request $request): float
    {
        return LegPayment::where('request_id', $request->id)
            ->sum('paid_amount') ?? 0;
    }

    /**
     * Get pending amount for a request
     */
    public function getPendingAmount(Request $request): float
    {
        return LegPayment::where('request_id', $request->id)
            ->whereIn('payment_status', ['pending', 'additional_payment_required'])
            ->sum('balance_due') ?? 0;
    }

    /**
     * Get payment summary for a request
     */
    public function getPaymentSummary(Request $request): array
    {
        $legPayments = LegPayment::where('request_id', $request->id)
            ->orderBy('leg_number')
            ->get();
        
        $totalOriginal = $legPayments->sum('original_amount');
        $totalAdjusted = $legPayments->sum('adjusted_amount');
        $totalPaid = $legPayments->sum('paid_amount');
        $totalRefunded = $legPayments->sum('refund_amount');
        $totalPending = $legPayments
            ->whereIn('payment_status', ['pending', 'additional_payment_required'])
            ->sum('balance_due');
        
        return [
            'request_number' => $request->request_number,
            'currency' => 'NGN',
            'summary' => [
                'original_total' => $totalOriginal,
                'adjusted_total' => $totalAdjusted,
                'total_paid' => $totalPaid,
                'total_refunded' => $totalRefunded,
                'total_pending' => $totalPending,
                'net_amount' => $totalAdjusted - $totalRefunded,
            ],
            'legs' => $legPayments->map(fn($lp) => [
                'leg_number' => $lp->leg_number,
                'leg_type' => $lp->leg_type,
                'original_amount' => $lp->original_amount,
                'adjusted_amount' => $lp->adjusted_amount,
                'paid_amount' => $lp->paid_amount,
                'balance_due' => $lp->balance_due,
                'status' => $lp->payment_status,
                'paid_at' => $lp->paid_at,
            ]),
        ];
    }

    /**
     * Finalize all payments when request is completed
     */
    public function finalizeRequestPayments(Request $request): void
    {
        LegPayment::where('request_id', $request->id)
            ->where('payment_status', '!=', 'paid')
            ->update([
                'payment_status' => 'finalized',
                'finalized_at' => now(),
            ]);
        
        Log::info("Payments finalized for request {$request->request_number}");
    }

    /**
     * Get payment status for Flutter app display
     */
    public function getPaymentStatusForApp(Request $request, ?int $legNumber = null): array
    {
        $query = LegPayment::where('request_id', $request->id);
        
        if ($legNumber) {
            $query->where('leg_number', $legNumber);
        }
        
        $payments = $query->orderBy('leg_number')->get();
        
        return [
            'request_id' => $request->id,
            'request_number' => $request->request_number,
            'overall_status' => $this->getOverallPaymentStatus($request),
            'payments' => $payments->map(fn($p) => [
                'leg_number' => $p->leg_number,
                'leg_type' => $p->leg_type,
                'display_name' => $this->getLegDisplayName($p->leg_type),
                'original_amount' => $p->original_amount,
                'adjusted_amount' => $p->adjusted_amount,
                'paid_amount' => $p->paid_amount,
                'balance_due' => $p->balance_due,
                'status' => $p->payment_status,
                'is_payment_required' => in_array($p->payment_status, ['pending', 'additional_payment_required']),
                'payment_deadline' => $this->calculatePaymentDeadline($p),
            ]),
        ];
    }

    /**
     * Get overall payment status
     */
    private function getOverallPaymentStatus(Request $request): string
    {
        $statuses = LegPayment::where('request_id', $request->id)
            ->pluck('payment_status')
            ->toArray();
        
        if (empty($statuses)) {
            return 'no_payments';
        }
        
        if (in_array('additional_payment_required', $statuses)) {
            return 'additional_payment_required';
        }
        
        if (in_array('pending', $statuses)) {
            return 'pending';
        }
        
        if (in_array('refund_pending', $statuses)) {
            return 'refund_pending';
        }
        
        return 'complete';
    }

    /**
     * Get display name for leg type
     */
    private function getLegDisplayName(string $legType): string
    {
        $names = [
            'local_pickup' => 'Pickup from Seller',
            'hub_dropoff' => 'Hub Drop-off',
            'interstate_transport' => 'Interstate Transport',
            'hub_pickup' => 'Hub Pickup',
            'local_delivery' => 'Delivery to Buyer',
        ];
        
        return $names[$legType] ?? $legType;
    }

    /**
     * Calculate payment deadline
     */
    private function calculatePaymentDeadline(LegPayment $payment): ?string
    {
        if (!in_array($payment->payment_status, ['pending', 'additional_payment_required'])) {
            return null;
        }
        
        // Payment due within 2 hours of weight verification
        return now()->addHours(2)->toIso8601String();
    }
}
