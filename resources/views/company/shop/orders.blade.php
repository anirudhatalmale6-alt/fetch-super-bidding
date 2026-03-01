@extends('company.layouts.app')

@section('title', 'My Orders')

@section('extra-css')
<style>
    .order-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        transition: box-shadow 0.2s;
    }
    .order-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .order-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #f1f5f9;
    }
    .order-number {
        font-weight: 600;
        color: #1e293b;
    }
    .order-status {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .order-status.pending {
        background: #fef3c7;
        color: #92400e;
    }
    .order-status.processing {
        background: #dbeafe;
        color: #1e40af;
    }
    .order-status.shipped {
        background: #e0e7ff;
        color: #3730a3;
    }
    .order-status.delivered {
        background: #dcfce7;
        color: #166534;
    }
    .order-status.cancelled {
        background: #fee2e2;
        color: #991b1b;
    }
    .order-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }
    .order-info-item label {
        display: block;
        font-size: 0.85rem;
        color: #64748b;
        margin-bottom: 4px;
    }
    .order-info-item span {
        font-weight: 500;
        color: #1e293b;
    }
    .order-total {
        font-size: 1.1rem;
        font-weight: 700;
        color: #F97316;
    }
    .order-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }
    .order-actions .btn {
        padding: 8px 16px;
        font-size: 0.9rem;
    }
    .payment-status {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 500;
        margin-left: 10px;
    }
    .payment-status.paid {
        background: #dcfce7;
        color: #166534;
    }
    .payment-status.pending {
        background: #fef3c7;
        color: #92400e;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="page-top-bar mb-4">
        <h4><i class="fas fa-box mr-2"></i>My Orders</h4>
        <p>View and track your orders</p>
    </div>

    <!-- Orders List -->
    @if($orders->count() > 0)
        @foreach($orders as $order)
        <div class="order-card">
            <div class="order-header">
                <div>
                    <span class="order-number">{{ $order->order_number }}</span>
                    <span class="payment-status {{ $order->payment_status }}">
                        {{ ucfirst($order->payment_status) }}
                    </span>
                </div>
                <span class="order-status {{ $order->status }}">
                    {{ ucfirst($order->status) }}
                </span>
            </div>
            
            <div class="order-info">
                <div class="order-info-item">
                    <label>Order Date</label>
                    <span>{{ $order->created_at->format('d M Y, H:i') }}</span>
                </div>
                <div class="order-info-item">
                    <label>Items</label>
                    <span>{{ $order->items->sum('quantity') }} item(s)</span>
                </div>
                <div class="order-info-item">
                    <label>Delivery Type</label>
                    <span>{{ ucfirst($order->delivery_type) }}</span>
                </div>
                <div class="order-info-item">
                    <label>Total</label>
                    <span class="order-total">₦{{ number_format($order->total_amount, 2) }}</span>
                </div>
            </div>
            
            <div class="order-actions">
                <a href="{{ route('company.shop.orders.detail', $order->id) }}" class="btn btn-outline-primary">
                    <i class="fas fa-eye mr-1"></i>View Details
                </a>
                @if($order->payment_status === 'pending' && $order->payment_method === 'bank_transfer')
                <button class="btn btn-warning" onclick="uploadProof({{ $order->id }})">
                    <i class="fas fa-upload mr-1"></i>Upload Payment Proof
                </button>
                @endif
            </div>
        </div>
        @endforeach
        
        <!-- Pagination -->
        <div class="d-flex justify-content-center mt-4">
            {{ $orders->links() }}
        </div>
    @else
        <!-- Empty State -->
        <div class="text-center py-5">
            <div class="empty-state">
                <i class="fas fa-box-open fa-3x mb-3 text-muted"></i>
                <h4>No Orders Yet</h4>
                <p class="text-muted mb-4">You haven't placed any orders yet.</p>
                <a href="{{ route('company.shop.index') }}" class="btn btn-primary" style="background: #F97316; border-color: #F97316;">
                    <i class="fas fa-store mr-2"></i>Browse Products
                </a>
            </div>
        </div>
    @endif
</div>
@endsection

@section('extra-js')
<script>
function uploadProof(orderId) {
    // Implement bank transfer proof upload
    toastr.info('Payment proof upload feature coming soon');
}
</script>
@endsection
