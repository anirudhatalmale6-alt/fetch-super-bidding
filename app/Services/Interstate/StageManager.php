<?php

namespace App\Services\Interstate;

use App\Models\Interstate\OrderStage;
use App\Models\Interstate\StagePayment;
use App\Models\Request\Request;
use App\Events\Interstate\StageUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StageManager
{
    /**
     * Initialize stages for a new interstate request
     */
    public function initializeStages(Request $request): void
    {
        DB::transaction(function () use ($request) {
            foreach (OrderStage::STAGES as $number => $stageInfo) {
                OrderStage::create([
                    'request_id' => $request->id,
                    'stage_number' => $number,
                    'stage_code' => $stageInfo['code'],
                    'stage_name' => $stageInfo['name'],
                    'status' => $number === 1 ? 'in_progress' : 'pending',
                    'requires_payment' => $stageInfo['requires_payment'],
                    'started_at' => $number === 1 ? now() : null,
                ]);
            }
        });

        Log::info("Stages initialized for request {$request->id}");
    }

    /**
     * Transition to the next stage
     */
    public function transitionToNextStage(
        Request $request,
        string $triggeredByType = 'system',
        int $triggeredById = null
    ): ?OrderStage {
        return DB::transaction(function () use ($request, $triggeredByType, $triggeredById) {
            // Get current stage
            $currentStage = OrderStage::forRequest($request->id)
                ->whereIn('status', ['in_progress', 'pending'])
                ->orderBy('stage_number')
                ->first();

            if (!$currentStage) {
                Log::warning("No pending stage found for request {$request->id}");
                return null;
            }

            // Complete current stage if in progress
            if ($currentStage->isInProgress()) {
                $currentStage->complete([
                    'triggered_by_type' => $triggeredByType,
                    'triggered_by_id' => $triggeredById,
                ]);
            }

            // Get next stage
            $nextStage = OrderStage::forRequest($request->id)
                ->where('stage_number', $currentStage->stage_number + 1)
                ->where('status', 'pending')
                ->first();

            if (!$nextStage) {
                Log::info("Request {$request->id} completed all stages");
                return null;
            }

            // Start next stage
            $nextStage->start([
                'triggered_by_type' => $triggeredByType,
                'triggered_by_id' => $triggeredById,
                'previous_stage_id' => $currentStage->id,
            ]);

            // Dispatch event
            event(new StageUpdated($request, $currentStage, $nextStage));

            Log::info("Request {$request->id} transitioned from {$currentStage->stage_code} to {$nextStage->stage_code}");

            return $nextStage;
        });
    }

    /**
     * Transition to a specific stage
     */
    public function transitionToStage(
        Request $request,
        string $stageCode,
        string $triggeredByType = 'system',
        int $triggeredById = null
    ): ?OrderStage {
        $targetStage = OrderStage::forRequest($request->id)
            ->byStageCode($stageCode)
            ->first();

        if (!$targetStage) {
            throw new \InvalidArgumentException("Stage {$stageCode} not found for request {$request->id}");
        }

        return DB::transaction(function () use ($request, $targetStage, $triggeredByType, $triggeredById) {
            // Complete all stages before target
            OrderStage::forRequest($request->id)
                ->where('stage_number', '<', $targetStage->stage_number)
                ->whereIn('status', ['pending', 'in_progress'])
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

            // Start target stage
            $targetStage->start([
                'triggered_by_type' => $triggeredByType,
                'triggered_by_id' => $triggeredById,
            ]);

            event(new StageUpdated($request, null, $targetStage));

            return $targetStage;
        });
    }

    /**
     * Override stage (admin function)
     */
    public function overrideStage(
        Request $request,
        string $toStageCode,
        int $adminId,
        string $reason
    ): OrderStage {
        $fromStage = $this->getCurrentStage($request);
        $toStage = OrderStage::forRequest($request->id)
            ->byStageCode($toStageCode)
            ->firstOrFail();

        return DB::transaction(function () use ($request, $fromStage, $toStage, $adminId, $reason) {
            // Cancel current stage
            if ($fromStage) {
                $fromStage->update([
                    'status' => 'skipped',
                    'notes' => "Overridden by admin: {$reason}",
                    'completed_at' => now(),
                ]);
            }

            // Reset stages between current and target
            OrderStage::forRequest($request->id)
                ->whereBetween('stage_number', [
                    $fromStage ? $fromStage->stage_number + 1 : 1,
                    $toStage->stage_number - 1
                ])
                ->update([
                    'status' => 'skipped',
                    'notes' => 'Skipped due to admin override',
                ]);

            // Start target stage
            $toStage->start([
                'triggered_by_type' => 'admin',
                'triggered_by_id' => $adminId,
            ]);

            // Log admin action
            \App\Models\Admin\AdminActionLog::logStageOverride(
                $adminId,
                $request->id,
                $fromStage?->stage_code ?? 'none',
                $toStage->stage_code,
                $reason
            );

            event(new StageUpdated($request, $fromStage, $toStage, true));

            return $toStage;
        });
    }

    /**
     * Get current stage
     */
    public function getCurrentStage(Request $request): ?OrderStage
    {
        return OrderStage::forRequest($request->id)
            ->whereIn('status', ['in_progress', 'pending'])
            ->orderBy('stage_number')
            ->first();
    }

    /**
     * Get stage by code
     */
    public function getStage(Request $request, string $stageCode): ?OrderStage
    {
        return OrderStage::forRequest($request->id)
            ->byStageCode($stageCode)
            ->first();
    }

    /**
     * Check if request is at specific stage
     */
    public function isAtStage(Request $request, string $stageCode): bool
    {
        $stage = $this->getCurrentStage($request);
        return $stage && $stage->stage_code === $stageCode;
    }

    /**
     * Check if stage transition is allowed
     */
    public function canTransitionToStage(Request $request, string $stageCode): bool
    {
        $targetStage = $this->getStage($request, $stageCode);
        if (!$targetStage) return false;

        $currentStage = $this->getCurrentStage($request);
        if (!$currentStage) return false;

        // Can only move forward
        return $targetStage->stage_number > $currentStage->stage_number;
    }

    /**
     * Check if payment is required for current stage
     */
    public function isPaymentRequired(Request $request): bool
    {
        $stage = $this->getCurrentStage($request);
        return $stage ? $stage->isPaymentRequired() : false;
    }

    /**
     * Process payment completion and unlock stage
     */
    public function processPaymentCompletion(
        Request $request,
        StagePayment $payment
    ): void {
        $stage = OrderStage::forRequest($request->id)
            ->where('stage_number', $payment->stage_number)
            ->first();

        if ($stage) {
            $stage->markPaymentCompleted($payment->id);
            Log::info("Payment completed for stage {$stage->stage_code} on request {$request->id}");
        }
    }

    /**
     * Handle rerouting - reset stages for new attempt
     */
    public function handleRerouting(Request $request, int $reroutingAttempt): void
    {
        DB::transaction(function () use ($request, $reroutingAttempt) {
            // Cancel stages from inspection onwards
            OrderStage::forRequest($request->id)
                ->whereIn('stage_code', [
                    'inspection_pending',
                    'awaiting_user_approval',
                    'in_transit',
                    'arrived_destination_hub',
                    'last_mile_assigned',
                    'delivered',
                ])
                ->update([
                    'status' => 'pending',
                    'started_at' => null,
                    'completed_at' => null,
                    'rerouting_attempt' => $reroutingAttempt,
                ]);

            // Reset to inspection pending
            $inspectionStage = OrderStage::forRequest($request->id)
                ->byStageCode('inspection_pending')
                ->first();

            if ($inspectionStage) {
                $inspectionStage->start([
                    'triggered_by_type' => 'system',
                    'rerouting_attempt' => $reroutingAttempt,
                ]);
            }

            Log::info("Stages reset for rerouting attempt {$reroutingAttempt} on request {$request->id}");
        });
    }

    /**
     * Get stage timeline for request
     */
    public function getTimeline(Request $request): array
    {
        $stages = OrderStage::forRequest($request->id)
            ->orderBy('stage_number')
            ->get();

        $currentStage = $stages->firstWhere('status', 'in_progress');

        return [
            'current_stage' => $currentStage ? [
                'number' => $currentStage->stage_number,
                'code' => $currentStage->stage_code,
                'name' => $currentStage->stage_name,
                'progress' => $currentStage->getProgressPercentage(),
            ] : null,
            'stages' => $stages->map(fn($s) => [
                'number' => $s->stage_number,
                'code' => $s->stage_code,
                'name' => $s->stage_name,
                'status' => $s->status,
                'requires_payment' => $s->requires_payment,
                'payment_completed' => !is_null($s->payment_completed_at),
                'started_at' => $s->started_at,
                'completed_at' => $s->completed_at,
                'duration_minutes' => $s->duration_minutes,
            ]),
        ];
    }

    /**
     * Get completed stages count
     */
    public function getCompletedStagesCount(Request $request): int
    {
        return OrderStage::forRequest($request->id)
            ->completed()
            ->count();
    }

    /**
     * Check if request is complete
     */
    public function isComplete(Request $request): bool
    {
        $deliveredStage = OrderStage::forRequest($request->id)
            ->byStageCode('delivered')
            ->first();

        return $deliveredStage && $deliveredStage->isCompleted();
    }

    /**
     * Cancel all remaining stages
     */
    public function cancelRemainingStages(Request $request, string $reason = null): void
    {
        OrderStage::forRequest($request->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->update([
                'status' => 'cancelled',
                'notes' => $reason,
                'completed_at' => now(),
            ]);

        Log::info("Remaining stages cancelled for request {$request->id}: {$reason}");
    }
}
