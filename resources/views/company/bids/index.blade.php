@extends('company.layouts.app')

@section('title', 'My Bids')

@section('content')
<div class="container-fluid">
    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #F97316, #EA580C);">
                <div class="stat-icon"><i class="fas fa-gavel"></i></div>
                <div>
                    <div class="stat-num">{{ $bids ? $bids->total() : 0 }}</div>
                    <div class="stat-lbl">Total Bids</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #3B82F6, #2563EB);">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-num">{{ $bids ? $bids->where('status', 'pending')->count() : 0 }}</div>
                    <div class="stat-lbl">Pending</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #10B981, #059669);">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-num">{{ $bids ? $bids->where('status', 'accepted')->count() : 0 }}</div>
                    <div class="stat-lbl">Accepted</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                <div>
                    <div class="stat-num">{{ $bids ? $bids->where('status', 'rejected')->count() : 0 }}</div>
                    <div class="stat-lbl">Rejected</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bids Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-gavel mr-2"></i>My Bids</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Bid ID</th>
                            <th>Request</th>
                            <th>Transport Fee</th>
                            <th>Insurance</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($bids && $bids->count() > 0)
                            @foreach($bids as $bid)
                            <tr>
                                <td><strong>#{{ $bid->id }}</strong></td>
                                <td>
                                    @if($bid->request)
                                        Request #{{ $bid->request_id }}
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>&#8358;{{ number_format($bid->transportation_fee ?? 0, 2) }}</td>
                                <td>&#8358;{{ number_format($bid->insurance_fee ?? 0, 2) }}</td>
                                <td><strong>&#8358;{{ number_format($bid->total_bid_amount ?? 0, 2) }}</strong></td>
                                <td>
                                    @php
                                        $statusColors = [
                                            'pending' => 'warning',
                                            'accepted' => 'success',
                                            'rejected' => 'danger',
                                            'withdrawn' => 'secondary',
                                            'expired' => 'dark',
                                        ];
                                        $color = $statusColors[$bid->status] ?? 'info';
                                    @endphp
                                    <span class="badge badge-{{ $color }}">{{ ucfirst($bid->status) }}</span>
                                </td>
                                <td>{{ $bid->created_at ? $bid->created_at->format('M d, Y') : 'N/A' }}</td>
                            </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-gavel fa-3x text-muted mb-3 d-block"></i>
                                    <p class="text-muted">No bids placed yet</p>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        @if($bids && $bids->hasPages())
        <div class="card-footer">
            {{ $bids->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
