@extends('user.layouts.app')

@section('title', 'Dashboard')

@section('content')
<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-home mr-3"></i>Welcome back, {{ auth()->user()->name }}!</h1>
        <p class="mb-0 mt-2 opacity-75">Here's what's happening with your deliveries today.</p>
    </div>
</div>

<div class="container">
    <!-- Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-box"></i>
                </div>
                <div>
                    <div class="stat-value">{{ $stats['total_orders'] ?? 0 }}</div>
                    <div class="stat-label">Total Orders</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div>
                    <div class="stat-value">{{ $stats['active_shipments'] ?? 0 }}</div>
                    <div class="stat-label">Active Shipments</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <div class="stat-value">{{ $stats['delivered'] ?? 0 }}</div>
                    <div class="stat-label">Delivered</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-wallet"></i>
                </div>
                <div>
                    <div class="stat-value">₦{{ number_format($stats['total_spent'] ?? 0, 0) }}</div>
                    <div class="stat-label">Total Spent</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="fetch-card p-4">
                <h5 class="font-weight-bold mb-3">Quick Actions</h5>
                <div class="row">
                    <div class="col-md-3 col-6 mb-3">
                        <a href="{{ route('user.delivery.book') }}" class="text-decoration-none">
                            <div class="text-center p-3 rounded-lg" style="background: #FFF7ED;">
                                <i class="fas fa-plus-circle fa-2x text-warning mb-2"></i>
                                <div class="font-weight-600 text-dark">Book Delivery</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <a href="{{ route('user.shipments.track') }}" class="text-decoration-none">
                            <div class="text-center p-3 rounded-lg" style="background: #DBEAFE;">
                                <i class="fas fa-search-location fa-2x text-primary mb-2"></i>
                                <div class="font-weight-600 text-dark">Track Package</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <a href="{{ route('user.orders') }}" class="text-decoration-none">
                            <div class="text-center p-3 rounded-lg" style="background: #DCFCE7;">
                                <i class="fas fa-history fa-2x text-success mb-2"></i>
                                <div class="font-weight-600 text-dark">Order History</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <a href="{{ route('user.shop.index') }}" class="text-decoration-none">
                            <div class="text-center p-3 rounded-lg" style="background: #F3E8FF;">
                                <i class="fas fa-store fa-2x mb-2" style="color: #9333EA;"></i>
                                <div class="font-weight-600 text-dark">Shop</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Orders -->
        <div class="col-xl-8 mb-4">
            <div class="fetch-card">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="font-weight-bold mb-0">Recent Orders</h5>
                    <a href="{{ route('user.orders') }}" class="text-warning font-weight-600">View All</a>
                </div>
                <div class="p-0">
                    @if(isset($recentOrders) && $recentOrders->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="border-0">Order #</th>
                                        <th class="border-0">Date</th>
                                        <th class="border-0">Status</th>
                                        <th class="border-0 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentOrders as $order)
                                    <tr>
                                        <td>
                                            <a href="{{ route('user.orders.detail', $order->id) }}" class="font-weight-600">
                                                {{ $order->order_number }}
                                            </a>
                                        </td>
                                        <td class="text-muted">{{ $order->created_at->format('d M Y') }}</td>
                                        <td>
                                            <span class="status-badge {{ $order->status }}">
                                                {{ ucfirst($order->status) }}
                                            </span>
                                        </td>
                                        <td class="text-right font-weight-600">₦{{ number_format($order->total_amount, 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <h5>No orders yet</h5>
                            <p class="text-muted">Start by booking your first delivery or shopping in our store.</p>
                            <a href="{{ route('user.delivery.book') }}" class="btn btn-brand">Book a Delivery</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Active Shipments -->
        <div class="col-xl-4 mb-4">
            <div class="fetch-card">
                <div class="p-4 border-bottom">
                    <h5 class="font-weight-bold mb-0">Active Shipments</h5>
                </div>
                <div class="p-3">
                    @if(isset($activeShipments) && $activeShipments->count() > 0)
                        @foreach($activeShipments as $shipment)
                        <div class="d-flex align-items-center p-3 mb-3 rounded-lg" style="background: #F8FAFC;">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle d-flex align-items-center justify-content-center"
                                     style="width: 48px; height: 48px; background: #FFF7ED;">
                                    <i class="fas fa-truck text-warning"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ml-3">
                                <div class="font-weight-600">{{ $shipment->tracking_number }}</div>
                                <div class="small text-muted">{{ $shipment->origin }} → {{ $shipment->destination }}</div>
                                <div class="mt-1">
                                    <span class="status-badge {{ $shipment->status }}">
                                        {{ ucfirst(str_replace('_', ' ', $shipment->status)) }}
                                    </span>
                                </div>
                            </div>
                            <a href="{{ route('user.shipments.track', $shipment->tracking_number) }}" class="text-warning">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                        @endforeach
                    @else
                        <div class="empty-state py-4">
                            <i class="fas fa-shipping-fast" style="font-size: 2.5rem;"></i>
                            <p class="text-muted mb-0">No active shipments</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Delivery Tips -->
            <div class="fetch-card mt-4">
                <div class="p-4">
                    <h6 class="font-weight-bold mb-3"><i class="fas fa-lightbulb text-warning mr-2"></i>Did you know?</h6>
                    <p class="text-muted mb-0 small">You can track your shipments in real-time and get notifications at every step of the delivery process.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
