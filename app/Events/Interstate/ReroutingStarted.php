<?php

namespace App\Events\Interstate;

use App\Models\Request\Request;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when rerouting is initiated
 */
class ReroutingStarted
{
    use Dispatchable, SerializesModels;

    public Request $request;
    public int $reroutingAttempt;
    public ?int $previousCompanyId;
    public string $reason;
    public array $metadata;

    public function __construct(
        Request $request,
        int $reroutingAttempt,
        ?int $previousCompanyId,
        string $reason,
        array $metadata = []
    ) {
        $this->request = $request;
        $this->reroutingAttempt = $reroutingAttempt;
        $this->previousCompanyId = $previousCompanyId;
        $this->reason = $reason;
        $this->metadata = $metadata;
    }

    /**
     * Check if this is the first rerouting attempt
     */
    public function isFirstAttempt(): bool
    {
        return $this->reroutingAttempt === 1;
    }

    /**
     * Check if max attempts reached
     */
    public function isMaxAttemptsReached(int $maxAttempts = 2): bool
    {
        return $this->reroutingAttempt >= $maxAttempts;
    }
}
