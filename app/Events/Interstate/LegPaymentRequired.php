<?php

namespace App\Events\Interstate;

use App\Models\Request\Request;
use App\Models\Interstate\RequestLeg;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when payment is required for a leg
 * (Additional payment after weight verification or refund)
 */
class LegPaymentRequired
{
    use Dispatchable, SerializesModels;

    public Request $request;
    public RequestLeg $leg;
    public float $amount;
    public string $type; // 'additional' or 'refund'

    public function __construct(Request $request, RequestLeg $leg, float $amount, string $type)
    {
        $this->request = $request;
        $this->leg = $leg;
        $this->amount = $amount;
        $this->type = $type;
    }

    public function isAdditionalPayment(): bool
    {
        return $this->type === 'additional';
    }

    public function isRefund(): bool
    {
        return $this->type === 'refund';
    }
}
