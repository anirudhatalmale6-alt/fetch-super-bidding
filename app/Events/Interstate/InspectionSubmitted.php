<?php

namespace App\Events\Interstate;

use App\Models\Request\Request;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a trucking company submits inspection results
 */
class InspectionSubmitted
{
    use Dispatchable, SerializesModels;

    public Request $request;
    public array $inspectionData;
    public float $finalTransportationFee;
    public float $finalInsuranceFee;

    public function __construct(
        Request $request,
        array $inspectionData,
        float $finalTransportationFee,
        float $finalInsuranceFee
    ) {
        $this->request = $request;
        $this->inspectionData = $inspectionData;
        $this->finalTransportationFee = $finalTransportationFee;
        $this->finalInsuranceFee = $finalInsuranceFee;
    }
}
