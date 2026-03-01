<?php

namespace App\Events\Interstate;

use App\Models\Interstate\InterstateBid;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a trucking company places a bid
 */
class BidPlaced
{
    use Dispatchable, SerializesModels;

    public InterstateBid $bid;
    public array $metadata;

    public function __construct(InterstateBid $bid, array $metadata = [])
    {
        $this->bid = $bid;
        $this->metadata = $metadata;
    }
}
