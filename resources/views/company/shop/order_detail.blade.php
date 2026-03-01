@extends('company.layouts.app')

@section('title', 'Order Details')

@section('extra-css')
<style>
    .order-detail-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .status-badge {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 500;
    }
    .status-badge.pending { background: #fef3c7; color: #92400e; }
    .status-badge.processing { background: #dbeafe; color: #1e40af; }
    .status-badge.shipped { background: #e0e7ff; color: #3730a3; }
    .status-badge.delivered { background: #dcfce7; color: #166534; }
    .status-badge.cancelled { background: #fee2e2; color: #991b1b; }
    
    .timeline {
        position: relative;
        padding-left: 30px;
    }
    .timeline::before {
        content: '';
        position: absolute;
        left: 10px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e5e7eb;
    }
    .timeline-item {
        position: relative;
        padding-bottom: 20px;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -24px;
        top: 4px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #F97316;
        border: 2px solid white;
        box-shadow: 0 0 0 2px #F97316;
    }
    .timeline-item.completed::before {
        background: #22c55e;
        box-shadow: 0 0 0 2px #22c55e;
    }
    .item-card {
        display: flex;
        gap: 15px;
        padding: 15px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        margin-bottom: 10px;
    }
    .item-image {
        width: 80px;
        height: 80px;
        background: #f8fafc;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .item-image img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .summary-row.total {
        border-bottom: none;
        font-size: 1.2rem;
        font-weight: 700;
        color: #F97316;
        padding-top: 15px;
        border-top: 2px solid #e5e7eb;
        margin-top: 10px;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Back Button -->
    <a href="{{ route('company.shop.orders') }}" class="btn btn-outline-secondary mb-4">
        <i class="fas fa-arrow-left mr-1"></i>Back to Orders
    </a>

    <!-- Order Header -->
    <div class="order-detail-card">
        <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div>
                <h4 class="mb-2">Order {{ $order->order_number }}</h4>
                <p class="text-muted mb-0">Placed on {{ $order->created_at->format('d M Y, H:i') }}</p>
            </div>
            <div class="mt-3 mt-sm-0">
                <span class="status-badge {{ $order->status }}">
                    {{ ucfirst($order->status) }}
                </span>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Order Items -->
        <div class="col-lg-8">
            <div class="order-detail-card">
                <h5 class="mb-4" style="font-weight: 600;">Order Items</h5>
                
                @foreach($order->items as $item)
                <div class="item-card">
                    <div class="item-image">
                        @if($item->product_image)
                            <img src="{{ $item->product_image }}" alt="{{ $item->product_name }}">
                        @else
                            <i class="fas fa-image fa-2x text-muted"></i>
                        @endif
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">{{ $item->product_name }}</h6>
                        @if($item->product_sku)
                            <p class="text-muted small mb-2">SKU: {{ $item->product_sku }}</p>
                        @endif
                        <p class="mb-0">Qty: {{ $item->quantity }} × ₦{{ number_format($item->unit_price, 2) }}</p>
                    </div>
                    <div class="text-right">
                        <span style="font-weight: 600; color: #F97316;">
                            ₦{{ number_format($item->total_price, 2) }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Order Timeline -->
            <div class="order-detail-card">
                <h5 class="mb-4" style="font-weight: 600;">Order Timeline</h5>
                <div class="timeline">
                    <div class="timeline-item completed">
                        <strong>Order Placed</strong>
                        <p class="text-muted small mb-0">{{ $order->created_at->format('d M Y, H:i') }}</p>
                    </div>
                    <div class="timeline-item {{ in_array($order->status, ['processing', 'shipped', 'delivered']) ? 'completed' : '' }}">
                        <strong>Payment {{ $order->payment_status === 'paid' ? 'Confirmed' : 'Pending' }}</strong>
                        <p class="text-muted small mb-0">
                            @if($order->payment_status === 'paid')
                                Paid on {{ $order->paid_at ? $order->paid_at->format('d M Y, H:i') : 'N/A' }}
                            @else
                                Awaiting payment confirmation
                            @endif
                        </p>
                    </div>
                    <div class="timeline-item {{ in_array($order->status, ['shipped', 'delivered']) ? 'completed' : '' }}">
                        <strong>Processing</strong>
                        <p class="text-muted small mb-0">Order is being prepared</p>
                    </div>
                    <div class="timeline-item {{ $order->status === 'delivered' ? 'completed' : '' }}">
                        <strong>Shipped</strong>
                        <p class="text-muted small mb-0">
                            @if($order->delivery_type === 'metro')
                                Local delivery
                            @else
                                Interstate delivery
                            @endif
                        </p>
                    </div>
                    <div class="timeline-item {{ $order->status === 'delivered' ? 'completed' : '' }}">
                        <strong>Delivered</strong>
                        <p class="text-muted small mb-0">
                            @if($order->status === 'delivered')
                                Order delivered successfully
                            @else
                                Awaiting delivery
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="col-lg-4">
            <div class="order-detail-card">
                <h5 class="mb-4" style="font-weight: 600;">Order Summary</h5>
                
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>₦{{ number_format($order->subtotal, 2) }}</span>
                </div>
                <div class="summary-row">
                    <span>Tax (7.5%)</span>
                    <span>₦{{ number_format($order->tax_amount, 2) }}</span>
                </div>
                <div class="summary-row">
                    <span>Delivery Fee</span>
                    <span>₦{{ number_format($order->delivery_fee, 2) }}</span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span>₦{{ number_format($order->total_amount, 2) }}</span>
                </div>
            </div>

            <div class="order-detail-card">
                <h5 class="mb-4" style="font-weight: 600;">Delivery Information</h5>
                
                <p class="mb-2"><strong>{{ $order->delivery_contact_name }}</strong></p>
                <p class="mb-2"><i class="fas fa-phone mr-2 text-muted"></i>{{ $order->delivery_contact_phone }}</p>
                <p class="mb-2"><i class="fas fa-map-marker-alt mr-2 text-muted"></i>{{ $order->delivery_address }}</p>
                @if($order->delivery_notes)
                    <p class="mb-0 text-muted"><i class="fas fa-sticky-note mr-2"></i>{{ $order->delivery_notes }}</p>
                @endif
            </div>

            <div class="order-detail-card">
                <h5 class="mb-4" style="font-weight: 600;">Payment Information</h5>
                
                <div class="summary-row">
                    <span>Method</span>
                    <span>{{ ucfirst(str_replace('_', ' ', $order->payment_method)) }}</span>
                </div>
                <div class="summary-row">
                    <span>Status</span>
                    <span class="badge {{ $order->payment_status === 'paid' ? 'badge-success' : 'badge-warning' }}">
                        {{ ucfirst($order->payment_status) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
