@extends('company.layouts.app')

@section('title', 'Fleet Management')

@section('extra-css')
<style>
    .fleet-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        overflow: hidden;
        transition: all 0.3s;
    }
    .fleet-card:hover {
        box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        transform: translateY(-4px);
    }
    .vehicle-image {
        height: 180px;
        background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 4rem;
        color: #94A3B8;
    }
    .fleet-card-body {
        padding: 20px;
    }
    .vehicle-title {
        font-weight: 700;
        font-size: 1.1rem;
        margin-bottom: 8px;
    }
    .vehicle-meta {
        color: #64748B;
        font-size: 0.875rem;
        margin-bottom: 4px;
    }
    .status-badge-fleet {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-active { background: #DCFCE7; color: #166534; }
    .status-maintenance { background: #FEF3C7; color: #92400E; }
    .add-vehicle-card {
        background: linear-gradient(135deg, #F97316 0%, #EA580C 100%);
        border-radius: 12px;
        height: 100%;
        min-height: 320px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #fff;
        cursor: pointer;
    }
    .add-icon {
        width: 80px;
        height: 80px;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin-bottom: 16px;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="page-top-bar mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4><i class="fas fa-truck mr-2"></i>Fleet Management</h4>
                <p>Manage your vehicles and track their performance</p>
            </div>
            <a href="{{ route('company.fleet.create') }}" class="btn btn-light">
                <i class="fas fa-plus mr-2"></i>Add Vehicle
            </a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card stat-card-orange">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-num">{{ $stats['total'] ?? 0 }}</div>
                        <div class="stat-lbl">Total Vehicles</div>
                    </div>
                    <div class="icon-circle"><i class="fas fa-truck"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card stat-card-green">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-num">{{ $stats['active'] ?? 0 }}</div>
                        <div class="stat-lbl">Active</div>
                    </div>
                    <div class="icon-circle"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card stat-card-amber">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-num">{{ $stats['maintenance'] ?? 0 }}</div>
                        <div class="stat-lbl">In Maintenance</div>
                    </div>
                    <div class="icon-circle"><i class="fas fa-wrench"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card stat-card-blue">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-num">{{ $stats['drivers'] ?? 0 }}</div>
                        <div class="stat-lbl">Assigned Drivers</div>
                    </div>
                    <div class="icon-circle"><i class="fas fa-user-tie"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-4 col-sm-6 mb-4">
            <a href="{{ route('company.fleet.create') }}" class="text-decoration-none">
                <div class="add-vehicle-card">
                    <div class="add-icon"><i class="fas fa-plus"></i></div>
                    <h5 class="font-weight-bold">Add New Vehicle</h5>
                </div>
            </a>
        </div>

        @forelse($vehicles ?? [] as $vehicle)
        <div class="col-xl-3 col-md-4 col-sm-6 mb-4">
            <div class="fleet-card">
                <div class="vehicle-image">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="fleet-card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="vehicle-title">{{ $vehicle->name }}</div>
                        <span class="status-badge-fleet status-{{ $vehicle->status }}">{{ ucfirst($vehicle->status) }}</span>
                    </div>
                    <div class="vehicle-meta"><i class="fas fa-hashtag mr-1"></i>{{ $vehicle->plate_number }}</div>
                    <div class="vehicle-meta"><i class="fas fa-user mr-1"></i>{{ $vehicle->driver ? $vehicle->driver->name : 'No Driver' }}</div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="text-center py-5">
                <i class="fas fa-truck fa-4x text-muted mb-3"></i>
                <h4>No vehicles in your fleet</h4>
            </div>
        </div>
        @endforelse
    </div>
</div>
@endsection
