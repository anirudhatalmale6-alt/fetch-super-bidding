@extends('company.layouts.app')

@section('title', 'Delivery Legs')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Delivery Legs</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('company.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Deliveries</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif

            {{-- Stats Cards --}}
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3>{{ $pendingCount }}</h3>
                            <p>Pending Legs</p>
                        </div>
                        <div class="icon"><i class="fas fa-clock"></i></div>
                        <a href="{{ route('company.deliveries.index', ['status' => 'pending']) }}" class="small-box-footer">
                            View Pending <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>{{ $activeCount }}</h3>
                            <p>Active Legs</p>
                        </div>
                        <div class="icon"><i class="fas fa-shipping-fast"></i></div>
                        <a href="{{ route('company.deliveries.index', ['status' => 'active']) }}" class="small-box-footer">
                            View Active <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>{{ $completedCount }}</h3>
                            <p>Completed</p>
                        </div>
                        <div class="icon"><i class="fas fa-check-circle"></i></div>
                        <a href="{{ route('company.deliveries.index', ['status' => 'completed']) }}" class="small-box-footer">
                            View Completed <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Status Tabs --}}
            <div class="card">
                <div class="card-header p-2">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a class="nav-link {{ $status == 'all' ? 'active' : '' }}"
                               href="{{ route('company.deliveries.index', ['status' => 'all']) }}">
                                All Legs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $status == 'pending' ? 'active' : '' }}"
                               href="{{ route('company.deliveries.index', ['status' => 'pending']) }}">
                                Pending <span class="badge badge-warning">{{ $pendingCount }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $status == 'active' ? 'active' : '' }}"
                               href="{{ route('company.deliveries.index', ['status' => 'active']) }}">
                                Active <span class="badge badge-info">{{ $activeCount }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $status == 'completed' ? 'active' : '' }}"
                               href="{{ route('company.deliveries.index', ['status' => 'completed']) }}">
                                Completed
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="card-body table-responsive p-0">
                    @if($legs->count() > 0)
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Leg #</th>
                                <th>Type</th>
                                <th>Request</th>
                                <th>Route</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($legs as $leg)
                            <tr>
                                <td><strong>#{{ $leg->id }}</strong></td>
                                <td>
                                    @php
                                        $typeLabels = [
                                            'local_pickup' => ['Local Pickup', 'badge-primary'],
                                            'hub_dropoff' => ['Hub Dropoff', 'badge-info'],
                                            'interstate_transport' => ['Interstate', 'badge-dark'],
                                            'hub_pickup' => ['Hub Pickup', 'badge-info'],
                                            'local_delivery' => ['Local Delivery', 'badge-success'],
                                        ];
                                        $typeInfo = $typeLabels[$leg->leg_type] ?? [$leg->leg_type, 'badge-secondary'];
                                    @endphp
                                    <span class="badge {{ $typeInfo[1] }}">{{ $typeInfo[0] }}</span>
                                </td>
                                <td>
                                    @if($leg->request)
                                        {{ $leg->request->request_number ?? 'REQ-' . $leg->request_id }}
                                    @else
                                        REQ-{{ $leg->request_id }}
                                    @endif
                                </td>
                                <td>
                                    @if($leg->pickup_location)
                                        {{ $leg->pickup_location['city'] ?? '' }}
                                    @endif
                                    <i class="fas fa-arrow-right text-muted mx-1"></i>
                                    @if($leg->drop_location)
                                        {{ $leg->drop_location['city'] ?? '' }}
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $statusColors = [
                                            'pending' => 'badge-warning',
                                            'accepted' => 'badge-primary',
                                            'en_route_pickup' => 'badge-info',
                                            'picked_up' => 'badge-info',
                                            'arrived_at_hub' => 'badge-secondary',
                                            'collected_from_hub' => 'badge-dark',
                                            'in_transit' => 'badge-dark',
                                            'en_route_delivery' => 'badge-primary',
                                            'completed' => 'badge-success',
                                            'cancelled' => 'badge-danger',
                                        ];
                                    @endphp
                                    <span class="badge {{ $statusColors[$leg->status] ?? 'badge-secondary' }}">
                                        {{ ucwords(str_replace('_', ' ', $leg->status)) }}
                                    </span>
                                </td>
                                <td>{{ $leg->created_at->format('M d, Y') }}</td>
                                <td>
                                    <a href="{{ route('company.deliveries.show', $leg->id) }}" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    @if($leg->status == 'pending')
                                        <form action="{{ route('company.deliveries.accept', $leg->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Accept this delivery leg?')">
                                                <i class="fas fa-check"></i> Accept
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
                        <i class="fas fa-truck fa-3x text-muted mb-3 d-block"></i>
                        <h5>No Delivery Legs Found</h5>
                        <p class="text-muted">
                            @if($status == 'pending')
                                No pending delivery legs at the moment.
                            @elseif($status == 'active')
                                No active delivery legs right now.
                            @elseif($status == 'completed')
                                No completed deliveries yet.
                            @else
                                When you get assigned delivery legs, they will appear here.
                            @endif
                        </p>
                    </div>
                    @endif
                </div>

                @if($legs->hasPages())
                <div class="card-footer">
                    {{ $legs->appends(['status' => $status])->links() }}
                </div>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
