<?php

namespace App\Events\Interstate;

use App\Models\Interstate\RequestLeg;
use App\Models\Request\Request;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when shipment goes into transit
 */
class ShipmentInTransit
{
    use Dispatchable, SerializesModels;

    public Request $request;
    public RequestLeg $leg;
    public array $metadata;

    public function __construct(Request $request, RequestLeg $leg, array $metadata = [])
    {
        $this->request = $request;
        $this->leg = $leg;
        $this->metadata = $metadata;
    }

    /**
     * Get transit leg type
     */
    public function getLegType(): string
    {
        return $this->leg->leg_type;
    }

    /**
     * Check if this is interstate transport
     */
    public function isInterstateTransport(): bool
    {
        return $this->leg->leg_type === 'interstate_transport';
    }
}
