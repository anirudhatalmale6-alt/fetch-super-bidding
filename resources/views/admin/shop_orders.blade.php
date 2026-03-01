@extends('admin.layouts.app')

@section('title', 'Shop Orders')

@section('content')
<style>
    .order-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        margin-bottom: 20px;
        overflow: hidden;
    }
    .order-card-header {
        padding: 16px 20px;
        border-bottom: 1px solid #F1F5F9;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, #F97316 0%, #EA580C 100%);
        color: #fff;
    }
    .order-card-body { padding: 20px; }
    .customer-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .customer-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #F1F5F9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: #64748B;
    }
    .status-select {
        border: 2px solid #E5E7EB;
        border-radius: 8px;
        padding: 8px 12px;
        font-weight: 600;
    }
    .action-btn {
        padding: 8px 16px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .action-btn-primary { background: #F97316; color: #fff; }
    .action-btn-success { background: #22C55E; color: #fff; }
    .filter-bar {
        background: #fff;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .stat-card-small {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .stat-value-small {
        font-size: 2rem;
        font-weight: 700;
        color: #1E293B;
    }
    .stat-label-small {
        color: #64748B;
        font-size: 0.875rem;
    }
</style>

<!-- Page Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold" style="color: #F97316;">
                    <i class="fas fa-shopping-bag mr-2"></i>Shop Orders
                </h1>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="stat-card-small">
                    <div class="stat-value-small" style="color: #F97316;">{{ $stats['total'] ?? 0 }}</div>
                    <div class="stat-label-small">Total Orders</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="stat-card-small">
                    <div class="stat-value-small" style="color: #F59E0B;">{{ $stats['pending'] ?? 0 }}</div>
                    <div class="stat-label-small">Pending</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="stat-card-small">
                    <div class="stat-value-small" style="color: #3B82F6;">{{ $stats['processing'] ?? 0 }}</div>
                    <div class="stat-label-small">Processing</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="stat-card-small">
                    <div class="stat-value-small" style="color: #8B5CF6;">{{ $stats['shipped'] ?? 0 }}</div>
                    <div class="stat-label-small">Shipped</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="stat-card-small">
                    <div class="stat-value-small" style="color: #22C55E;">{{ $stats['delivered'] ?? 0 }}</div>
                    <div class="stat-label-small">Delivered</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="stat-card-small">
                    <div class="stat-value-small" style="color: #10B981;">₦{{ number_format($stats['revenue'] ?? 0, 0) }}</div>
                    <div class="stat-label-small">Revenue</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
            <div class="row align-items-center">
                <div class="col-md-3 mb-2">
                    <input type="text" class="form-control" placeholder="Search orders...">
                </div>
                <div class="col-md-2 mb-2">
                    <select class="form-control">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <select class="form-control">
                        <option value="">All Payment</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <input type="date" class="form-control">
                </div>
                <div class="col-md-2 mb-2">
                    <button class="btn btn-block" style="background: #F97316; color: #fff;">
                        <i class="fas fa-filter mr-1"></i>Filter
                    </button>
                </div>
            </div>
        </div>

        <!-- Orders Grid -->
        <div class="row">
            @forelse($orders ?? [] as $order)
            <div class="col-xl-6">
                <div class="order-card">
                    <div class="order-card-header">
                        <div>
                            <div class="font-weight-bold">{{ $order->order_number }}</div>
                            <div class="small opacity-75">{{ $order->created_at->format('d M Y, H:i') }}</div>
                        </div>
                        <div>
                            <span class="badge bg-white" style="color: #F97316;">
                                ₦{{ number_format($order->total_amount, 2) }}
                            </span>
                        </div>
                    </div>
                    <div class="order-card-body">
                        <div class="customer-info mb-3">
                            <div class="customer-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <div class="font-weight-bold">{{ $order->customer_name }}</div>
                                <div class="small text-muted">{{ $order->customer_email }}</div>
                                <div class="small text-muted"><i class="fas fa-phone mr-1"></i>{{ $order->customer_phone }}</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="small text-muted mb-1">Delivery Address:</div>
                            <div>{{ $order->delivery_address }}</div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="small text-muted">Items: {{ $order->items_count }}</div>
                                <div class="small text-muted">Payment: <span class="badge badge-{{ $order->payment_status === 'paid' ? 'success' : 'warning' }}">{{ ucfirst($order->payment_status) }}</span></div>
                            </div>
                            <div>
                                <select class="status-select" onchange="updateStatus({{ $order->id }}, this.value)">
                                    <option value="pending" {{ $order->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="processing" {{ $order->status === 'processing' ? 'selected' : '' }}>Processing</option>
                                    <option value="shipped" {{ $order->status === 'shipped' ? 'selected' : '' }}>Shipped</option>
                                    <option value="delivered" {{ $order->status === 'delivered' ? 'selected' : '' }}>Delivered</option>
                                    <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.shop.orders.detail', $order->id) }}" class="action-btn action-btn-primary flex-fill text-center">
                                <i class="fas fa-eye mr-1"></i>View Details
                            </a>
                            @if($order->status !== 'delivered' && $order->status !== 'cancelled')
                            <button class="action-btn action-btn-success" onclick="markAsDelivered({{ $order->id }})">
                                <i class="fas fa-check"></i>
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-shopping-bag fa-4x text-muted mb-3"></i>
                    <h4>No orders found</h4>
                    <p class="text-muted">Orders will appear here when customers make purchases.</p>
                </div>
            </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if(isset($orders) && $orders->hasPages())
        <div class="d-flex justify-content-center mt-4">
            {{ $orders->links() }}
        </div>
        @endif
    </div>
</section>
@endsection

@section('extra-js')
<script>
function updateStatus(orderId, status) {
    fetch(`/admin/shop/orders/${orderId}/status`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ status: status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            toastr.success('Order status updated');
        } else {
            toastr.error(data.message || 'Failed to update status');
        }
    })
    .catch(error => {
        toastr.error('Failed to update status');
    });
}

function markAsDelivered(orderId) {
    if (!confirm('Mark this order as delivered?')) return;

    updateStatus(orderId, 'delivered');
}
</script>
@endsection
