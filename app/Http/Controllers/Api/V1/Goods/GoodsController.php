<?php

namespace App\Http\Controllers\Api\V1\Goods;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Interstate\GoodsItem;
use App\Models\Interstate\GoodsPaymentLeg;
use App\Models\Interstate\TrackingUpdate;
use App\Models\Interstate\TruckingCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Goods API Controller
 *
 * Goods = shipment payloads being transported.
 * This controller contains ZERO ecommerce/shop logic.
 *
 * Endpoints:
 *  - lookup by goods_id (validation before handover)
 *  - receive goods (company accepts handover)
 *  - add tracking note
 *  - add transport/insurance cost
 *  - dispatch goods
 *  - user tracking view
 *  - payment legs summary
 */
class GoodsController extends BaseController
{
    // ══════════════════════════════════════════════════════════════════════════
    // COMPANY SIDE
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Lookup goods by goods_id — validates before company accepts handover.
     * POST /api/v1/goods/lookup
     */
    public function lookup(Request $request)
    {
        $request->validate([
            'goods_id' => 'required|string|max:50',
        ]);

        $item = GoodsItem::with(['request', 'truckingCompany'])
            ->byGoodsId($request->goods_id)
            ->first();

        if (!$item) {
            return $this->respondError('Goods ID not found. Verify the ID and try again.', 404);
        }

        if (in_array($item->status, ['delivered', 'cancelled'])) {
            return $this->respondError(
                "Goods already {$item->status}. Cannot process handover.",
                422
            );
        }

        return $this->respondSuccess([
            'goods_id'        => $item->goods_id,
            'item_number'     => $item->item_number,
            'status'          => $item->status,
            'status_label'    => $item->getStatusLabel(),
            'description'     => $item->description,
            'category'        => $item->getCategoryLabel(),
            'weight_kg'       => $item->weight_kg,
            'quantity'        => $item->quantity,
            'is_fragile'      => $item->is_fragile,
            'requires_insurance' => $item->requires_insurance,
            'origin'          => $item->origin_address,
            'destination'     => $item->destination_address,
            'current_handler' => [
                'type' => $item->current_handler_type,
                'id'   => $item->current_handler_id,
                'name' => $item->current_handler_name,
            ],
            'can_receive'     => true,
        ], 'Goods found');
    }

    /**
     * Trucking company receives goods (handover confirmed).
     * POST /api/v1/goods/receive
     */
    public function receive(Request $request)
    {
        $request->validate([
            'goods_id' => 'required|string|max:50',
            'note'     => 'nullable|string|max:1000',
        ]);

        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return $this->respondError('No trucking company linked to your account.', 403);
        }

        $item = GoodsItem::byGoodsId($request->goods_id)->first();

        if (!$item) {
            return $this->respondError('Goods ID not found.', 404);
        }

        if (in_array($item->status, ['delivered', 'cancelled'])) {
            return $this->respondError("Cannot receive goods with status: {$item->status}", 422);
        }

        // Security: prevent receiving goods assigned to another company
        if ($item->trucking_company_id && $item->trucking_company_id !== $company->id) {
            return $this->respondError(
                'These goods are assigned to a different company. Access denied.',
                403
            );
        }

