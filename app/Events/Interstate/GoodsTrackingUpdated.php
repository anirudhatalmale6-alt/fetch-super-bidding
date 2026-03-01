<?php

namespace App\Events\Interstate;

use App\Models\Interstate\GoodsItem;
use App\Models\Interstate\TrackingUpdate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a tracking note or status update is added to a goods item.
 * Listeners push live updates to the user app via Firebase.
 */
class GoodsTrackingUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly GoodsItem $goodsItem,
        public readonly ?TrackingUpdate $trackingUpdate
    ) {}
}
