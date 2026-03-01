<?php

namespace App\Events\Interstate;

use App\Models\Interstate\StagePayment;
use App\Models\Request\Request;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a stage payment is completed
 */
class PaymentCompleted
{
    use Dispatchable, SerializesModels;

    public Request $request;
    public StagePayment $payment;
    public array $metadata;

    public function __construct(Request $request, StagePayment $payment, array $metadata = [])
    {
        $this->request = $request;
        $this->payment = $payment;
        $this->metadata = $metadata;
    }

    /**
     * Check if this payment unlocks a stage
     */
    public function unlocksStage(): bool
    {
        return $this->payment->payment_type === StagePayment::TYPE_STAGE_UNLOCK;
    }
}
