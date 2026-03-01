<?php

namespace App\Http\Controllers\Api\V1\Interstate;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\Interstate\Payment\MultiLegPaymentService;
use App\Services\Interstate\LegOrchestrationService;
use App\Models\Request\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Validator;

/**
 * Controller for handling interstate delivery payments
 * User-facing payment endpoints
 */
class PaymentController extends BaseController
{
    public function __construct(
        private MultiLegPaymentService $paymentService,
        private LegOrchestrationService $legOrchestrationService
    ) {}

    /**
     * Get payment status for a request
     * 
     * GET /api/v1/interstate/payment/status/{requestId}
     */
    public function getPaymentStatus(string $requestId)
    {
        $request = Request::where('user_id', auth()->id())
            ->where('delivery_mode', 'interstate')
            ->findOrFail($requestId);

        $paymentStatus = $this->paymentService->getPaymentStatusForApp($request);

        return $this->respondSuccess($paymentStatus);
    }

    /**
     * Initiate payment for a specific leg
     * 
     * POST /api/v1/interstate/payment/initiate/{requestId}/{legNumber}
     */
    public function initiatePayment(string $requestId, int $legNumber, HttpRequest $httpRequest)
    {
        $validator = Validator::make($httpRequest->all(), [
            'payment_method' => 'required|string|in:card,bank_transfer,ussd,wallet',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $request = Request::where('user_id', auth()->id())
            ->where('delivery_mode', 'interstate')
            ->findOrFail($requestId);

        try {
            $paymentResult = $this->paymentService->processLegPayment(
                $request,
                $legNumber,
                $httpRequest->input('payment_method'),
                $httpRequest->except('payment_method')
            );

            return $this->respondSuccess([
                'payment_reference' => $paymentResult['reference'] ?? null,
                'payment_url' => $paymentResult['payment_url'] ?? null,
                'status' => $paymentResult['status'],
                'message' => 'Payment initiated successfully. Please complete payment using the provided URL.',
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->respondError($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->respondError('Payment processing failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Confirm payment (webhook callback)
     * 
     * POST /api/v1/interstate/payment/confirm
     */
    public function confirmPayment(HttpRequest $httpRequest)
    {
        $validator = Validator::make($httpRequest->all(), [
            'payment_reference' => 'required|string',
            'status' => 'required|string|in:success,failed,pending',
            'transaction_id' => 'nullable|string',
            'amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        try {
            $this->paymentService->confirmPayment(
                $httpRequest->input('payment_reference'),
                $httpRequest->only(['status', 'transaction_id', 'amount'])
            );

            return $this->respondSuccess(null, 'Payment confirmed successfully');

        } catch (\Exception $e) {
            return $this->respondError('Payment confirmation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get payment summary for a request
     * 
     * GET /api/v1/interstate/payment/summary/{requestId}
     */
    public function getPaymentSummary(string $requestId)
    {
        $request = Request::where('user_id', auth()->id())
            ->where('delivery_mode', 'interstate')
            ->findOrFail($requestId);

        $summary = $this->paymentService->getPaymentSummary($request);

        return $this->respondSuccess($summary);
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
