<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request\Request;
use App\Services\Interstate\StageManager;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Validator;

class InterstateOrderController extends Controller
{
    protected StageManager $stageManager;

    public function __construct(StageManager $stageManager)
    {
        $this->stageManager = $stageManager;
    }

    /**
     * List all interstate orders
     */
    public function index(HttpRequest $request)
    {
        $query = Request::with(['userDetail', 'truckingCompany', 'originHub', 'destinationHub'])
            ->where('delivery_mode', 'interstate')
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('stage')) {
            $query->whereHas('orderStages', function ($q) use ($request) {
                $q->where('stage_code', $request->input('stage'))
                    ->whereIn('status', ['in_progress', 'pending']);
            });
        }

        if ($request->has('company_id')) {
            $query->where('trucking_company_id', $request->input('company_id'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('userDetail', function ($sq) use ($search) {
                        $sq->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('phone', 'LIKE', "%{$search}%");
                    });
            });
        }

        $orders = $query->paginate(20);

        return view('admin.interstate.orders.index', compact('orders'));
    }

    /**
     * Show order details
     */
    public function show(string $id)
    {
        $order = Request::with([
            'userDetail',
            'truckingCompany',
            'originHub',
            'destinationHub',
            'packages',
            'legs',
            'bids',
            'trackingUpdates',
            'orderStages' => function ($q) {
                $q->orderBy('stage_number');
            }
        ])
            ->where('delivery_mode', 'interstate')
            ->findOrFail($id);

        $timeline = $this->stageManager->getTimeline($order);

        return view('admin.interstate.orders.show', compact('order', 'timeline'));
    }

    /**
     * Override stage (admin function)
     */
    public function overrideStage(HttpRequest $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'stage_code' => 'required|string|in:pending_pickup,picked_up,arrived_trucking_hub,inspection_pending,awaiting_user_approval,in_transit,arrived_destination_hub,last_mile_assigned,delivered,cancelled',
            'reason' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $order = Request::where('delivery_mode', 'interstate')->findOrFail($id);

        try {
            $newStage = $this->stageManager->overrideStage(
                $order,
                $request->input('stage_code'),
                auth()->id(),
                $request->input('reason')
            );

            return back()->with('success', "Stage overridden to '{$newStage->stage_name}' successfully");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to override stage: ' . $e->getMessage());
        }
    }

    /**
     * Cancel order
     */
    public function cancelOrder(HttpRequest $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:500',
            'refund_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $order = Request::where('delivery_mode', 'interstate')->findOrFail($id);

        // Update order status
        $order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'reason' => $request->input('reason'),
        ]);

        // Cancel all stages
        $this->stageManager->cancelRemainingStages($order, $request->input('reason'));

        // Log admin action
        \App\Models\Admin\AdminActionLog::log(
            auth()->id(),
            'order_cancellation',
            \App\Models\Admin\AdminActionLog::CATEGORY_ORDER,
            "Order {$order->request_number} cancelled by admin",
            [
                'target_type' => 'request',
                'target_id' => $order->id,
                'reason' => $request->input('reason'),
            ]
        );

        return back()->with('success', 'Order cancelled successfully');
    }

    /**
     * Reassign company
     */
    public function reassignCompany(HttpRequest $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|exists:trucking_companies,id',
            'reason' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $order = Request::where('delivery_mode', 'interstate')->findOrFail($id);
        $oldCompanyId = $order->trucking_company_id;
        $newCompanyId = $request->input('company_id');

        $order->update([
            'trucking_company_id' => $newCompanyId,
        ]);

        // Log admin action
        \App\Models\Admin\AdminActionLog::log(
            auth()->id(),
            'company_reassignment',
            \App\Models\Admin\AdminActionLog::CATEGORY_COMPANY,
            "Order {$order->request_number} reassigned from company {$oldCompanyId} to {$newCompanyId}",
            [
                'target_type' => 'request',
                'target_id' => $order->id,
                'previous_state' => ['company_id' => $oldCompanyId],
                'new_state' => ['company_id' => $newCompanyId],
                'reason' => $request->input('reason'),
            ]
        );

        return back()->with('success', 'Company reassigned successfully');
    }

    /**
     * Adjust fees
     */
    public function adjustFees(HttpRequest $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'field' => 'required|in:local_pickup_fee,interstate_transport_fee,local_delivery_fee,final_transportation_fee,final_insurance_fee',
            'amount' => 'required|numeric|min:0',
            'reason' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $order = Request::where('delivery_mode', 'interstate')->findOrFail($id);
        $field = $request->input('field');
        $newAmount = $request->input('amount');
        $oldAmount = $order->{$field};

        $order->update([
            $field => $newAmount,
        ]);

        // Log admin action
        \App\Models\Admin\AdminActionLog::logFeeAdjustment(
            auth()->id(),
            $order->id,
            $oldAmount ?? 0,
            $newAmount,
            $request->input('reason')
        );

        return back()->with('success', 'Fees adjusted successfully');
    }

    /**
     * Add manual tracking update
     */
    public function addTrackingUpdate(HttpRequest $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:500',
            'update_type' => 'required|in:status_change,location_update,inspection_note,delay_notification,general_update,hub_arrival,hub_departure,checkpoint_passed',
            'location_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $order = Request::where('delivery_mode', 'interstate')->findOrFail($id);

        \App\Models\Interstate\TrackingUpdate::create([
            'request_id' => $order->id,
            'message' => $request->input('message'),
            'update_type' => $request->input('update_type'),
            'location_name' => $request->input('location_name'),
            'created_by_type' => 'admin',
            'created_by_id' => auth()->id(),
            'created_by_name' => auth()->user()->name,
        ]);

        return back()->with('success', 'Tracking update added successfully');
    }
}
