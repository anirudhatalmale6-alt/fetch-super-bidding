@extends('admin.layouts.app')

@section('title', 'Edit Trucking Company - ' . $company->company_name)

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row page-header">
        <div class="col-lg-12">
            <h2 class="page-title">
                <i class="fa fa-edit"></i> Edit Trucking Company
                <small>{{ $company->company_name }}</small>
            </h2>
            <ol class="breadcrumb">
                <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li><a href="{{ route('admin.interstate.companies.index') }}">Trucking Companies</a></li>
                <li class="active">Edit</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-building"></i> Company Information
                    </h3>
                </div>
                <div class="panel-body">
                    <form action="{{ route('admin.interstate.companies.update', $company->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <!-- Company Type Selection - HIGHLIGHTED -->
                        <div class="form-group">
                            <label for="company_type">Company Type <span class="text-danger">*</span></label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="panel panel-default company-type-option {{ $company->company_type == 'last_mile' ? 'active' : '' }}" data-value="last_mile">
                                        <div class="panel-body text-center">
                                            <input type="radio" name="company_type" value="last_mile" required style="display:none;" id="type_last_mile" {{ $company->company_type == 'last_mile' ? 'checked' : '' }}>
                                            <i class="fa fa-motorcycle fa-3x {{ $company->company_type == 'last_mile' ? 'text-success' : 'text-info' }}"></i>
                                            <h4>Last Mile</h4>
                                            <p class="text-muted">Local delivery only</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="panel panel-default company-type-option {{ $company->company_type == 'interstate_trucking' ? 'active' : '' }}" data-value="interstate_trucking">
                                        <div class="panel-body text-center">
                                            <input type="radio" name="company_type" value="interstate_trucking" required style="display:none;" id="type_interstate" {{ $company->company_type == 'interstate_trucking' ? 'checked' : '' }}>
                                            <i class="fa fa-truck fa-3x {{ $company->company_type == 'interstate_trucking' ? 'text-success' : 'text-primary' }}"></i>
                                            <h4>Interstate</h4>
                                            <p class="text-muted">Long distance trucking</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="panel panel-default company-type-option {{ $company->company_type == 'both' ? 'active' : '' }}" data-value="both">
                                        <div class="panel-body text-center">
                                            <input type="radio" name="company_type" value="both" required style="display:none;" id="type_both" {{ $company->company_type == 'both' ? 'checked' : '' }}>
                                            <i class="fa fa-road fa-3x {{ $company->company_type == 'both' ? 'text-success' : 'text-success' }}"></i>
                                            <h4>Both</h4>
                                            <p class="text-muted">All services</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @error('company_type')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="company_name">Company Name <span class="text-danger">*</span></label>
                                    <input type="text" name="company_name" id="company_name" class="form-control" value="{{ old('company_name', $company->company_name) }}" required>
                                    @error('company_name')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="registration_number">Registration Number <span class="text-danger">*</span></label>
                                    <input type="text" name="registration_number" id="registration_number" class="form-control" value="{{ old('registration_number', $company->registration_number) }}" required>
                                    @error('registration_number')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $company->email) }}" required>
                                    @error('email')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone">Phone <span class="text-danger">*</span></label>
                                    <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone', $company->phone) }}" required>
                                    @error('phone')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select name="status" id="status" class="form-control">
                                        <option value="active" {{ $company->status == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="pending" {{ $company->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="suspended" {{ $company->status == 'suspended' ? 'selected' : '' }}>Suspended</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="fleet_size">Fleet Size</label>
                                    <input type="number" name="fleet_size" id="fleet_size" class="form-control" value="{{ old('fleet_size', $company->fleet_size) }}" min="0">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="default_price_per_kg">Default Price per KG (₦)</label>
                                    <input type="number" name="default_price_per_kg" id="default_price_per_kg" class="form-control" value="{{ old('default_price_per_kg', $company->default_price_per_kg) }}" min="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="insurance_rate_percent">Insurance Rate (%)</label>
                                    <input type="number" name="insurance_rate_percent" id="insurance_rate_percent" class="form-control" value="{{ old('insurance_rate_percent', $company->insurance_rate_percent) }}" min="0" max="100" step="0.01">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Service Types</label>
                            @php
                                $serviceTypes = $company->service_types ?? [];
                            @endphp
                            <div class="checkbox">
                                <label><input type="checkbox" name="service_types[]" value="standard" {{ in_array('standard', $serviceTypes) ? 'checked' : '' }}> Standard</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="service_types[]" value="express" {{ in_array('express', $serviceTypes) ? 'checked' : '' }}> Express</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="service_types[]" value="same_day" {{ in_array('same_day', $serviceTypes) ? 'checked' : '' }}> Same Day</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Operating States</label>
                            @php
                                $operatingStates = $company->operating_states ?? [];
                            @endphp
                            <select name="operating_states[]" class="form-control" multiple>
                                <option value="lagos" {{ in_array('lagos', $operatingStates) ? 'selected' : '' }}>Lagos</option>
                                <option value="abuja" {{ in_array('abuja', $operatingStates) ? 'selected' : '' }}>Abuja</option>
                                <option value="kano" {{ in_array('kano', $operatingStates) ? 'selected' : '' }}>Kano</option>
                                <option value="rivers" {{ in_array('rivers', $operatingStates) ? 'selected' : '' }}>Rivers</option>
                                <option value="oyo" {{ in_array('oyo', $operatingStates) ? 'selected' : '' }}>Oyo</option>
                                <option value="kaduna" {{ in_array('kaduna', $operatingStates) ? 'selected' : '' }}>Kaduna</option>
                                <option value="enugu" {{ in_array('enugu', $operatingStates) ? 'selected' : '' }}>Enugu</option>
                                <option value="delta" {{ in_array('delta', $operatingStates) ? 'selected' : '' }}>Delta</option>
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple states</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="logo">Company Logo</label>
                                    <input type="file" name="logo" id="logo" class="form-control" accept="image/*">
                                </div>
                            </div>
                            <div class="col-md-6">
                                @if($company->logo)
                                <div class="form-group">
                                    <label>Current Logo</label>
                                    <br>
                                    <img src="{{ asset('storage/' . $company->logo) }}" alt="Logo" style="max-height: 100px;">
                                </div>
                                @endif
                            </div>
                        </div>

                        <hr>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fa fa-save"></i> Update Company
                            </button>
                            <a href="{{ route('admin.interstate.companies.index') }}" class="btn btn-default btn-lg">
                                <i class="fa fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Info Panel -->
        <div class="col-lg-4">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-info-circle"></i> Company Info
                    </h3>
                </div>
                <div class="panel-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Created:</strong></td>
                            <td>{{ $company->created_at->format('M d, Y') }}</td>
                        </tr>
                        <tr>
                            <td><strong>Last Updated:</strong></td>
                            <td>{{ $company->updated_at->format('M d, Y') }}</td>
                        </tr>
                        <tr>
                            <td><strong>Current Type:</strong></td>
                            <td>
                                <span class="label label-primary">{{ ucfirst(str_replace('_', ' ', $company->company_type)) }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>
                                <span class="label label-{{ $company->status == 'active' ? 'success' : ($company->status == 'pending' ? 'warning' : 'danger') }}">
                                    {{ ucfirst($company->status) }}
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
.company-type-option {
    cursor: pointer;
    transition: all 0.3s;
    border: 2px solid transparent;
}
.company-type-option:hover {
    border-color: #337ab7;
    box-shadow: 0 0 10px rgba(51, 122, 183, 0.3);
}
.company-type-option.active {
    border-color: #5cb85c;
    background-color: #f0f9f0;
}
.company-type-option.active i {
    color: #5cb85c !important;
}
</style>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Company type selection
    $('.company-type-option').on('click', function() {
        $('.company-type-option').removeClass('active');
        $(this).addClass('active');
        $(this).find('input[type="radio"]').prop('checked', true);
        
        // Reset icon colors
        $('.company-type-option i').removeClass('text-success').each(function() {
            var originalColor = $(this).hasClass('text-info') ? 'text-info' : 
                               $(this).hasClass('text-primary') ? 'text-primary' : 'text-success';
            $(this).addClass(originalColor);
        });
        
        // Set selected icon color
        $(this).find('i').removeClass('text-info text-primary').addClass('text-success');
    });
});
</script>
@endsection
