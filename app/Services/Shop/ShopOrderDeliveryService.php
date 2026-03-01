<?php

namespace App\Services\Shop;

use App\Models\ShopOrder;
use App\Models\Request\Request;
use App\Models\Request\RequestPlace;
use App\Models\Interstate\RequestPackage;
use App\Models\Interstate\RequestLeg;
use App\Services\Interstate\InterstateRequestService;
use App\Events\Interstate\StageUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ShopOrderDeliveryService
{
    protected InterstateRequestService $interstateService;

    public function __construct(InterstateRequestService $interstateService)
    {
        $this->interstateService = $interstateService;
    }

    /**
     * Create a logistics delivery request from a shop order
     *
     * @param ShopOrder $shopOrder
     * @param string $deliveryType 'metro' or 'interstate'
     * @param array $deliveryData
     * @return Request|null
     * @throws \Exception
     */
    public function createDeliveryRequest(ShopOrder $shopOrder, string $deliveryType, array $deliveryData = []): ?Request
    {
        return DB::transaction(function () use ($shopOrder, $deliveryType, $deliveryData) {
            try {
                // Create the appropriate type of delivery request
                $request = match($deliveryType) {
                    'metro' => $this->createMetroDeliveryRequest($shopOrder, $deliveryData),
                    'interstate' => $this->createInterstateDeliveryRequest($shopOrder, $deliveryData),
                    default => throw new \InvalidArgumentException("Invalid delivery type: {$deliveryType}")
                };

                // Link the shop order to the logistics request
                $shopOrder->update([
                    'logistics_request_id' => $request->id,
                    'delivery_status' => 'logistics_request_created',
                    'delivery_type' => $deliveryType,
                ]);

                Log::info("Created {$deliveryType} delivery request for shop order", [
                    'shop_order_id' => $shopOrder->id,
                    'logistics_request_id' => $request->id,
                    'request_number' => $request->request_number,
                ]);

                return $request;

            } catch (\Exception $e) {
                Log::error("Failed to create delivery request for shop order", [
                    'shop_order_id' => $shopOrder->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }

    /**
     * Create a metro delivery request
     */
    protected function createMetroDeliveryRequest(ShopOrder $shopOrder, array $data): Request
    {
        // Generate request number
        $requestNumber = $this->generateRequestNumber();

        // Create the main request
        $request = Request::create([
            'request_number' => $requestNumber,
            'user_id' => $shopOrder->user_id,
            'delivery_mode' => 'metro',
            'transport_type' => 'delivery',
            'payment_opt' => $shopOrder->payment_method === 'bank_transfer' ? 'cash' : 'card',
            'is_paid' => $shopOrder->payment_status === 'paid',
            'request_eta_amount' => $shopOrder->delivery_fee,
            'status' => 'pending',
            'zone_type_id' => $data['zone_type_id'] ?? null,
            'service_location_id' => $data['service_location_id'] ?? null,
            'trip_start_time' => now(),
            'unit' => 1, // KM
            'timezone' => $data['timezone'] ?? 'Africa/Lagos',
            'company_key' => $data['company_key'] ?? null,
        ]);

        // Create request place
        RequestPlace::create([
            'request_id' => $request->id,
            'pick_address' => $data['pickup_address'] ?? $shopOrder->company->address ?? 'Shop Pickup',
            'pick_lat' => $data['pickup_lat'] ?? null,
            'pick_lng' => $data['pickup_lng'] ?? null,
            'drop_address' => $shopOrder->delivery_address,
            'drop_lat' => $shopOrder->delivery_lat,
            'drop_lng' => $shopOrder->delivery_lng,
            'contact_name' => $shopOrder->delivery_contact_name,
            'contact_number' => $shopOrder->delivery_contact_phone,
        ]);

        // Store shop order reference in request meta
        DB::table('requests_meta')->insert([
            'request_id' => $request->id,
            'shop_order_id' => $shopOrder->id,
            'order_number' => $shopOrder->order_number,
            'item_count' => $shopOrder->items->count(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $request;
    }

    /**
     * Create an interstate delivery request
     */
    protected function createInterstateDeliveryRequest(ShopOrder $shopOrder, array $data): Request
    {
        // Validate required data
        if (empty($data['origin_hub_id']) || empty($data['destination_hub_id'])) {
            throw new \InvalidArgumentException('Origin and destination hubs are required for interstate delivery');
        }

        // Prepare packages from shop order items
        $packages = $this->preparePackagesFromOrderItems($shopOrder);

        // Use the interstate request service
        $requestData = [
            'user_id' => $shopOrder->user_id,
            'pickup_address' => $data['pickup_address'] ?? $shopOrder->company->address ?? 'Shop Pickup',
            'pickup_lat' => $data['pickup_lat'] ?? null,
            'pickup_lng' => $data['pickup_lng'] ?? null,
            'drop_address' => $shopOrder->delivery_address,
            'drop_lat' => $shopOrder->delivery_lat ?? $data['delivery_lat'] ?? null,
            'drop_lng' => $shopOrder->delivery_lng ?? $data['delivery_lng'] ?? null,
            'origin_hub_id' => $data['origin_hub_id'],
            'destination_hub_id' => $data['destination_hub_id'],
            'packages' => $packages,
            'requires_inspection' => $data['requires_inspection'] ?? false,
            'service_location_id' => $data['service_location_id'] ?? null,
            'zone_type_id' => $data['zone_type_id'] ?? null,
            'timezone' => $data['timezone'] ?? 'Africa/Lagos',
        ];

        $request = $this->interstateService->createInterstateRequest($requestData);

        // Store shop order reference
        DB::table('requests_meta')->insert([
            'request_id' => $request->id,
            'shop_order_id' => $shopOrder->id,
            'order_number' => $shopOrder->order_number,
            'item_count' => $shopOrder->items->count(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $request;
    }

    /**
     * Prepare package data from shop order items
     */
    protected function preparePackagesFromOrderItems(ShopOrder $shopOrder): array
    {
        $packages = [];
        
        foreach ($shopOrder->items as $item) {
            $packages[] = [
                'description' => $item->product_name,
                'estimated_weight_kg' => $item->product->weight_kg ?? 1.0,
                'estimated_length_cm' => $item->product->length_cm ?? 10,
                'estimated_width_cm' => $item->product->width_cm ?? 10,
                'estimated_height_cm' => $item->product->height_cm ?? 10,
                'quantity' => $item->quantity,
                'declared_value' => $item->unit_price * $item->quantity,
                'category' => $item->product->category ?? 'general',
                'requires_insurance' => false,
                'is_fragile' => $item->product->is_fragile ?? false,
            ];
        }

        return $packages;
    }

    /**
     * Sync delivery status from logistics request to shop order
     */
    public function syncDeliveryStatus(ShopOrder $shopOrder): void
    {
        if (!$shopOrder->logistics_request_id) {
            return;
        }

        $logisticsRequest = Request::find($shopOrder->logistics_request_id);
        
        if (!$logisticsRequest) {
            return;
        }

        $deliveryStatus = $this->mapLogisticsStatusToDeliveryStatus($logisticsRequest->status);

        if ($deliveryStatus !== $shopOrder->delivery_status) {
            $shopOrder->update([
                'delivery_status' => $deliveryStatus,
                'actual_delivered_at' => $deliveryStatus === 'delivered' ? now() : $shopOrder->actual_delivered_at,
            ]);

            // Fire event
            event(new StageUpdated($shopOrder, $deliveryStatus));
        }
    }

    /**
     * Map logistics request status to shop order delivery status
     */
    protected function mapLogisticsStatusToDeliveryStatus(string $logisticsStatus): string
    {
        return match($logisticsStatus) {
            'pending' => 'logistics_request_created',
            'accepted', 'driver_assigned' => 'driver_assigned',
            'picked_up' => 'picked_up',
            'in_transit' => 'in_transit',
            'completed' => 'delivered',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    /**
     * Generate unique request number
     */
    protected function generateRequestNumber(): string
    {
        $prefix = 'SHOP';
        $date = date('Ymd');
        $random = strtoupper(\Str::random(6));
        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Get available delivery options for a shop order
     */
    public function getDeliveryOptions(ShopOrder $shopOrder, array $locationData): array
    {
        $options = [];

        // Metro option
        $metroEstimate = $this->estimateMetroDelivery($shopOrder, $locationData);
        $options[] = [
            'type' => 'metro',
            'name' => 'Local Delivery (Metro)',
            'description' => 'Same-day local delivery within the city',
            'estimated_fee' => $metroEstimate['fee'],
            'estimated_duration' => $metroEstimate['duration'],
            'available' => $metroEstimate['available'],
        ];

        // Interstate option
        $interstateEstimate = $this->estimateInterstateDelivery($shopOrder, $locationData);
        $options[] = [
            'type' => 'interstate',
            'name' => 'Interstate Delivery',
            'description' => 'Cross-state delivery via trucking hubs',
            'estimated_fee' => $interstateEstimate['fee'],
            'estimated_duration' => $interstateEstimate['duration'],
            'available' => $interstateEstimate['available'],
            'requires_hubs' => true,
            'available_routes' => $interstateEstimate['routes'] ?? [],
        ];

        return $options;
    }

    /**
     * Estimate metro delivery cost and time
     */
    protected function estimateMetroDelivery(ShopOrder $shopOrder, array $locationData): array
    {
        // Calculate based on distance and package weight
        $distance = $locationData['distance_km'] ?? 10;
        $baseFee = 500; // Base fee in currency
        $perKmRate = 50;
        $weightSurcharge = $shopOrder->items->sum('quantity') * 100;

        $estimatedFee = $baseFee + ($distance * $perKmRate) + $weightSurcharge;

        return [
            'fee' => round($estimatedFee, 2),
            'duration' => '1-3 hours',
            'available' => $distance <= 50, // Only available within 50km
        ];
    }

    /**
     * Estimate interstate delivery cost and time
     */
    protected function estimateInterstateDelivery(ShopOrder $shopOrder, array $locationData): array
    {
        // Get available routes
        $routes = \App\Models\Interstate\SupportedRoute::with(['originHub', 'destinationHub'])
            ->where('is_active', true)
            ->get();

        $availableRoutes = $routes->map(function ($route) {
            return [
                'route_id' => $route->id,
                'origin_hub' => [
                    'id' => $route->originHub->id,
                    'name' => $route->originHub->hub_name,
                    'city' => $route->originHub->city,
                ],
                'destination_hub' => [
                    'id' => $route->destinationHub->id,
                    'name' => $route->destinationHub->hub_name,
                    'city' => $route->destinationHub->city,
                ],
                'estimated_hours' => $route->standard_sla_hours,
                'price_per_kg' => $route->price_per_kg,
            ];
        });

        // Calculate estimated fee based on chargeable weight
        $totalWeight = $shopOrder->items->sum(function ($item) {
            return ($item->product->weight_kg ?? 1) * $item->quantity;
        });

        $estimatedFee = $routes->first() 
            ? $totalWeight * $routes->first()->price_per_kg 
            : 5000;

        return [
            'fee' => round(max($estimatedFee, 1000), 2),
            'duration' => '1-3 days',
            'available' => $routes->isNotEmpty(),
            'routes' => $availableRoutes,
        ];
    }
}
