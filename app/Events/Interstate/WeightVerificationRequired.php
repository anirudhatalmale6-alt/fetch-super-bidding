<?php

namespace App\Events\Interstate;

use App\Models\Request\Request;
use App\Models\Interstate\RequestLeg;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when weight verification is completed by trucking company
 * This triggers notifications to the customer
 */
class WeightVerificationRequired
{
    use Dispatchable, SerializesModels;

    public Request $request;
    public RequestLeg $leg;
    public array $verificationData;

    public function __construct(Request $request, RequestLeg $leg, array $verificationData)
    {
        $this->request = $request;
        $this->leg = $leg;
        $this->verificationData = $verificationData;
    }
}
