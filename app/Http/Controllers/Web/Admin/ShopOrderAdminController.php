<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopOrder;
use Illuminate\Http\Request;

class ShopOrderAdminController extends Controller
{
    /**
     * List all shop orders
     */
    public function index(Request $request)
    {
        $query = ShopOrder::with(['company', 'user', 'items'])
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->has('status')) {
            $query->byStatus($request->input('status'));
        }

        if ($request->has('payment_status')) {
            $query->byPaymentStatus($request->input('payment_status'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('company', function ($sq) use ($search) {
                      $sq->where('company_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $orders = $query->paginate(20);

        return view('admin.shop.orders.index', compact('orders'));
    }

    /**
     * Show order details
     */
    public function show($id)
    {
        $order = ShopOrder::with(['company', 'user', 'items.product'])
            ->findOrFail($id);

        return view('admin.shop.orders.show', compact('order'));
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, $id)
    {
        $order = ShopOrder::findOrFail($id);

        $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
            'admin_notes' => 'nullable|string',
        ]);

        $order->update([
            'status' => $request->input('status'),
            'admin_notes' => $request->input('admin_notes'),
        ]);

        return back()->with('success', 'Order status updated');
    }

    /**
     * Confirm bank transfer payment
     */
    public function confirmBankTransfer($id)
    {
        $order = ShopOrder::findOrFail($id);

        if ($order->payment_method !== 'bank_transfer') {
            return back()->with('error', 'Order is not using bank transfer');
        }

        $order->markAsPaid($order->transaction_reference);

        return back()->with('success', 'Bank transfer payment confirmed');
    }

    /**
     * Mark order as delivered
     */
    public function markDelivered($id)
    {
        $order = ShopOrder::findOrFail($id);

        $order->update([
            'status' => ShopOrder::STATUS_DELIVERED,
        ]);

        return back()->with('success', 'Order marked as delivered');
    }
}
