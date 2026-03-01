<?php

namespace App\Http\Controllers\Web\Booking;

use App\Http\Controllers\Controller;
use App\Models\Request\Request;
use App\Models\Interstate\RequestLeg;
use App\Models\Interstate\GoodsItem;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;

class TrackingController extends Controller
{
    /**
     * Display leg tracking for a request
     */
    public function trackLegs($requestNumber)
    {
        $request = Request::with(['legs', 'truckingCompany'])
            ->where('request_number', $requestNumber)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $legs = $request->legs()->orderBy('leg_number')->get();
        
        // Enrich leg data
        $enrichedLegs = $legs->map(function ($leg) {
            $legData = (object) [
                'id' => $leg->id,
                'leg_number' => $leg->leg_number,
                'leg_type' => $leg->leg_type,
                'display_name' => $this->getLegDisplayName($leg->leg_type),
                'status' => $leg->status,
                'status_class' => $this->getStatusClass($leg->status),
                'is_completed' => in_array($leg->status, ['completed', 'delivered']),
                'is_active' => in_array($leg->status, ['accepted', 'picked_up', 'in_transit']),
                'is_pending' => $leg->status === 'pending',
                'payment_status' => $leg->payment_status,
                'paid_amount' => $leg->paid_amount,
                'total_leg_price' => $leg->total_leg_price,
                'provider_base_price' => $leg->provider_base_price,
                'insurance_fee' => $leg->insurance_fee,
                'platform_commission' => $leg->platform_commission,
                'provider_name' => $leg->provider_name,
                'provider_phone' => $leg->provider_phone,
                'pickup_location' => $leg->pickup_location,
                'drop_location' => $leg->drop_location,
                'completed_at' => $leg->completed_at,
                'goods_items' => $this->getLegGoodsItems($leg->id)
            ];
            return $legData;
        });

        // Calculate summary stats
        $totalLegs = $legs->count();
        $completedLegs = $legs->whereIn('status', ['completed', 'delivered'])->count();
        $progressPercentage = $totalLegs > 0 ? ($completedLegs / $totalLegs) * 100 : 0;
        
        $totalPaid = $legs->sum('paid_amount');
        $totalPrice = $legs->sum('total_leg_price');
        $remainingBalance = $totalPrice - $totalPaid;
        
        $hasActiveLeg = $legs->contains(function ($leg) {
            return in_array($leg->status, ['accepted', 'picked_up', 'in_transit']);
        });

        return view('web-booking.tracking.legs', compact(
            'request',
            'legs',
            'enrichedLegs',
            'totalLegs',
            'completedLegs',
            'progressPercentage',
            'totalPaid',
            'remainingBalance',
            'hasActiveLeg'
        ));
    }

    /**
     * Get display name for leg type
     */
    private function getLegDisplayName($legType)
    {
        $names = [
            'local_pickup' => 'Local Pickup from Seller',
            'hub_dropoff' => 'Drop at Origin Hub',
            'interstate_transport' => 'Interstate Transport',
            'hub_pickup' => 'Pickup from Destination Hub',
            'local_delivery' => 'Final Delivery to You'
        ];

        return $names[$legType] ?? 'Unknown';
    }

    /**
     * Get CSS class for leg status
     */
    private function getStatusClass($status)
    {
        $classes = [
            'pending' => 'pending',
            'accepted' => 'active',
            'picked_up' => 'active',
            'in_transit' => 'active',
            'completed' => 'completed',
            'delivered' => 'completed',
            'cancelled' => 'cancelled'
        ];

        return $classes[$status] ?? 'pending';
    }

    /**
     * Get goods items for a leg
     */
    private function getLegGoodsItems($legId)
    {
        return GoodsItem::where('request_leg_id', $legId)
            ->select('item_number', 'description', 'chargeable_weight_kg', 'declared_value', 'company_total_price', 'status')
            ->get();
    }

    /**
     * Get current active leg for a request
     */
    public function getCurrentLeg($requestNumber)
    {
        $request = Request::where('request_number', $requestNumber)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $currentLeg = RequestLeg::where('request_id', $request->id)
            ->whereIn('status', ['accepted', 'picked_up', 'in_transit'])
            ->orderBy('leg_number')
            ->first();

        if (!$currentLeg) {
            return response()->json([
                'success' => false,
                'message' => 'No active leg found'
            ]);
        }

        return response()->json([
            'success' => true,
            'leg' => [
                'id' => $currentLeg->id,
                'leg_number' => $currentLeg->leg_number,
                'leg_type' => $currentLeg->leg_type,
                'display_name' => $this->getLegDisplayName($currentLeg->leg_type),
                'status' => $currentLeg->status,
                'provider_name' => $currentLeg->provider_name,
                'provider_phone' => $currentLeg->provider_phone,
                'current_location' => [
                    'lat' => $currentLeg->current_lat,
                    'lng' => $currentLeg->current_lng
                ],
                'pickup_location' => $currentLeg->pickup_location,
                'drop_location' => $currentLeg->drop_location,
                'payment_status' => $currentLeg->payment_status,
                'total_price' => $currentLeg->total_leg_price
            ]
        ]);
    }

    /**
     * Pay for a specific leg
     */
    public function payForLeg($legId)
    {
        $leg = RequestLeg::with('request')
            ->whereHas('request', function ($q) {
                $q->where('user_id', Auth::id());
            })
            ->findOrFail($legId);

        // Check if already paid
        if ($leg->payment_status === 'paid') {
            return redirect()->back()->with('error', 'This leg has already been paid for.');
        }

        // Redirect to payment gateway with leg-specific details
        return view('web-booking.payment.leg-payment', compact('leg'));
    }
}
