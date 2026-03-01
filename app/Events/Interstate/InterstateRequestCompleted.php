<?php

namespace App\Events\Interstate;

use App\Models\Request\Request;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when the entire interstate request is completed
 */
class InterstateRequestCompleted
{
    use Dispatchable, SerializesModels;

    public Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }
}
