@extends('company.layouts.app')

@section('title', 'Package Details')

@section('breadcrumb-items')
<li class="breadcrumb-item"><a href="{{ route('company.packages.index') }}">Packages</a></li>
<li class="breadcrumb-item active">Details</li>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="page-top-bar">
        <h4><i class="fas fa-box mr-2"></i>Package: {{ $package->goods_id }}</h4>
        <p>Manage tracking, costs, and status for this package</p>
    </div>

    <div class="row">
        <!-- Package Info -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle mr-2"></i>Package Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Goods ID:</strong></td>
                            <td>{{ $package->goods_id }}</td>
                        </tr>
                        <tr>
                            <td><strong>User:</strong></td>
                            <td>
                                @if($package->user)
                                    {{ $package->user->name ?? 'N/A' }} ({{ $package->user->email ?? 'No email' }})
                                @else
                                    User #{{ $package->user_id }}
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Origin:</strong></td>
                            <td>{{ $package->origin ?? '—' }} ({{ $package->origin_address ?? 'No address' }})</td>
                        </tr>
                        <tr>
                            <td><strong>Destination:</strong></td>
                            <td>{{ $package->destination ?? '—' }} ({{ $package->destination_address ?? 'No address' }})</td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>
                                <span class="badge {{ $package->getStatusBadgeClass() }}" style="font-size: 0.9rem;">
                                    {{ $package->getStatusLabel() }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Weight:</strong></td>
                            <td>{{ $package->weight_kg ?? '—' }} kg</td>
                        </tr>
                        <tr>
                            <td><strong>Dimensions:</strong></td>
                            <td>{{ $package->dimensions ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Description:</strong></td>
                            <td>{{ $package->description ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Created:</strong></td>
                            <td>{{ $package->created_at->format('d M Y, h:i A') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Update Form -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-edit mr-2"></i>Update Package</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('company.packages.update', $package->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label"><strong>Status</strong></label>
                            <select name="status" class="form-select">
                                <option value="">-- Select Status --</option>
                                <option value="awaiting_pickup" {{ $package->status == 'awaiting_pickup' ? 'selected' : '' }}>Awaiting Pickup</option>
                                <option value="picked_up" {{ $package->status == 'picked_up' ? 'selected' : '' }}>Picked Up</option>
                                <option value="in_transit" {{ $package->status == 'in_transit' ? 'selected' : '' }}>In Transit</option>
                                <option value="out_for_delivery" {{ $package->status == 'out_for_delivery' ? 'selected' : '' }}>Out for Delivery</option>
                                <option value="delivered" {{ $package->status == 'delivered' ? 'selected' : '' }}>Delivered</option>
                                <option value="cancelled" {{ $package->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>Insurance Cost (₦)</strong></label>
                            <input type="number" name="insurance_cost" class="form-control" 
                                   value="{{ $package->insurance_cost ?? 0 }}" min="0" step="0.01"
                                   placeholder="Enter insurance cost">
                            <small class="text-muted">Current: ₦{{ number_format($package->insurance_cost ?? 0, 2) }}</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>Transportation Cost (₦)</strong></label>
                            <input type="number" name="transportation_cost" class="form-control" 
                                   value="{{ $package->transportation_cost ?? 0 }}" min="0" step="0.01"
                                   placeholder="Enter transportation cost">
                            <small class="text-muted">Current: ₦{{ number_format($package->transportation_cost ?? 0, 2) }}</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>Add Note</strong></label>
                            <textarea name="note" class="form-control" rows="3" 
                                      placeholder="Enter tracking note..."></textarea>
                        </div>

                        <div class="mb-3">
                            <strong>Total Cost:</strong>
                            <span class="h5 text-success">₦{{ number_format($package->total_cost ?? 0, 2) }}</span>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Save Updates
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Tracking History -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-history mr-2"></i>Tracking History</h5>
        </div>
        <div class="card-body">
            @if($trackingLogs->isNotEmpty())
                <div class="timeline">
                    @foreach($trackingLogs as $log)
                    <div class="timeline-item">
                        <div class="timeline-badge bg-info">
                            <i class="fas fa-tag"></i>
                        </div>
                        <div class="timeline-panel">
                            <div class="timeline-heading">
                                <h6 class="timeline-title">{{ $log->note }}</h6>
                                <small class="text-muted">{{ $log->created_at->format('d M Y, h:i A') }}</small>
                            </div>
                            @if($log->cost_added)
                            <div class="timeline-body">
                                <span class="badge bg-warning">Cost: ₦{{ number_format($log->cost_added, 2) }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <p class="text-muted text-center py-3">No tracking history yet.</p>
            @endif
        </div>
    </div>

    <!-- Payments -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-credit-card mr-2"></i>Payment Records</h5>
        </div>
        <div class="card-body">
            @if($payments->isNotEmpty())
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Paid At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                        <tr>
                            <td>
                                @if($payment->cost_type == 'insurance')
                                    <span class="badge bg-info">Insurance</span>
                                @else
                                    <span class="badge bg-primary">Transportation</span>
                                @endif
                            </td>
                            <td>₦{{ number_format($payment->amount, 2) }}</td>
                            <td>
                                @if($payment->status == 'paid')
                                    <span class="badge bg-success">Paid</span>
                                @else
                                    <span class="badge bg-warning">Pending</span>
                                @endif
                            </td>
                            <td>{{ $payment->paid_at ? $payment->paid_at->format('d M Y, h:i A') : '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-muted text-center py-3">No payment records yet.</p>
            @endif
        </div>
    </div>
</div>
@endsection
