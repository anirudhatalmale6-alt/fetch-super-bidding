@extends('company.layouts.app')

@section('title', 'My Bids')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">My Bids</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('company.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">My Bids</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Quick Stats -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>{{ $bids->total() }}</h3>
                            <p>Total Bids</p>
                        </div>
                        <div class="icon"><i class="fas fa-gavel"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3>{{ $bids->where('status', 'pending')->count() }}</h3>
                            <p>Pending</p>
                        </div>
                        <div class="icon"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>{{ $bids->where('status', 'accepted')->count() }}</h3>
                            <p>Accepted</p>
                        </div>
                        <div class="icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3>{{ $bids->where('status', 'rejected')->count() }}</h3>
                            <p>Rejected</p>
                        </div>
                        <div class="icon"><i class="fas fa-times-circle"></i></div>
                    </div>
                </div>
            </div>

            <!-- Bids Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-gavel mr-2"></i>All Bids</h3>
                    <div class="card-tools">
                        <a href="{{ route('company.bids.available') }}" class="btn btn-sm btn-success">
                            <i class="fas fa-search mr-1"></i>Find Requests
                        </a>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Bid #</th>
                                <th>Request</th>
                                <th>Transport Fee</th>
                                <th>Insurance</th>
                                <th>Total</th>
                                <th>ETA</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bids as $bid)
                            <tr>
                                <td><strong>#{{ $bid->id }}</strong></td>
                                <td>
                                    @if($bid->request)
                                        #{{ $bid->request->request_number ?? $bid->request_id }}
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>&#8358;{{ number_format($bid->transportation_fee, 2) }}</td>
                                <td>&#8358;{{ number_format($bid->insurance_fee, 2) }}</td>
                                <td><strong>&#8358;{{ number_format($bid->total_bid_amount, 2) }}</strong></td>
                                <td>{{ $bid->getFormattedDeliveryTime() }}</td>
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
                                    @if($bid->is_revised)
                                        <span class="badge badge-info">Revised</span>
                                    @endif
                                </td>
                                <td>{{ $bid->created_at ? $bid->created_at->format('M d, Y') : 'N/A' }}</td>
                                <td>
                                    <a href="{{ route('company.bids.show', $bid->id) }}" class="btn btn-sm btn-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if($bid->status === 'pending')
                                        <a href="{{ route('company.bids.edit', $bid->id) }}" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('company.bids.withdraw', $bid->id) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Withdraw this bid?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-danger" title="Withdraw">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="fas fa-gavel fa-3x text-muted mb-3 d-block"></i>
                                    <p class="text-muted">No bids placed yet.</p>
                                    <a href="{{ route('company.bids.available') }}" class="btn btn-success">
                                        <i class="fas fa-search mr-1"></i>Find Requests to Bid
                                    </a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($bids->hasPages())
                <div class="card-footer">
                    {{ $bids->links() }}
                </div>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
