@extends('company.layouts.app')

@section('title', 'Edit Route')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Route: {{ $route->origin_city }} → {{ $route->destination_city }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('company.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('company.routes.index') }}">Routes</a></li>
                        <li class="breadcrumb-item active">Edit Route</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <form action="{{ route('company.routes.update', $route->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-map-marker-alt mr-2"></i>Origin & Destination</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-3"><i class="fas fa-arrow-circle-right text-success mr-1"></i>Origin</h6>
                                <div class="form-group">
                                    <label>Origin City <span class="text-danger">*</span></label>
                                    <input type="text" name="origin_city" class="form-control @error('origin_city') is-invalid @enderror"
                                           value="{{ old('origin_city', $route->origin_city) }}" required>
                                    @error('origin_city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group">
                                    <label>Origin State <span class="text-danger">*</span></label>
                                    <input type="text" name="origin_state" class="form-control @error('origin_state') is-invalid @enderror"
                                           value="{{ old('origin_state', $route->origin_state) }}" required>
                                    @error('origin_state')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group">
                                    <label>Origin Hub</label>
                                    <select name="origin_hub_id" class="form-control select2">
                                        <option value="">-- Select Hub (Optional) --</option>
                                        @foreach($hubs as $hub)
                                            <option value="{{ $hub->id }}" {{ old('origin_hub_id', $route->origin_hub_id) == $hub->id ? 'selected' : '' }}>
                                                {{ $hub->name ?? $hub->hub_name }} ({{ $hub->city }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-3"><i class="fas fa-arrow-circle-left text-danger mr-1"></i>Destination</h6>
                                <div class="form-group">
                                    <label>Destination City <span class="text-danger">*</span></label>
                                    <input type="text" name="destination_city" class="form-control @error('destination_city') is-invalid @enderror"
                                           value="{{ old('destination_city', $route->destination_city) }}" required>
                                    @error('destination_city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group">
                                    <label>Destination State <span class="text-danger">*</span></label>
                                    <input type="text" name="destination_state" class="form-control @error('destination_state') is-invalid @enderror"
                                           value="{{ old('destination_state', $route->destination_state) }}" required>
                                    @error('destination_state')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group">
                                    <label>Destination Hub</label>
                                    <select name="destination_hub_id" class="form-control select2">
                                        <option value="">-- Select Hub (Optional) --</option>
                                        @foreach($hubs as $hub)
                                            <option value="{{ $hub->id }}" {{ old('destination_hub_id', $route->destination_hub_id) == $hub->id ? 'selected' : '' }}>
                                                {{ $hub->name ?? $hub->hub_name }} ({{ $hub->city }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-money-bill-wave mr-2"></i>Pricing & Logistics</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Base Rate per KG (&#8358;) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" name="base_rate_per_kg" class="form-control @error('base_rate_per_kg') is-invalid @enderror"
                                           value="{{ old('base_rate_per_kg', $route->base_rate_per_kg) }}" required min="0">
                                    @error('base_rate_per_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Minimum Charge (&#8358;) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" name="minimum_charge" class="form-control @error('minimum_charge') is-invalid @enderror"
                                           value="{{ old('minimum_charge', $route->minimum_charge) }}" required min="0">
                                    @error('minimum_charge')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Estimated Days <span class="text-danger">*</span></label>
                                    <input type="number" name="estimated_days" class="form-control @error('estimated_days') is-invalid @enderror"
                                           value="{{ old('estimated_days', $route->estimated_days) }}" required min="1">
                                    @error('estimated_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Distance (KM)</label>
                                    <input type="number" step="0.01" name="distance_km" class="form-control"
                                           value="{{ old('distance_km', $route->distance_km) }}" min="0">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Express Multiplier</label>
                                    <input type="number" step="0.01" name="express_multiplier" class="form-control"
                                           value="{{ old('express_multiplier', $route->express_multiplier) }}" min="1">
                                    <small class="text-muted">Price multiplier for express delivery</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Fragile Surcharge %</label>
                                    <input type="number" step="0.01" name="fragile_surcharge_percent" class="form-control"
                                           value="{{ old('fragile_surcharge_percent', $route->fragile_surcharge_percent) }}" min="0" max="100">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Insurance Rate %</label>
                                    <input type="number" step="0.01" name="insurance_rate_percent" class="form-control"
                                           value="{{ old('insurance_rate_percent', $route->insurance_rate_percent) }}" min="0" max="100">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Max Weight (KG)</label>
                                    <input type="number" step="0.01" name="max_weight_kg" class="form-control"
                                           value="{{ old('max_weight_kg', $route->max_weight_kg) }}" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-cog mr-2"></i>Settings</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="is_active"
                                               {{ old('is_active', $route->is_active) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="is_active">Active Route</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="hidden" name="is_express_available" value="0">
                                        <input type="checkbox" name="is_express_available" value="1" class="custom-control-input" id="is_express_available"
                                               {{ old('is_express_available', $route->is_express_available) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="is_express_available">Express Delivery Available</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes', $route->notes) }}</textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i>Update Route
                        </button>
                        <a href="{{ route('company.routes.index') }}" class="btn btn-secondary ml-2">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>
@endsection
