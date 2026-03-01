<?php

namespace App\Events\Interstate;

use App\Models\Request\Request;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when package arrives at origin hub and is ready for interstate transport
 */
class PackageReadyForTransport
{
    use Dispatchable, SerializesModels;

    public Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }
}
