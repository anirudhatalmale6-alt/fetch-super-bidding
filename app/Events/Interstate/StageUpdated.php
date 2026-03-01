<?php

namespace App\Events\Interstate;

use App\Models\Interstate\OrderStage;
use App\Models\Request\Request;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an order stage is updated
 */
class StageUpdated
{
    use Dispatchable, SerializesModels;

    public Request $request;
    public ?OrderStage $previousStage;
    public OrderStage $newStage;
    public bool $isAdminOverride;
    public array $metadata;

    public function __construct(
        Request $request,
        ?OrderStage $previousStage,
        OrderStage $newStage,
        bool $isAdminOverride = false,
        array $metadata = []
    ) {
        $this->request = $request;
        $this->previousStage = $previousStage;
        $this->newStage = $newStage;
        $this->isAdminOverride = $isAdminOverride;
        $this->metadata = $metadata;
    }

    /**
     * Get the stage transition description
     */
    public function getTransitionDescription(): string
    {
        $from = $this->previousStage?->stage_code ?? 'start';
        $to = $this->newStage->stage_code;

        if ($this->isAdminOverride) {
            return "Stage manually overridden from '{$from}' to '{$to}'";
        }

        return "Stage transitioned from '{$from}' to '{$to}'";
    }
}