        DB::beginTransaction();
        try {
            // Record handover in the immutable chain
            $item->recordHandover(
                toType: 'trucking_company',
                toId: $company->id,
                toName: $company->company_name,
                recordedByUserId: Auth::id(),
                note: $request->note ?? 'Goods received at company'
            );

            $item->update([
                'trucking_company_id'      => $company->id,
                'received_by_company_at'   => now(),
                'status'                   => 'in_transit',
            ]);

            // Append tracking update (visible to user)
            TrackingUpdate::create([
                'request_id'        => $item->request_id,
                'goods_item_id'     => $item->id,
                'update_type'       => 'status_change',
                'previous_status'   => 'pending_pricing',
                'new_status'        => 'in_transit',
                'message'           => "Goods received by {$company->company_name}. " . ($request->note ?? ''),
                'created_by_type'   => 'trucking_company',
                'created_by_id'     => $company->id,
                'is_customer_visible' => true,
                'is_handover'       => true,
                'handover_from_type'=> 'dispatch_rider',
                'handover_to_type'  => 'trucking_company',
                'handover_to_id'    => $company->id,
            ]);

            // Notify user (fire event)
            event(new \App\Events\Interstate\GoodsHandoverRecorded($item, $company, Auth::id()));

            DB::commit();
            return $this->respondSuccess([
                'goods_id'   => $item->goods_id,
                'status'     => $item->status,
                'handler'    => $company->company_name,
                'received_at'=> now()->toIso8601String(),
            ], 'Goods received and recorded successfully');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('GoodsController::receive failed', ['error' => $e->getMessage()]);
            return $this->respondError('Failed to record goods receipt. Please try again.', 500);
        }
    }

    /**
     * Add a tracking note to goods.
     * POST /api/v1/goods/{goods_id}/note
     */
    public function addNote(Request $request, string $goodsId)
    {
        $request->validate([
            'note'          => 'required|string|max:1000',
            'location'      => 'nullable|string|max:500',
            'latitude'      => 'nullable|numeric|between:-90,90',
            'longitude'     => 'nullable|numeric|between:-180,180',
            'visible_to_user' => 'nullable|boolean',
        ]);

        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return $this->respondError('No trucking company linked to your account.', 403);
        }

        $item = GoodsItem::byGoodsId($goodsId)
            ->forCompany($company->id)
            ->first();

        if (!$item) {
            return $this->respondError('Goods not found or not assigned to your company.', 404);
        }

        $tracking = TrackingUpdate::create([
            'request_id'          => $item->request_id,
            'goods_item_id'       => $item->id,
            'update_type'         => 'note',
            'message'             => $request->note,
            'location_name'       => $request->location,
            'latitude'            => $request->latitude,
            'longitude'           => $request->longitude,
            'created_by_type'     => 'trucking_company',
            'created_by_id'       => $company->id,
            'created_by_name'     => $company->company_name,
            'is_customer_visible' => $request->boolean('visible_to_user', true),
        ]);

        // Update the goods item's tracking notes field
        $item->update(['tracking_notes' => $request->note]);

        // Notify user if visible
        if ($tracking->is_customer_visible) {
            event(new \App\Events\Interstate\GoodsTrackingUpdated($item, $tracking));
        }

        return $this->respondSuccess([
            'tracking_id' => $tracking->id,
            'goods_id'    => $goodsId,
            'note'        => $request->note,
            'timestamp'   => $tracking->created_at->toIso8601String(),
        ], 'Tracking note added');
    }

    /**
     * Add transport cost and/or insurance cost to goods.
     * POST /api/v1/goods/{goods_id}/costs
     */
    public function addCosts(Request $request, string $goodsId)
    {
        $request->validate([
            'transport_cost'  => 'nullable|numeric|min:0',
            'insurance_cost'  => 'nullable|numeric|min:0',
            'handling_cost'   => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string|max:500',
        ]);

        if (!$request->transport_cost && !$request->insurance_cost && !$request->handling_cost) {
            return $this->respondError('At least one cost amount is required.', 422);
        }

        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return $this->respondError('No trucking company linked to your account.', 403);
        }

        $item = GoodsItem::with(['request'])
            ->byGoodsId($goodsId)
            ->forCompany($company->id)
            ->first();

        if (!$item) {
            return $this->respondError('Goods not found or not assigned to your company.', 404);
        }

        DB::beginTransaction();
        try {
            $createdLegs = [];

            // Create payment leg for transport cost
            if ($request->transport_cost > 0) {
                $leg = GoodsPaymentLeg::create([
                    'goods_id'       => $item->goods_id,
                    'goods_item_id'  => $item->id,
                    'request_id'     => $item->request_id,
                    'payer_user_id'  => $item->request->user_id,
                    'handler_type'   => 'trucking_company',
                    'handler_id'     => $company->id,
                    'handler_name'   => $company->company_name,
                    'leg_type'       => 'interstate_transport',
                    'amount'         => $request->transport_cost,
                    'payment_status' => 'pending',
                    'is_unlocked'    => true,
                ]);
                $createdLegs[] = $leg;

                // Log as tracking update
                TrackingUpdate::create([
                    'request_id'        => $item->request_id,
                    'goods_item_id'     => $item->id,
                    'update_type'       => 'cost_update',
                    'message'           => "Transport cost added: ₦" . number_format($request->transport_cost, 2) . ($request->notes ? " — {$request->notes}" : ''),
                    'created_by_type'   => 'trucking_company',
                    'created_by_id'     => $company->id,
                    'is_customer_visible' => true,
                    'is_cost_update'    => true,
                    'cost_type'         => 'transport',
                    'cost_amount'       => $request->transport_cost,
                ]);
            }

            // Create payment leg for insurance cost
            if ($request->insurance_cost > 0) {
                $leg = GoodsPaymentLeg::create([
                    'goods_id'       => $item->goods_id,
                    'goods_item_id'  => $item->id,
                    'request_id'     => $item->request_id,
                    'payer_user_id'  => $item->request->user_id,
                    'handler_type'   => 'trucking_company',
                    'handler_id'     => $company->id,
                    'handler_name'   => $company->company_name,
                    'leg_type'       => 'insurance',
                    'amount'         => $request->insurance_cost,
                    'payment_status' => 'pending',
                    'is_unlocked'    => true,
                ]);
                $createdLegs[] = $leg;

                TrackingUpdate::create([
                    'request_id'        => $item->request_id,
                    'goods_item_id'     => $item->id,
                    'update_type'       => 'cost_update',
                    'message'           => "Insurance cost added: ₦" . number_format($request->insurance_cost, 2),
                    'created_by_type'   => 'trucking_company',
                    'created_by_id'     => $company->id,
                    'is_customer_visible' => true,
                    'is_cost_update'    => true,
                    'cost_type'         => 'insurance',
                    'cost_amount'       => $request->insurance_cost,
                ]);
            }

            // Handling cost
            if ($request->handling_cost > 0) {
                GoodsPaymentLeg::create([
                    'goods_id'       => $item->goods_id,
                    'goods_item_id'  => $item->id,
                    'request_id'     => $item->request_id,
                    'payer_user_id'  => $item->request->user_id,
                    'handler_type'   => 'trucking_company',
                    'handler_id'     => $company->id,
                    'handler_name'   => $company->company_name,
                    'leg_type'       => 'handling',
                    'amount'         => $request->handling_cost,
                    'payment_status' => 'pending',
                    'is_unlocked'    => true,
                ]);
            }

            // Update item with latest fees
            $item->update([
                'transportation_service_fee' => ($item->transportation_service_fee ?? 0) + ($request->transport_cost ?? 0),
                'insurance_fee'              => ($item->insurance_fee ?? 0) + ($request->insurance_cost ?? 0),
                'total_service_fee'          => ($item->total_service_fee ?? 0)
                    + ($request->transport_cost ?? 0)
                    + ($request->insurance_cost ?? 0)
                    + ($request->handling_cost ?? 0),
                'status' => 'priced',
            ]);

            // Notify user of new costs
            event(new \App\Events\Interstate\GoodsCostsAdded($item, $company, Auth::id()));

            DB::commit();
            return $this->respondSuccess([
                'goods_id'         => $goodsId,
                'transport_cost'   => $request->transport_cost ?? 0,
                'insurance_cost'   => $request->insurance_cost ?? 0,
                'handling_cost'    => $request->handling_cost ?? 0,
                'total_service_fee'=> $item->fresh()->total_service_fee,
                'payment_status'   => 'pending',
                'legs_created'     => count($createdLegs),
            ], 'Costs recorded and user notified');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('GoodsController::addCosts failed', ['error' => $e->getMessage()]);
            return $this->respondError('Failed to save costs. Please try again.', 500);
        }
    }

    /**
     * Mark goods as dispatched (company sends it on to next leg).
     * POST /api/v1/goods/{goods_id}/dispatch
     */
    public function dispatchGoods(Request $request, string $goodsId)
    {
        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $company = $this->getAuthenticatedCompany();
        if (!$company) { return $this->respondError('No company linked.', 403); }

        $item = GoodsItem::byGoodsId($goodsId)->forCompany($company->id)->first();
        if (!$item) { return $this->respondError('Goods not found.', 404); }

        $item->update([
            'dispatched_at' => now(),
            'status'        => 'in_transit',
        ]);

        TrackingUpdate::create([
            'request_id'        => $item->request_id,
            'goods_item_id'     => $item->id,
            'update_type'       => 'status_change',
            'previous_status'   => $item->getOriginal('status'),
            'new_status'        => 'in_transit',
            'message'           => "Goods dispatched by {$company->company_name}. " . ($request->note ?? ''),
            'created_by_type'   => 'trucking_company',
            'created_by_id'     => $company->id,
            'is_customer_visible' => true,
        ]);

        event(new \App\Events\Interstate\GoodsTrackingUpdated($item, null));

        return $this->respondSuccess([
            'goods_id'     => $goodsId,
            'status'       => 'in_transit',
            'dispatched_at'=> now()->toIso8601String(),
        ], 'Goods dispatched');
    }

    /**
     * Mark goods as delivered.
     * POST /api/v1/goods/{goods_id}/deliver
     */
    public function markDelivered(Request $request, string $goodsId)
    {
        $request->validate(['note' => 'nullable|string|max:500']);

        $company = $this->getAuthenticatedCompany();
        if (!$company) { return $this->respondError('No company linked.', 403); }

        $item = GoodsItem::byGoodsId($goodsId)->forCompany($company->id)->first();
        if (!$item) { return $this->respondError('Goods not found.', 404); }

        $item->update([
            'delivered_at' => now(),
            'status'       => 'delivered',
        ]);

        TrackingUpdate::create([
            'request_id'        => $item->request_id,
            'goods_item_id'     => $item->id,
            'update_type'       => 'status_change',
            'previous_status'   => 'in_transit',
            'new_status'        => 'delivered',
            'message'           => 'Goods delivered successfully. ' . ($request->note ?? ''),
            'created_by_type'   => 'trucking_company',
            'created_by_id'     => $company->id,
            'is_customer_visible' => true,
        ]);

        event(new \App\Events\Interstate\GoodsTrackingUpdated($item, null));

        return $this->respondSuccess([
            'goods_id'     => $goodsId,
            'status'       => 'delivered',
            'delivered_at' => now()->toIso8601String(),
        ], 'Goods marked as delivered');
    }

    /**
     * List all goods for the authenticated company.
     * GET /api/v1/goods
     */
    public function index(Request $request)
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) { return $this->respondError('No company linked.', 403); }

        $query = GoodsItem::with(['request', 'trackingUpdates' => fn($q) => $q->limit(1)])
            ->forCompany($company->id);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $goods = $query->latest()->paginate($request->input('per_page', 20));

        return $this->respondSuccess([
            'goods' => $goods->map(fn($item) => $this->formatGoodsItem($item)),
            'pagination' => [
                'current_page' => $goods->currentPage(),
                'last_page'    => $goods->lastPage(),
                'per_page'     => $goods->perPage(),
                'total'        => $goods->total(),
            ],
        ]);
    }

    /**
     * Get details + full tracking log for one goods item (company view).
     * GET /api/v1/goods/{goods_id}
     */
    public function show(string $goodsId)
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) { return $this->respondError('No company linked.', 403); }

        $item = GoodsItem::with(['request', 'trackingUpdates', 'paymentLegs'])
            ->byGoodsId($goodsId)
            ->forCompany($company->id)
            ->first();

        if (!$item) { return $this->respondError('Goods not found.', 404); }

        return $this->respondSuccess([
            'goods'        => $this->formatGoodsItem($item),
            'tracking'     => $item->trackingUpdates->map(fn($t) => [
                'id'          => $t->id,
                'type'        => $t->update_type,
                'message'     => $t->message,
                'location'    => $t->location_name,
                'is_handover' => $t->is_handover ?? false,
                'is_cost'     => $t->is_cost_update ?? false,
                'cost_amount' => $t->cost_amount,
                'timestamp'   => $t->created_at->toIso8601String(),
            ]),
            'payment_legs' => $item->paymentLegs->map(fn($leg) => [
                'id'             => $leg->id,
                'leg_type'       => $leg->leg_type,
                'leg_type_label' => $leg->getLegTypeLabel(),
                'amount'         => $leg->amount,
                'amount_paid'    => $leg->amount_paid,
                'balance_due'    => $leg->balance_due,
                'status'         => $leg->payment_status,
            ]),
            'handover_chain' => $item->handover_chain ?? [],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // USER SIDE — Tracking
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * User looks up their goods shipment by goods_id.
     * GET /api/v1/user/goods/{goods_id}/tracking
     */
    public function userTracking(string $goodsId)
    {
        $userId = Auth::id();

        $item = GoodsItem::with(['trackingUpdates' => fn($q) => $q->visibleToCustomer()->latest(), 'paymentLegs'])
            ->byGoodsId($goodsId)
            ->forUser($userId)
            ->first();

        if (!$item) {
            return $this->respondError('Shipment not found. Check your Goods ID.', 404);
        }

        return $this->respondSuccess([
            'goods_id'        => $item->goods_id,
            'item_number'     => $item->item_number,
            'status'          => $item->status,
            'status_label'    => $item->getStatusLabel(),
            'description'     => $item->description,
            'current_handler' => [
                'type' => $item->current_handler_type,
                'name' => $item->current_handler_name,
            ],
            'origin'          => $item->origin_address,
            'destination'     => $item->destination_address,
            'picked_up_at'    => $item->picked_up_at?->toIso8601String(),
            'estimated_delivery' => null, // Populate from request if available
            'tracking_timeline' => $item->trackingUpdates->map(fn($t) => [
                'message'   => $t->message,
                'type'      => $t->update_type,
                'location'  => $t->location_name,
                'timestamp' => $t->created_at->toIso8601String(),
            ]),
            'payment_summary' => [
                'total_fees'   => $item->paymentLegs->sum('amount'),
                'total_paid'   => $item->paymentLegs->sum('amount_paid'),
                'balance_due'  => $item->paymentLegs->sum('amount') - $item->paymentLegs->sum('amount_paid'),
                'payment_status' => $item->payment_status ?? 'unpaid',
                'legs'         => $item->paymentLegs->map(fn($leg) => [
                    'leg_type'  => $leg->getLegTypeLabel(),
                    'amount'    => $leg->amount,
                    'paid'      => $leg->amount_paid,
                    'status'    => $leg->payment_status,
                ]),
            ],
            'handover_chain'  => collect($item->handover_chain ?? [])->map(fn($h) => [
                'from'         => $h['from_name'] ?? 'Origin',
                'to'           => $h['to_name'],
                'type'         => $h['to_type'],
                'handover_at'  => $h['handover_at'],
                'note'         => $h['note'] ?? null,
            ]),
        ], 'Shipment tracking loaded');
    }

    /**
     * List all goods shipments for the authenticated user.
     * GET /api/v1/user/goods
     */
    public function userGoodsList(Request $request)
    {
        $userId = Auth::id();

        $goods = GoodsItem::with(['paymentLegs'])
            ->forUser($userId)
            ->latest()
            ->paginate($request->input('per_page', 20));

        return $this->respondSuccess([
            'shipments' => $goods->map(fn($item) => [
                'goods_id'       => $item->goods_id,
                'item_number'    => $item->item_number,
                'description'    => $item->description,
                'status'         => $item->status,
                'status_label'   => $item->getStatusLabel(),
                'status_badge'   => $item->getStatusBadgeClass(),
                'current_handler'=> $item->current_handler_name,
                'total_fees'     => $item->paymentLegs->sum('amount'),
                'balance_due'    => $item->paymentLegs->sum('amount') - $item->paymentLegs->sum('amount_paid'),
                'payment_status' => $item->payment_status ?? 'unpaid',
                'created_at'     => $item->created_at->toIso8601String(),
            ]),
            'pagination' => [
                'current_page' => $goods->currentPage(),
                'last_page'    => $goods->lastPage(),
                'total'        => $goods->total(),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Payment — User pays a goods leg
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Get payment legs for a shipment.
     * GET /api/v1/user/goods/{goods_id}/payment
     */
    public function paymentSummary(string $goodsId)
    {
        $userId = Auth::id();

        $item = GoodsItem::with('paymentLegs')
            ->byGoodsId($goodsId)
            ->forUser($userId)
            ->first();

        if (!$item) { return $this->respondError('Shipment not found.', 404); }

        $legs = $item->paymentLegs;

        return $this->respondSuccess([
            'goods_id'       => $goodsId,
            'payment_status' => $item->payment_status ?? 'unpaid',
            'total_fees'     => $legs->sum('amount'),
            'total_paid'     => $legs->sum('amount_paid'),
            'balance_due'    => $legs->sum('amount') - $legs->sum('amount_paid'),
            'legs'           => $legs->map(fn($leg) => [
                'id'          => $leg->id,
                'leg_type'    => $leg->leg_type,
                'label'       => $leg->getLegTypeLabel(),
                'amount'      => $leg->amount,
                'amount_paid' => $leg->amount_paid,
                'balance_due' => $leg->balance_due,
                'status'      => $leg->payment_status,
                'handler'     => $leg->handler_name,
            ]),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Private helpers
    // ══════════════════════════════════════════════════════════════════════════

    private function getAuthenticatedCompany(): ?TruckingCompany
    {
        $user = Auth::user();
        if (!$user) return null;
        return TruckingCompany::where('user_id', $user->id)->first();
    }

    private function formatGoodsItem(GoodsItem $item): array
    {
        return [
            'id'                   => $item->id,
            'goods_id'             => $item->goods_id,
            'item_number'          => $item->item_number,
            'description'          => $item->description,
            'category'             => $item->getCategoryLabel(),
            'status'               => $item->status,
            'status_label'         => $item->getStatusLabel(),
            'status_badge'         => $item->getStatusBadgeClass(),
            'weight_kg'            => $item->weight_kg,
            'quantity'             => $item->quantity,
            'is_fragile'           => $item->is_fragile,
            'requires_insurance'   => $item->requires_insurance,
            'origin'               => $item->origin_address,
            'destination'          => $item->destination_address,
            'current_handler_type' => $item->current_handler_type,
            'current_handler_name' => $item->current_handler_name,
            'transport_cost'       => $item->transportation_service_fee,
            'insurance_cost'       => $item->insurance_fee,
            'total_service_fee'    => $item->total_service_fee,
            'payment_status'       => $item->payment_status ?? 'unpaid',
            'tracking_notes'       => $item->tracking_notes,
            'picked_up_at'         => $item->picked_up_at?->toIso8601String(),
            'received_at'          => $item->received_by_company_at?->toIso8601String(),
            'dispatched_at'        => $item->dispatched_at?->toIso8601String(),
            'delivered_at'         => $item->delivered_at?->toIso8601String(),
            'created_at'           => $item->created_at->toIso8601String(),
        ];
    }
}
