@extends('company.layouts.app')

@section('title', 'Bid Details')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Bid #{{ $bid->id }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('company.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('company.bids.history') }}">Bid History</a></li>
                        <li class="breadcrumb-item active">Bid #{{ $bid->id }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- Bid Info -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-gavel mr-2"></i>Bid Details</h3>
                            <div class="card-tools">
                                @php
                                    $statusColors = [
                                        'pending' => 'warning',
                                        'accepted' => 'success',
                                        'rejected' => 'danger',
                                        'withdrawn' => 'secondary',
                                        'expired' => 'dark',
                                    ];
                                @endphp
                                <span class="badge badge-{{ $statusColors[$bid->status] ?? 'info' }} badge-lg" style="font-size: 14px;">
                                    {{ ucfirst($bid->status) }}
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td class="text-muted">Transport Fee</td>
                                            <td class="font-weight-bold">&#8358;{{ number_format($bid->transportation_fee, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Insurance Fee</td>
                                            <td class="font-weight-bold">&#8358;{{ number_format($bid->insurance_fee, 2) }}</td>
                                        </tr>
                                        <tr class="border-top">
                                            <td class="text-muted"><strong>Total Bid</strong></td>
                                            <td><strong class="text-primary" style="font-size: 1.2em;">&#8358;{{ number_format($bid->total_bid_amount, 2) }}</strong></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td class="text-muted">Estimated Delivery</td>
                                            <td class="font-weight-bold">{{ $bid->getFormattedDeliveryTime() }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Submitted</td>
                                            <td>{{ $bid->created_at->format('M d, Y H:i') }}</td>
                                        </tr>
                                        @if($bid->expires_at)
                                        <tr>
                                            <td class="text-muted">Expires</td>
                                            <td>
                                                @if($bid->isExpired())
                                                    <span class="text-danger">Expired {{ $bid->expires_at->diffForHumans() }}</span>
                                                @else
                                                    {{ $bid->expires_at->diffForHumans() }}
                                                @endif
                                            </td>
                                        </tr>
                                        @endif
                                        @if($bid->is_revised)
                                        <tr>
                                            <td class="text-muted">Revised</td>
                                            <td><span class="badge badge-info">Yes</span></td>
                                        </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>

                            @if($bid->bid_notes)
                            <hr>
                            <h6>Notes</h6>
                            <p class="text-muted">{{ $bid->bid_notes }}</p>
                            @endif

                            @if($bid->accepted_at)
                            <div class="alert alert-success mt-3">
                                <i class="fas fa-check-circle mr-1"></i>
                                Accepted on {{ $bid->accepted_at->format('M d, Y H:i') }}
                            </div>
                            @endif

                            @if($bid->rejected_at)
                            <div class="alert alert-danger mt-3">
                                <i class="fas fa-times-circle mr-1"></i>
                                Rejected on {{ $bid->rejected_at->format('M d, Y H:i') }}
                            </div>
                            @endif

                            @if($bid->withdrawn_at)
                            <div class="alert alert-secondary mt-3">
                                <i class="fas fa-undo mr-1"></i>
                                Withdrawn on {{ $bid->withdrawn_at->format('M d, Y H:i') }}
                            </div>
                            @endif
                        </div>
                        <div class="card-footer">
                            @if($bid->status === 'pending')
                                <a href="{{ route('company.bids.edit', $bid->id) }}" class="btn btn-warning">
                                    <i class="fas fa-edit mr-1"></i>Edit Bid
                                </a>
                                <form action="{{ route('company.bids.withdraw', $bid->id) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Are you sure you want to withdraw this bid?');">
                                    @csrf
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-times mr-1"></i>Withdraw Bid
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('company.bids.history') }}" class="btn btn-secondary ml-2">
                                <i class="fas fa-arrow-left mr-1"></i>Back to History
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Request Info -->
                <div class="col-md-4">
                    @if($bid->request)
                    <div class="card">
                        <div class="card-header bg-primary">
                            <h3 class="card-title">Request #{{ $bid->request->request_number ?? $bid->request_id }}</h3>
                        </div>
                        <div class="card-body">
                            <p><strong><i class="fas fa-map-marker-alt text-danger mr-1"></i>Pickup:</strong><br>
                            {{ $bid->request->pick_address ?? 'N/A' }}<br>
                            <small class="text-muted">{{ $bid->request->pickup_state ?? '' }}</small></p>

                            <p><strong><i class="fas fa-flag-checkered text-success mr-1"></i>Dropoff:</strong><br>
                            {{ $bid->request->drop_address ?? 'N/A' }}<br>
                            <small class="text-muted">{{ $bid->request->destination_state ?? '' }}</small></p>

                            @if($bid->request->packages && $bid->request->packages->count() > 0)
                            <hr>
                            <h6>Packages ({{ $bid->request->packages->count() }})</h6>
                            <ul class="list-group list-group-flush">
                                @foreach($bid->request->packages as $package)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    {{ $package->package_type ?? 'Package' }}
                                    <span class="badge badge-primary badge-pill">{{ $package->weight }} kg</span>
                                </li>
                                @endforeach
                            </ul>
                            <p class="mt-2 mb-0"><strong>Total Weight:</strong> {{ $bid->request->packages->sum('weight') }} kg</p>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
