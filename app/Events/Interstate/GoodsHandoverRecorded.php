<?php

namespace App\Events\Interstate;

use App\Models\Interstate\GoodsItem;
use App\Models\Interstate\TruckingCompany;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a trucking company receives goods (handover confirmed).
 * Listeners should push a real-time notification to the shipment owner.
 */
class GoodsHandoverRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly GoodsItem $goodsItem,
        public readonly TruckingCompany $company,
        public readonly int $recordedByUserId
    ) {}
}
