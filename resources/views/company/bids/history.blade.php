@extends('company.layouts.app')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">My Bid History</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('company.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Bid History</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">All Bids</h3>
                            <div class="card-tools">
                                <a href="{{ route('company.bids.available') }}" class="btn btn-sm btn-success">
                                    <i class="fas fa-plus"></i> Find New Requests
                                </a>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0">
                            @if($bids->count() > 0)
                            <table class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Request #</th>
                                        <th>Route</th>
                                        <th>Bid Amount</th>
                                        <th>Transport</th>
                                        <th>Insurance</th>
                                        <th>ETA (Days)</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bids as $bid)
                                    <tr>
                                        <td>
                                            <strong>#{{ $bid->request->request_number ?? 'N/A' }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary">{{ $bid->request->pickupLocation->state ?? 'N/A' }}</span>
                                            <i class="fas fa-arrow-right mx-1"></i>
                                            <span class="badge badge-success">{{ $bid->request->dropLocation->state ?? 'N/A' }}</span>
                                        </td>
                                        <td><strong>${{ number_format($bid->bid_amount, 2) }}</strong></td>
                                        <td>${{ number_format($bid->transport_fee, 2) }}</td>
                                        <td>${{ number_format($bid->insurance_fee, 2) }}</td>
                                        <td>{{ $bid->estimated_delivery_days }} days</td>
                                        <td>
                                            @switch($bid->status)
                                                @case('pending')
                                                    <span class="badge badge-warning">Pending</span>
                                                    @break
                                                @case('accepted')
                                                    <span class="badge badge-success">Accepted</span>
                                                    @break
                                                @case('rejected')
                                                    <span class="badge badge-danger">Rejected</span>
                                                    @break
                                                @case('withdrawn')
                                                    <span class="badge badge-secondary">Withdrawn</span>
                                                    @break
                                                @default
                                                    <span class="badge badge-info">{{ ucfirst($bid->status) }}</span>
                                            @endswitch
                                        </td>
                                        <td>{{ $bid->created_at->diffForHumans() }}</td>
                                        <td>
                                            <a href="{{ route('company.bids.show', $bid->id) }}" class="btn btn-sm btn-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            @if($bid->status === 'pending')
                                                <a href="{{ route('company.bids.edit', $bid->id) }}" class="btn btn-sm btn-warning" title="Edit Bid">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="{{ route('company.bids.withdraw', $bid->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to withdraw this bid?');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Withdraw Bid">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @else
                            <div class="text-center py-5">
                                <i class="fas fa-gavel fa-3x text-muted mb-3"></i>
                                <h5>No Bids Yet</h5>
                                <p class="text-muted">You haven't placed any bids yet.</p>
                                <a href="{{ route('company.bids.available') }}" class="btn btn-success">
                                    <i class="fas fa-search"></i> Find Requests to Bid
                                </a>
                            </div>
                            @endif
                        </div>
                        @if($bids->hasPages())
                        <div class="card-footer">
                            {{ $bids->links() }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Stats Summary -->
            <div class="row">
                <div class="col-md-3">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>{{ $bids->where('status', 'pending')->count() }}</h3>
                            <p>Pending Bids</p>
                        </div>
                        <div class="icon"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>{{ $bids->where('status', 'accepted')->count() }}</h3>
                            <p>Accepted Bids</p>
                        </div>
                        <div class="icon"><i class="fas fa-check"></i></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3>{{ $bids->where('status', 'rejected')->count() }}</h3>
                            <p>Rejected Bids</p>
                        </div>
                        <div class="icon"><i class="fas fa-times"></i></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small-box bg-secondary">
                        <div class="inner">
                            <h3>{{ $bids->where('status', 'withdrawn')->count() }}</h3>
                            <p>Withdrawn Bids</p>
                        </div>
                        <div class="icon"><i class="fas fa-undo"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
