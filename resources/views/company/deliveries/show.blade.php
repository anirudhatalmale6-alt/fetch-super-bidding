@extends('company.layouts.app')

@section('title', 'Delivery Leg Details')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Delivery Leg #{{ $leg->id }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('company.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('company.deliveries.index') }}">Deliveries</a></li>
                        <li class="breadcrumb-item active">Leg #{{ $leg->id }}</li>
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

            <div class="row">
                {{-- Main Details --}}
                <div class="col-md-8">
                    {{-- Leg Info Card --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-truck mr-2"></i>Leg Information</h3>
                            <div class="card-tools">
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
                                <span class="badge {{ $statusColors[$leg->status] ?? 'badge-secondary' }} p-2" style="font-size: 0.9rem;">
                                    {{ ucwords(str_replace('_', ' ', $leg->status)) }}
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td class="text-muted">Leg Type:</td>
                                            <td><strong>{{ ucwords(str_replace('_', ' ', $leg->leg_type)) }}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Leg Number:</td>
                                            <td>{{ $leg->leg_number ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Request:</td>
                                            <td>
                                                @if($leg->request)
                                                    <strong>{{ $leg->request->request_number ?? 'REQ-' . $leg->request_id }}</strong>
                                                @else
                                                    REQ-{{ $leg->request_id }}
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Provider:</td>
                                            <td>{{ $leg->provider_name ?? $company->company_name ?? 'Your Company' }}</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td class="text-muted">Created:</td>
                                            <td>{{ $leg->created_at->format('M d, Y h:i A') }}</td>
                                        </tr>
                                        @if($leg->accepted_at)
                                        <tr>
                                            <td class="text-muted">Accepted:</td>
                                            <td>{{ $leg->accepted_at->format('M d, Y h:i A') }}</td>
                                        </tr>
                                        @endif
                                        @if($leg->picked_up_at)
                                        <tr>
                                            <td class="text-muted">Picked Up:</td>
                                            <td>{{ $leg->picked_up_at->format('M d, Y h:i A') }}</td>
                                        </tr>
                                        @endif
                                        @if($leg->completed_at)
                                        <tr>
                                            <td class="text-muted">Completed:</td>
                                            <td>{{ $leg->completed_at->format('M d, Y h:i A') }}</td>
                                        </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>

                            {{-- Pickup & Drop Locations --}}
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-map-marker-alt text-success mr-1"></i>Pickup Location</h6>
                                    @if($leg->pickup_location)
                                        <p class="mb-1">
                                            <strong>{{ $leg->pickup_location['address'] ?? '' }}</strong><br>
                                            {{ $leg->pickup_location['city'] ?? '' }}, {{ $leg->pickup_location['state'] ?? '' }}
                                        </p>
                                        @if(isset($leg->pickup_location['contact_name']))
                                            <small class="text-muted">
                                                Contact: {{ $leg->pickup_location['contact_name'] }}
                                                @if(isset($leg->pickup_location['contact_phone']))
                                                    - {{ $leg->pickup_location['contact_phone'] }}
                                                @endif
                                            </small>
                                        @endif
                                    @else
                                        <p class="text-muted">Not specified</p>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-map-marker-alt text-danger mr-1"></i>Drop Location</h6>
                                    @if($leg->drop_location)
                                        <p class="mb-1">
                                            <strong>{{ $leg->drop_location['address'] ?? '' }}</strong><br>
                                            {{ $leg->drop_location['city'] ?? '' }}, {{ $leg->drop_location['state'] ?? '' }}
                                        </p>
                                        @if(isset($leg->drop_location['contact_name']))
                                            <small class="text-muted">
                                                Contact: {{ $leg->drop_location['contact_name'] }}
                                                @if(isset($leg->drop_location['contact_phone']))
                                                    - {{ $leg->drop_location['contact_phone'] }}
                                                @endif
                                            </small>
                                        @endif
                                    @else
                                        <p class="text-muted">Not specified</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Full Pipeline --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-project-diagram mr-2"></i>Delivery Pipeline</h3>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                @foreach($allLegs as $pipelineLeg)
                                @php
                                    $isCurrent = $pipelineLeg->id == $leg->id;
                                    $isCompleted = $pipelineLeg->status == 'completed';
                                    $isPending = $pipelineLeg->status == 'pending';
                                    $dotColor = $isCompleted ? 'bg-success' : ($isPending ? 'bg-secondary' : 'bg-primary');
                                    $typeLabels = [
                                        'local_pickup' => 'Local Pickup',
                                        'hub_dropoff' => 'Hub Drop-off',
                                        'interstate_transport' => 'Interstate Transport',
                                        'hub_pickup' => 'Hub Pickup',
                                        'local_delivery' => 'Local Delivery',
                                    ];
                                @endphp
                                <div>
                                    <i class="fas fa-{{ $isCompleted ? 'check-circle' : ($isPending ? 'circle' : 'dot-circle') }} {{ $dotColor }}"
                                       style="font-size: 1.2rem;"></i>
                                    <div class="timeline-item {{ $isCurrent ? 'border-primary' : '' }}" style="{{ $isCurrent ? 'border-left: 3px solid #007bff;' : '' }}">
                                        <h3 class="timeline-header">
                                            <strong>Leg {{ $pipelineLeg->leg_number ?? $loop->iteration }}:</strong>
                                            {{ $typeLabels[$pipelineLeg->leg_type] ?? ucwords(str_replace('_', ' ', $pipelineLeg->leg_type)) }}
                                            @if($isCurrent)
                                                <span class="badge badge-primary ml-2">Current</span>
                                            @endif
                                            <span class="badge {{ $statusColors[$pipelineLeg->status] ?? 'badge-secondary' }} float-right">
                                                {{ ucwords(str_replace('_', ' ', $pipelineLeg->status)) }}
                                            </span>
                                        </h3>
                                        <div class="timeline-body">
                                            <small>
                                                {{ $pipelineLeg->provider_name ?? 'Unassigned' }}
                                                @if($pipelineLeg->completed_at)
                                                    | Completed {{ $pipelineLeg->completed_at->diffForHumans() }}
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sidebar --}}
                <div class="col-md-4">
                    {{-- Actions Card --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-bolt mr-2"></i>Actions</h3>
                        </div>
                        <div class="card-body">
                            @if($leg->status == 'pending')
                                <form action="{{ route('company.deliveries.accept', $leg->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-block mb-2" onclick="return confirm('Accept this leg?')">
                                        <i class="fas fa-check mr-1"></i>Accept Leg
                                    </button>
                                </form>
                            @elseif($leg->status == 'accepted')
                                <form action="{{ route('company.deliveries.update-status', $leg->id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="status" value="picked_up">
                                    <button type="submit" class="btn btn-info btn-block mb-2">
                                        <i class="fas fa-box mr-1"></i>Mark Picked Up
                                    </button>
                                </form>
                            @elseif($leg->status == 'picked_up')
                                <form action="{{ route('company.deliveries.update-status', $leg->id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="status" value="in_transit">
                                    <button type="submit" class="btn btn-dark btn-block mb-2">
                                        <i class="fas fa-shipping-fast mr-1"></i>Mark In Transit
                                    </button>
                                </form>
                            @elseif($leg->status == 'in_transit')
                                <form action="{{ route('company.deliveries.update-status', $leg->id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn btn-success btn-block mb-2">
                                        <i class="fas fa-check-double mr-1"></i>Mark Completed
                                    </button>
                                </form>
                            @elseif($leg->status == 'completed')
                                <div class="text-center text-success py-3">
                                    <i class="fas fa-check-circle fa-3x mb-2"></i>
                                    <h6>Delivery Completed</h6>
                                </div>
                            @endif

                            <a href="{{ route('company.deliveries.index') }}" class="btn btn-outline-secondary btn-block mt-2">
                                <i class="fas fa-arrow-left mr-1"></i>Back to List
                            </a>
                        </div>
                    </div>

                    {{-- Weight & Financial Info --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-weight-hanging mr-2"></i>Weight & Financials</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                @if($leg->total_actual_weight)
                                <tr>
                                    <td class="text-muted">Actual Weight:</td>
                                    <td>{{ number_format($leg->total_actual_weight, 2) }} kg</td>
                                </tr>
                                @endif
                                @if($leg->total_volumetric_weight)
                                <tr>
                                    <td class="text-muted">Volumetric:</td>
                                    <td>{{ number_format($leg->total_volumetric_weight, 2) }} kg</td>
                                </tr>
                                @endif
                                @if($leg->total_chargeable_weight)
                                <tr>
                                    <td class="text-muted">Chargeable:</td>
                                    <td><strong>{{ number_format($leg->total_chargeable_weight, 2) }} kg</strong></td>
                                </tr>
                                @endif
                            </table>
                            <hr>
                            <table class="table table-sm table-borderless">
                                @if($leg->base_fare)
                                <tr>
                                    <td class="text-muted">Base Fare:</td>
                                    <td>&#8358;{{ number_format($leg->base_fare, 2) }}</td>
                                </tr>
                                @endif
                                @if($leg->surcharge_amount)
                                <tr>
                                    <td class="text-muted">Surcharge:</td>
                                    <td>&#8358;{{ number_format($leg->surcharge_amount, 2) }}</td>
                                </tr>
                                @endif
                                @if($leg->final_fare)
                                <tr>
                                    <td class="text-muted">Final Fare:</td>
                                    <td><strong>&#8358;{{ number_format($leg->final_fare, 2) }}</strong></td>
                                </tr>
                                @endif
                                @if($leg->provider_earnings)
                                <tr>
                                    <td class="text-muted">Your Earnings:</td>
                                    <td class="text-success"><strong>&#8358;{{ number_format($leg->provider_earnings, 2) }}</strong></td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>

                    {{-- Packages Info --}}
                    @if($leg->request && $leg->request->packages && $leg->request->packages->count() > 0)
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-boxes mr-2"></i>Packages ({{ $leg->request->packages->count() }})</h3>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                @foreach($leg->request->packages as $package)
                                <li class="list-group-item">
                                    <strong>{{ $package->description ?? 'Package #' . $loop->iteration }}</strong>
                                    <br>
                                    <small class="text-muted">
                                        @if($package->weight_kg){{ $package->weight_kg }} kg @endif
                                        @if($package->length_cm) | {{ $package->length_cm }}x{{ $package->width_cm }}x{{ $package->height_cm }} cm @endif
                                    </small>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
