@extends('company.layouts.app')

@section('title', 'Add New Hub')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Add New Hub</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('company.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('company.hubs.index') }}">Hubs</a></li>
                        <li class="breadcrumb-item active">Add Hub</li>
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

            <form action="{{ route('company.hubs.store') }}" method="POST">
                @csrf
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-warehouse mr-2"></i>Hub Details</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label>Hub Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                           value="{{ old('name') }}" placeholder="e.g. Lagos Main Hub" required>
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Hub Type <span class="text-danger">*</span></label>
                                    <select name="hub_type" class="form-control @error('hub_type') is-invalid @enderror">
                                        <option value="both" {{ old('hub_type') == 'both' ? 'selected' : '' }}>Both (Origin & Destination)</option>
                                        <option value="origin" {{ old('hub_type') == 'origin' ? 'selected' : '' }}>Origin Only</option>
                                        <option value="destination" {{ old('hub_type') == 'destination' ? 'selected' : '' }}>Destination Only</option>
                                        <option value="transit" {{ old('hub_type') == 'transit' ? 'selected' : '' }}>Transit Hub</option>
                                    </select>
                                    @error('hub_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="+234...">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Address <span class="text-danger">*</span></label>
                            <textarea name="address" class="form-control @error('address') is-invalid @enderror"
                                      rows="2" placeholder="Full street address" required>{{ old('address') }}</textarea>
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>City <span class="text-danger">*</span></label>
                                    <input type="text" name="city" class="form-control @error('city') is-invalid @enderror"
                                           value="{{ old('city') }}" placeholder="e.g. Lagos" required>
                                    @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>State <span class="text-danger">*</span></label>
                                    <input type="text" name="state" class="form-control @error('state') is-invalid @enderror"
                                           value="{{ old('state') }}" placeholder="e.g. Lagos State" required>
                                    @error('state')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Country</label>
                                    <input type="text" name="country" class="form-control" value="{{ old('country', 'Nigeria') }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Postal Code</label>
                                    <input type="text" name="postal_code" class="form-control" value="{{ old('postal_code') }}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="hub@company.com">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Daily Capacity</label>
                                    <input type="number" name="daily_capacity" class="form-control" value="{{ old('daily_capacity', '100') }}" min="1">
                                    <small class="text-muted">Max packages per day</small>
                                </div>
                            </div>
                            <div class="col-md-4 d-flex align-items-center pt-3">
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox mr-4">
                                        <input type="hidden" name="is_primary" value="0">
                                        <input type="checkbox" name="is_primary" value="1" class="custom-control-input" id="is_primary"
                                               {{ old('is_primary') ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="is_primary">Primary Hub</label>
                                    </div>
                                    <div class="custom-control custom-checkbox mt-2">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="is_active"
                                               {{ old('is_active', true) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="is_active">Active</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save mr-1"></i>Create Hub
                        </button>
                        <a href="{{ route('company.hubs.index') }}" class="btn btn-secondary ml-2">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>
@endsection
