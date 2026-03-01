<?php

namespace App\Http\Middleware;

use App\Services\Interstate\StageManager;
use Closure;
use Illuminate\Http\Request;

class EnsureStageOrder
{
    protected StageManager $stageManager;

    public function __construct(StageManager $stageManager)
    {
        $this->stageManager = $stageManager;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $requiredStage = null)
    {
        $interstateRequest = $request->route('request') ?? $request->route('interstateRequest');

        if (!$interstateRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found',
            ], 404);
        }

        // Check if request is interstate
        if ($interstateRequest->delivery_mode !== 'interstate') {
            return $next($request);
        }

        $currentStage = $this->stageManager->getCurrentStage($interstateRequest);

        if (!$currentStage) {
            return response()->json([
                'success' => false,
                'message' => 'No active stage found for this request',
            ], 400);
        }

        // If specific stage is required, check it
        if ($requiredStage && $currentStage->stage_code !== $requiredStage) {
            return response()->json([
                'success' => false,
                'message' => "This action requires the request to be in '{$requiredStage}' stage",
                'current_stage' => $currentStage->stage_code,
            ], 422);
        }

        // Check if payment is required for current stage
        if ($currentStage->isPaymentRequired() && !$currentStage->payment_completed_at) {
            return response()->json([
                'success' => false,
                'message' => 'Payment required to proceed with this stage',
                'stage' => $currentStage->stage_code,
                'payment_required' => true,
            ], 402);
        }

        // Attach current stage to request for use in controllers
        $request->attributes->set('current_stage', $currentStage);

        return $next($request);
    }
}
