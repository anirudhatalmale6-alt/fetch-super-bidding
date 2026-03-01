<?php

namespace App\Events\Interstate;

use App\Models\Interstate\InterstateBid;
use App\Models\Request\Request;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a user accepts a bid
 */
class BidAccepted
{
    use Dispatchable, SerializesModels;

    public Request $request;
    public InterstateBid $bid;
    public array $metadata;

    public function __construct(Request $request, InterstateBid $bid, array $metadata = [])
    {
        $this->request = $request;
        $this->bid = $bid;
        $this->metadata = $metadata;
    }
}
