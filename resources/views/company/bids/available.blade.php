@extends('company.layouts.app')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Available Bidding Requests</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('company.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Available Requests</li>
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
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if(session('info'))
                <div class="alert alert-info">{{ session('info') }}</div>
            @endif

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Open Interstate Requests</h3>
                            <div class="card-tools">
                                <a href="{{ route('company.bids.history') }}" class="btn btn-sm btn-info">
                                    <i class="fas fa-history"></i> My Bid History
                                </a>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0">
                            @if($requests->count() > 0)
                            <table class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Request #</th>
                                        <th>Route</th>
                                        <th>Pickup</th>
                                        <th>Dropoff</th>
                                        <th>Packages</th>
                                        <th>Total Weight</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($requests as $request)
                                    <tr>
                                        <td>
                                            <strong>#{{ $request->request_number }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary">{{ $request->pickupLocation->state ?? 'N/A' }}</span>
                                            <i class="fas fa-arrow-right mx-2"></i>
                                            <span class="badge badge-success">{{ $request->dropLocation->state ?? 'N/A' }}</span>
                                        </td>
                                        <td>{{ Str::limit($request->pickup_address, 25) }}</td>
                                        <td>{{ Str::limit($request->drop_address, 25) }}</td>
                                        <td>{{ $request->packages->count() }}</td>
                                        <td>{{ number_format($request->packages->sum('weight'), 2) }} kg</td>
                                        <td>{{ $request->created_at->diffForHumans() }}</td>
                                        <td>
                                            <a href="{{ route('company.bids.create', $request->id) }}" class="btn btn-sm btn-success">
                                                <i class="fas fa-gavel"></i> Place Bid
                                            </a>
                                            <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#requestModal{{ $request->id }}">
                                                <i class="fas fa-eye"></i> Details
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @else
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5>No Available Requests</h5>
                                <p class="text-muted">There are no open interstate requests matching your supported routes at this time.</p>
                                <a href="{{ route('company.routes.index') }}" class="btn btn-primary">
                                    <i class="fas fa-route"></i> Manage Your Routes
                                </a>
                            </div>
                            @endif
                        </div>
                        @if($requests->hasPages())
                        <div class="card-footer">
                            {{ $requests->links() }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

{{-- Request Detail Modals --}}
@foreach($requests as $request)
<div class="modal fade" id="requestModal{{ $request->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request #{{ $request->request_number }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-map-marker-alt text-danger"></i> Pickup</h6>
                        <p>{{ $request->pickup_address }}</p>
                        <p><strong>Contact:</strong> {{ $request->pickup_contact_name }}<br>
                        <strong>Phone:</strong> {{ $request->pickup_contact_phone }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-flag-checkered text-success"></i> Dropoff</h6>
                        <p>{{ $request->drop_address }}</p>
                        <p><strong>Contact:</strong> {{ $request->drop_contact_name }}<br>
                        <strong>Phone:</strong> {{ $request->drop_contact_phone }}</p>
                    </div>
                </div>
                <hr>
                <h6>Package Details</h6>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Weight</th>
                            <th>Dimensions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($request->packages as $index => $package)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $package->package_type }}</td>
                            <td>{{ $package->weight }} kg</td>
                            <td>{{ $package->length }}x{{ $package->width }}x{{ $package->height }} cm</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <a href="{{ route('company.bids.create', $request->id) }}" class="btn btn-success">
                    <i class="fas fa-gavel"></i> Place Bid
                </a>
            </div>
        </div>
    </div>
</div>
@endforeach
@endsection
