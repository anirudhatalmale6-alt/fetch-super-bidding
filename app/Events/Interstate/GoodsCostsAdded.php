<?php

namespace App\Events\Interstate;

use App\Models\Interstate\GoodsItem;
use App\Models\Interstate\TruckingCompany;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a trucking company adds transport/insurance/handling costs.
 * Listeners notify the user of new payment obligations.
 */
class GoodsCostsAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly GoodsItem $goodsItem,
        public readonly TruckingCompany $company,
        public readonly int $addedByUserId
    ) {}
}
