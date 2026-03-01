@extends('company.layouts.app')

@section('title', 'Package Management')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="page-top-bar">
        <h4><i class="fas fa-box mr-2"></i>Package Management</h4>
        <p>Track and manage goods from accepted bids</p>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-num">{{ $stats['awaiting_pickup'] }}</div>
                    <div class="stat-lbl">Awaiting Pickup</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8);">
                <div class="stat-icon"><i class="fas fa-truck"></i></div>
                <div>
                    <div class="stat-num">{{ $stats['in_transit'] }}</div>
                    <div class="stat-lbl">In Transit</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card" style="background: linear-gradient(135deg, #22C55E, #15803D);">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-num">{{ $stats['delivered'] }}</div>
                    <div class="stat-lbl">Delivered</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card" style="background: linear-gradient(135deg, #EF4444, #B91C1C);">
                <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
                <div>
                    <div class="stat-num">{{ $stats['pending_payment'] }}</div>
                    <div class="stat-lbl">Pending Payment</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Packages Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list mr-2"></i>All Packages</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="packages-table">
                    <thead>
                        <tr>
                            <th>Goods ID</th>
                            <th>User</th>
                            <th>Origin</th>
                            <th>Destination</th>
                            <th>Insurance Cost</th>
                            <th>Transport Cost</th>
                            <th>Total Cost</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($packages as $package)
                        <tr>
                            <td>
                                <strong>{{ $package->goods_id }}</strong>
                            </td>
                            <td>
                                @if($package->user)
                                    {{ $package->user->name ?? 'N/A' }}
                                @else
                                    User #{{ $package->user_id }}
                                @endif
                            </td>
                            <td>{{ $package->origin ?? '—' }}</td>
                            <td>{{ $package->destination ?? '—' }}</td>
                            <td>₦{{ number_format($package->insurance_cost ?? 0, 2) }}</td>
                            <td>₦{{ number_format($package->transportation_cost ?? 0, 2) }}</td>
                            <td><strong>₦{{ number_format($package->total_cost ?? 0, 2) }}</strong></td>
                            <td>
                                <span class="badge {{ $package->getStatusBadgeClass() }}">
                                    {{ $package->getStatusLabel() }}
                                </span>
                            </td>
                            <td>{{ $package->created_at->format('d M Y') }}</td>
                            <td>
                                <a href="{{ route('company.packages.show', $package->id) }}" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-4">
                                <div class="empty-state">
                                    <i class="fas fa-box-open fa-3x mb-3 text-muted"></i>
                                    <p class="text-muted">No packages yet. Packages will appear here when driver bids are accepted.</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $packages->links() }}
        </div>
    </div>
</div>
@endsection

@section('extra-js')
<script>
$(document).ready(function() {
    $('#packages-table').DataTable({
        pageLength: 25,
        order: [[8, 'desc']]
    });
});
</script>
@endsection
