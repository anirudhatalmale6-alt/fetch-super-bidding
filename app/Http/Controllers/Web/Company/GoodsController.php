<?php

namespace App\Http\Controllers\Web\Company;

use App\Http\Controllers\Controller;
use App\Models\Interstate\GoodsItem;
use App\Models\Interstate\RequestLeg;
use App\Models\Interstate\TruckingCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Company Goods Controller — Logistics Hub
 *
 * Goods = shipment payloads being transported from/to company fleet.
 * Tracks items received, sent out, with memos and status updates.
 */
class GoodsController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // Goods List & Filtering
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Display all goods assigned to the company — logistics tab.
     */
    public function index()
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return redirect()->back()->with('error', 'Company not found');
        }

        $goods = GoodsItem::with(['request', 'requestLeg'])
            ->forCompany($company->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $stats = [
            'pending_pricing' => GoodsItem::forCompany($company->id)->pendingPricing()->count(),
            'priced'          => GoodsItem::forCompany($company->id)->priced()->count(),
            'in_transit'      => GoodsItem::forCompany($company->id)->where('status', 'in_transit')->count(),
            'delivered'       => GoodsItem::forCompany($company->id)->where('status', 'delivered')->count(),
        ];

        return view('company.goods.index', compact('goods', 'company', 'stats'));
    }

    /**
     * Show goods pending pricing.
     */
    public function pendingPricing()
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return redirect()->back()->with('error', 'Company not found');
        }

        $goods = GoodsItem::with(['request', 'requestLeg'])
            ->forCompany($company->id)
            ->pendingPricing()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('company.goods.pending', compact('goods', 'company'));
    }

    /**
     * Show form to add/edit pricing for a goods item.
     */
    public function editPricing($id)
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return redirect()->back()->with('error', 'Company not found');
        }

        $item = GoodsItem::with(['request', 'requestLeg'])
            ->forCompany($company->id)
            ->findOrFail($id);

        $suggestedPricePerKg   = $company->default_price_per_kg ?? 1000;
        $suggestedInsuranceRate = $company->insurance_rate_percent ?? 1.0;
        $suggestedBasePrice     = $item->calculateBasePrice($suggestedPricePerKg);
        $suggestedInsuranceFee  = $item->requires_insurance
            ? $item->calculateInsuranceFee($suggestedInsuranceRate)
            : 0;
        $suggestedTotal = $suggestedBasePrice + $suggestedInsuranceFee;

        return view('company.goods.pricing', compact(
            'item', 'company', 'suggestedPricePerKg',
            'suggestedInsuranceRate', 'suggestedBasePrice',
            'suggestedInsuranceFee', 'suggestedTotal'
        ));
    }

    /**
     * Save pricing (transport cost + insurance) for a goods item.
     * Triggers real-time sync to user app.
     */
    public function savePricing(Request $request, $id)
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return redirect()->back()->with('error', 'Company not found');
        }

        $validated = $request->validate([
            'price_per_kg'    => 'required|numeric|min:0',
            'insurance_rate'  => 'required|numeric|min:0|max:100',
            'notes'           => 'nullable|string|max:500',
        ]);

        $item = GoodsItem::forCompany($company->id)->findOrFail($id);

        try {
            DB::beginTransaction();

            $item->applyCompanyPricing(
                $validated['price_per_kg'],
                $validated['insurance_rate'],
                Auth::id()
            );

            if (!empty($validated['notes'])) {
                $breakdown = $item->pricing_breakdown ?? [];
                $breakdown['company_notes'] = $validated['notes'];
                $item->update(['pricing_breakdown' => $breakdown]);
            }

            $this->updateLegPricing($item);

            // Real-time sync: notify user
            event(new \App\Events\Interstate\GoodsCostsAdded($item, $company, Auth::id()));

            DB::commit();
            return redirect()->route('company.goods.index')
                ->with('success', 'Pricing saved — user notified. Item: ' . $item->item_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error saving pricing: ' . $e->getMessage());
        }
    }

    /**
     * Bulk pricing for multiple goods items.
     */
    public function bulkPricing(Request $request)
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Company not found'], 403);
        }

        $validated = $request->validate([
            'item_ids'        => 'required|array',
            'item_ids.*'      => 'exists:trucking_goods_items,id',
            'price_per_kg'    => 'required|numeric|min:0',
            'insurance_rate'  => 'required|numeric|min:0|max:100',
        ]);

        try {
            DB::beginTransaction();

            $items = GoodsItem::forCompany($company->id)
                ->whereIn('id', $validated['item_ids'])
                ->get();

            $updatedCount = 0;
            foreach ($items as $item) {
                $item->applyCompanyPricing(
                    $validated['price_per_kg'],
                    $validated['insurance_rate'],
                    Auth::id()
                );
                $updatedCount++;
            }

            DB::commit();
            return response()->json([
                'success'       => true,
                'message'       => "Pricing applied to {$updatedCount} items",
                'updated_count' => $updatedCount,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * View goods item detail — includes tracking timeline + handover chain.
     */
    public function show($id)
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return redirect()->back()->with('error', 'Company not found');
        }

        $item = GoodsItem::with(['request', 'requestLeg', 'pricedBy', 'trackingUpdates', 'paymentLegs'])
            ->forCompany($company->id)
            ->findOrFail($id);

        return view('company.goods.show', compact('item', 'company'));
    }

    /**
     * Update shipment status (in_transit, delivered, damaged, lost).
     */
    public function updateStatus(Request $request, $id)
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return response()->json(['error' => 'Company not found'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:in_transit,delivered,damaged,lost',
        ]);

        $item = GoodsItem::forCompany($company->id)->findOrFail($id);

        $previousStatus = $item->status;
        $updateData = ['status' => $validated['status']];
        if ($validated['status'] === 'delivered') {
            $updateData['delivered_at'] = now();
        }
        $item->update($updateData);

        // Append tracking entry
        \App\Models\Interstate\TrackingUpdate::create([
            'request_id'        => $item->request_id,
            'goods_item_id'     => $item->id,
            'update_type'       => 'status_change',
            'previous_status'   => $previousStatus,
            'new_status'        => $validated['status'],
            'message'           => "Status updated to: " . $item->getStatusLabel(),
            'created_by_type'   => 'trucking_company',
            'created_by_id'     => $company->id,
            'is_customer_visible' => true,
        ]);

        event(new \App\Events\Interstate\GoodsTrackingUpdated($item, null));

        return response()->json([
            'success'      => true,
            'message'      => 'Status updated',
            'new_status'   => $validated['status'],
            'status_label' => $item->getStatusLabel(),
        ]);
    }

    /**
     * Add a tracking note (location, message, timestamp).
     */
    public function addStatusUpdate(Request $request, $id)
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return redirect()->back()->with('error', 'Company not found');
        }

        $validated = $request->validate([
            'status_type'      => 'required|in:location_update,departure,arrival,custom',
            'message'          => 'required|string|max:1000',
            'location_address' => 'nullable|string|max:500',
            'latitude'         => 'nullable|numeric',
            'longitude'        => 'nullable|numeric',
            'update_timestamp' => 'required|date',
        ]);

        $item = GoodsItem::with('request')->forCompany($company->id)->findOrFail($id);

        try {
            DB::beginTransaction();

            $tracking = \App\Models\Interstate\TrackingUpdate::create([
                'request_id'      => $item->request_id,
                'goods_item_id'   => $item->id,
                'update_type'     => $validated['status_type'],
                'message'         => $validated['message'],
                'location_name'   => $validated['location_address'] ?? null,
                'latitude'        => $validated['latitude'] ?? null,
                'longitude'       => $validated['longitude'] ?? null,
                'created_by_type' => 'trucking_company',
                'created_by_id'   => $company->id,
                'created_by_name' => $company->company_name,
                'is_customer_visible' => true,
            ]);

            $item->update(['tracking_notes' => $validated['message']]);

            event(new \App\Events\Interstate\GoodsTrackingUpdated($item, $tracking));

            DB::commit();
            return redirect()->back()->with('success', 'Tracking note added and user notified');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Save transport and insurance fees — creates payment legs and notifies user instantly.
     */
    public function saveFees(Request $request, $id)
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return redirect()->back()->with('error', 'Company not found');
        }

        $validated = $request->validate([
            'transportation_service_fee' => 'required|numeric|min:0',
            'insurance_fee'              => 'required|numeric|min:0',
            'notes'                      => 'nullable|string|max:500',
        ]);

        $item = GoodsItem::with('request')->forCompany($company->id)->findOrFail($id);

        try {
            DB::beginTransaction();

            $totalFee = $validated['transportation_service_fee'] + $validated['insurance_fee'];

            $item->update([
                'transportation_service_fee' => $validated['transportation_service_fee'],
                'insurance_fee'              => $validated['insurance_fee'],
                'total_service_fee'          => $totalFee,
                'fee_added_at'               => now(),
                'fee_breakdown'              => [
                    'transportation_service_fee' => $validated['transportation_service_fee'],
                    'insurance_fee'              => $validated['insurance_fee'],
                    'total_service_fee'          => $totalFee,
                    'company_notes'              => $validated['notes'] ?? null,
                    'added_by'                   => Auth::id(),
                    'added_at'                   => now()->toIso8601String(),
                ],
                'status' => 'priced',
            ]);

            // Create payment legs so user can pay each fee
            if ($validated['transportation_service_fee'] > 0 && $item->request) {
                \App\Models\Interstate\GoodsPaymentLeg::firstOrCreate(
                    [
                        'goods_id'   => $item->goods_id,
                        'leg_type'   => 'interstate_transport',
                        'handler_id' => $company->id,
                    ],
                    [
                        'goods_item_id'  => $item->id,
                        'request_id'     => $item->request_id,
                        'payer_user_id'  => $item->request->user_id,
                        'handler_type'   => 'trucking_company',
                        'handler_name'   => $company->company_name,
                        'amount'         => $validated['transportation_service_fee'],
                        'payment_status' => 'pending',
                        'is_unlocked'    => true,
                    ]
                );
            }

            if ($validated['insurance_fee'] > 0 && $item->request) {
                \App\Models\Interstate\GoodsPaymentLeg::firstOrCreate(
                    [
                        'goods_id'   => $item->goods_id,
                        'leg_type'   => 'insurance',
                        'handler_id' => $company->id,
                    ],
                    [
                        'goods_item_id'  => $item->id,
                        'request_id'     => $item->request_id,
                        'payer_user_id'  => $item->request->user_id,
                        'handler_type'   => 'trucking_company',
                        'handler_name'   => $company->company_name,
                        'amount'         => $validated['insurance_fee'],
                        'payment_status' => 'pending',
                        'is_unlocked'    => true,
                    ]
                );
            }

            // Log tracking update
            \App\Models\Interstate\TrackingUpdate::create([
                'request_id'        => $item->request_id,
                'goods_item_id'     => $item->id,
                'update_type'       => 'cost_update',
                'message'           => "Total fees set: ₦" . number_format($totalFee, 2)
                    . " (Transport: ₦" . number_format($validated['transportation_service_fee'], 2)
                    . ", Insurance: ₦" . number_format($validated['insurance_fee'], 2) . ")"
                    . ($validated['notes'] ? " — {$validated['notes']}" : ''),
                'created_by_type'   => 'trucking_company',
                'created_by_id'     => $company->id,
                'is_customer_visible' => true,
                'is_cost_update'    => true,
                'cost_type'         => 'transport',
                'cost_amount'       => $totalFee,
            ]);

            // Real-time sync to user app
            event(new \App\Events\Interstate\GoodsCostsAdded($item, $company, Auth::id()));

            DB::commit();
            return redirect()->route('company.goods.index')
                ->with('success', 'Fees saved and user notified — Item: ' . $item->item_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error saving fees: ' . $e->getMessage());
        }
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

    private function updateLegPricing(GoodsItem $item): void
    {
        if (!$item->request_leg_id) return;

        $leg = RequestLeg::find($item->request_leg_id);
        if (!$leg) return;

        $allItems = GoodsItem::where('request_leg_id', $leg->id)->get();

        $totalBasePrice  = $allItems->sum('company_base_price');
        $totalInsurance  = $allItems->sum('company_insurance_fee');
        $totalPrice      = $allItems->sum('company_total_price');
        $commissionRate  = 10;
        $commission      = $totalPrice * ($commissionRate / 100);
        $providerPayout  = $totalPrice - $commission;

        $leg->update([
            'provider_base_price'    => $totalBasePrice,
            'insurance_fee'          => $totalInsurance,
            'platform_commission'    => $commission,
            'total_leg_price'        => $totalPrice,
            'provider_payout_amount' => $providerPayout,
        ]);
    }
}
