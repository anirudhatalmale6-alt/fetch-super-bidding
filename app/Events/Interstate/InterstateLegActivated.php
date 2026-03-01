<?php

namespace App\Events\Interstate;

use App\Models\Interstate\RequestLeg;
use App\Models\Interstate\SupportedRoute;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an interstate transport leg is activated
 */
class InterstateLegActivated
{
    use Dispatchable, SerializesModels;

    public RequestLeg $leg;
    public SupportedRoute $route;

    public function __construct(RequestLeg $leg, SupportedRoute $route)
    {
        $this->leg = $leg;
        $this->route = $route;
    }
}
