<?php

namespace App\Http\Controllers\Api\V1\Shop;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Cart;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\Shop\ShopOrderDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends BaseController
{
    protected ShopOrderDeliveryService $deliveryService;

    public function __construct(ShopOrderDeliveryService $deliveryService)
    {
        $this->deliveryService = $deliveryService;
    }

    /**
     * List company orders
     */
    public function index(Request $request)
    {
        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('Company not found', 404);
        }

        $query = ShopOrder::with('items')
            ->forCompany($company->id);

        // Filter by status
        if ($request->has('status')) {
            $query->byStatus($request->input('status'));
        }

        // Filter by payment status
        if ($request->has('payment_status')) {
            $query->byPaymentStatus($request->input('payment_status'));
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 10));

        return $this->respondSuccess([
            'orders' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $order->payment_method,
                    'item_count' => $order->items->sum('quantity'),
                    'created_at' => $order->created_at,
                ];
            }),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Get order details
     */
    public function show($id)
    {
        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('Company not found', 404);
        }

        $order = ShopOrder::with('items')
            ->forCompany($company->id)
            ->findOrFail($id);

        return $this->respondSuccess([
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'subtotal' => $order->subtotal,
                'tax_amount' => $order->tax_amount,
                'discount_amount' => $order->discount_amount,
                'total_amount' => $order->total_amount,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'transaction_reference' => $order->transaction_reference,
                'paid_at' => $order->paid_at,
                'delivery_contact_name' => $order->delivery_contact_name,
                'delivery_contact_phone' => $order->delivery_contact_phone,
                'delivery_address' => $order->delivery_address,
                'delivery_notes' => $order->delivery_notes,
                'admin_notes' => $order->admin_notes,
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_name' => $item->product_name,
                        'product_image' => $item->product_image,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                    ];
                }),
                'created_at' => $order->created_at,
            ],
        ]);
    }

    /**
     * Get delivery options for checkout
     */
    public function getDeliveryOptions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'delivery_address' => 'required|string',
            'delivery_lat' => 'nullable|numeric',
            'delivery_lng' => 'nullable|numeric',
            'distance_km' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('Company not found', 404);
        }

        // Create temporary shop order for estimation
        $tempOrder = new ShopOrder([
            'company_id' => $company->id,
            'user_id' => auth()->id(),
        ]);

        // Load cart items for weight calculation
        $cartItems = Cart::with('product')
            ->forCompany($company->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return $this->respondError('Cart is empty', 400);
        }

        $tempOrder->setRelation('items', $cartItems);
        $tempOrder->setRelation('company', $company);

        $locationData = [
            'delivery_address' => $request->input('delivery_address'),
            'delivery_lat' => $request->input('delivery_lat'),
            'delivery_lng' => $request->input('delivery_lng'),
            'distance_km' => $request->input('distance_km', 10),
        ];

        $options = $this->deliveryService->getDeliveryOptions($tempOrder, $locationData);

        return $this->respondSuccess([
            'delivery_options' => $options,
        ]);
    }

    /**
     * Create order from cart with delivery type
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:flutterwave,bank_transfer',
            'delivery_type' => 'required|in:metro,interstate',
            'delivery_contact_name' => 'required|string|max:255',
            'delivery_contact_phone' => 'required|string|max:20',
            'delivery_address' => 'required|string',
            'delivery_lat' => 'nullable|numeric',
            'delivery_lng' => 'nullable|numeric',
            'delivery_notes' => 'nullable|string',
            // For metro delivery
            'pickup_address' => 'nullable|string',
            'pickup_lat' => 'nullable|numeric',
            'pickup_lng' => 'nullable|numeric',
            // For interstate delivery
            'origin_hub_id' => 'required_if:delivery_type,interstate|exists:trucking_hubs,id',
            'destination_hub_id' => 'required_if:delivery_type,interstate|exists:trucking_hubs,id',
            'delivery_fee' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('Company not found', 404);
        }

        // Get cart items
        $cartItems = Cart::with('product')
            ->forCompany($company->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return $this->respondError('Cart is empty', 400);
        }

        $deliveryType = $request->input('delivery_type');

        return DB::transaction(function () use ($request, $company, $cartItems, $deliveryType) {
            // Calculate totals
            $subtotal = $cartItems->sum(function ($item) {
                return $item->total_price;
            });

            $deliveryFee = $request->input('delivery_fee', 0);
            $tax = 0; // Add tax calculation if needed
            $total = $subtotal + $tax + $deliveryFee;

            // Create order
            $order = ShopOrder::create([
                'order_number' => ShopOrder::generateOrderNumber(),
                'company_id' => $company->id,
                'user_id' => auth()->id(),
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $total,
                'payment_method' => $request->input('payment_method'),
                'payment_status' => ShopOrder::PAYMENT_PENDING,
                'status' => ShopOrder::STATUS_PENDING,
                'delivery_type' => $deliveryType,
                'delivery_status' => 'pending',
                'delivery_contact_name' => $request->input('delivery_contact_name'),
                'delivery_contact_phone' => $request->input('delivery_contact_phone'),
                'delivery_address' => $request->input('delivery_address'),
                'delivery_lat' => $request->input('delivery_lat'),
                'delivery_lng' => $request->input('delivery_lng'),
                'pickup_address' => $request->input('pickup_address'),
                'pickup_lat' => $request->input('pickup_lat'),
                'pickup_lng' => $request->input('pickup_lng'),
                'origin_hub_id' => $request->input('origin_hub_id'),
                'destination_hub_id' => $request->input('destination_hub_id'),
                'delivery_notes' => $request->input('delivery_notes'),
            ]);

            // Create order items
            foreach ($cartItems as $cartItem) {
                ShopOrderItem::create([
                    'shop_order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'product_name' => $cartItem->product->name,
                    'product_sku' => $cartItem->product->sku,
                    'product_image' => $cartItem->product->first_image,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->unit_price,
                    'total_price' => $cartItem->total_price,
                ]);
            }

            // Clear cart
            Cart::clearCart($company->id);

            // Create logistics delivery request
            try {
                $deliveryData = [
                    'pickup_address' => $request->input('pickup_address'),
                    'pickup_lat' => $request->input('pickup_lat'),
                    'pickup_lng' => $request->input('pickup_lng'),
                    'delivery_lat' => $request->input('delivery_lat'),
                    'delivery_lng' => $request->input('delivery_lng'),
                    'origin_hub_id' => $request->input('origin_hub_id'),
                    'destination_hub_id' => $request->input('destination_hub_id'),
                    'service_location_id' => $request->input('service_location_id'),
                    'zone_type_id' => $request->input('zone_type_id'),
                ];

                $logisticsRequest = $this->deliveryService->createDeliveryRequest($order, $deliveryType, $deliveryData);

                return $this->respondSuccess([
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'total_amount' => $order->total_amount,
                        'delivery_fee' => $order->delivery_fee,
                        'payment_method' => $order->payment_method,
                        'payment_status' => $order->payment_status,
                        'delivery_type' => $order->delivery_type,
                        'delivery_status' => $order->delivery_status,
                        'logistics_request_id' => $logisticsRequest?->id,
                        'logistics_request_number' => $logisticsRequest?->request_number,
                    ],
                ], 'Order created successfully with ' . $deliveryType . ' delivery');

            } catch (\Exception $e) {
                // Order created but logistics failed - order can proceed manually
                return $this->respondSuccess([
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'total_amount' => $order->total_amount,
                        'delivery_fee' => $order->delivery_fee,
                        'payment_method' => $order->payment_method,
                        'payment_status' => $order->payment_status,
                        'delivery_type' => $order->delivery_type,
                        'delivery_status' => $order->delivery_status,
                    ],
                    'warning' => 'Order created but delivery request could not be created automatically. Please contact support.',
                ], 'Order created successfully');
            }
        });
    }

    /**
     * Submit bank transfer proof
     */
    public function submitBankTransfer(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'proof' => 'required|image|max:5120',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('Company not found', 404);
        }

        $order = ShopOrder::forCompany($company->id)
            ->findOrFail($id);

        if ($order->payment_method !== 'bank_transfer') {
            return $this->respondError('Order is not using bank transfer', 400);
        }

        if ($order->isPaid()) {
            return $this->respondError('Order is already paid', 400);
        }

        // Store proof
        $proofPath = $request->file('proof')->store('bank-transfers', 'public');

        $order->update([
            'bank_transfer_proof' => $proofPath,
            'bank_transfer_submitted_at' => now(),
        ]);

        return $this->respondSuccess([], 'Bank transfer proof submitted');
    }

    protected function respondWithValidationErrors($validator)
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);
    }
}
