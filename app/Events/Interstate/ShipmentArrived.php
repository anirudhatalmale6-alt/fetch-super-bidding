<?php

namespace App\Events\Interstate;

use App\Models\Interstate\RequestLeg;
use App\Models\Request\Request;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when shipment arrives at destination
 */
class ShipmentArrived
{
    use Dispatchable, SerializesModels;

    public Request $request;
    public RequestLeg $leg;
    public string $arrivalType; // 'hub', 'final_destination'
    public array $metadata;

    public function __construct(
        Request $request,
        RequestLeg $leg,
        string $arrivalType = 'hub',
        array $metadata = []
    ) {
        $this->request = $request;
        $this->leg = $leg;
        $this->arrivalType = $arrivalType;
        $this->metadata = $metadata;
    }

    /**
     * Check if arrived at final destination
     */
    public function isFinalDestination(): bool
    {
        return $this->arrivalType === 'final_destination';
    }

    /**
     * Get hub name if arrived at hub
     */
    public function getHubName(): ?string
    {
        return $this->leg->drop_location['hub_name'] ?? null;
    }
}
