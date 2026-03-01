@extends('admin.layouts.app')

@section('title', 'Create Trucking Company')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row page-header">
        <div class="col-lg-12">
            <h2 class="page-title">
                <i class="fa fa-plus-circle"></i> Create Trucking Company
            </h2>
            <ol class="breadcrumb">
                <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li><a href="{{ route('admin.interstate.companies.index') }}">Trucking Companies</a></li>
                <li class="active">Create</li>
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
                    <form action="{{ route('admin.interstate.companies.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <!-- Company Type Selection - HIGHLIGHTED -->
                        <div class="form-group">
                            <label for="company_type">Company Type <span class="text-danger">*</span></label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="panel panel-default company-type-option" data-value="last_mile">
                                        <div class="panel-body text-center">
                                            <input type="radio" name="company_type" value="last_mile" required style="display:none;" id="type_last_mile">
                                            <i class="fa fa-motorcycle fa-3x text-info"></i>
                                            <h4>Last Mile</h4>
                                            <p class="text-muted">Local delivery only</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="panel panel-default company-type-option" data-value="interstate_trucking">
                                        <div class="panel-body text-center">
                                            <input type="radio" name="company_type" value="interstate_trucking" required style="display:none;" id="type_interstate">
                                            <i class="fa fa-truck fa-3x text-primary"></i>
                                            <h4>Interstate</h4>
                                            <p class="text-muted">Long distance trucking</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="panel panel-default company-type-option" data-value="both">
                                        <div class="panel-body text-center">
                                            <input type="radio" name="company_type" value="both" required style="display:none;" id="type_both">
                                            <i class="fa fa-road fa-3x text-success"></i>
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
                                    <input type="text" name="company_name" id="company_name" class="form-control" value="{{ old('company_name') }}" required>
                                    @error('company_name')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="registration_number">Registration Number <span class="text-danger">*</span></label>
                                    <input type="text" name="registration_number" id="registration_number" class="form-control" value="{{ old('registration_number') }}" required>
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
                                    <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}" required>
                                    @error('email')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone">Phone <span class="text-danger">*</span></label>
                                    <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone') }}" required>
                                    @error('phone')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="fleet_size">Fleet Size</label>
                                    <input type="number" name="fleet_size" id="fleet_size" class="form-control" value="{{ old('fleet_size', 0) }}" min="0">
                                    @error('fleet_size')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="default_price_per_kg">Default Price per KG (₦)</label>
                                    <input type="number" name="default_price_per_kg" id="default_price_per_kg" class="form-control" value="{{ old('default_price_per_kg', 1000) }}" min="0" step="0.01">
                                    @error('default_price_per_kg')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="insurance_rate_percent">Insurance Rate (%)</label>
                                    <input type="number" name="insurance_rate_percent" id="insurance_rate_percent" class="form-control" value="{{ old('insurance_rate_percent', 1.0) }}" min="0" max="100" step="0.01">
                                    @error('insurance_rate_percent')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Service Types</label>
                            <div class="checkbox">
                                <label><input type="checkbox" name="service_types[]" value="standard"> Standard</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="service_types[]" value="express"> Express</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="service_types[]" value="same_day"> Same Day</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Operating States</label>
                            <select name="operating_states[]" class="form-control" multiple>
                                <option value="lagos">Lagos</option>
                                <option value="abuja">Abuja</option>
                                <option value="kano">Kano</option>
                                <option value="rivers">Rivers</option>
                                <option value="oyo">Oyo</option>
                                <option value="kaduna">Kaduna</option>
                                <option value="enugu">Enugu</option>
                                <option value="delta">Delta</option>
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple states</small>
                        </div>

                        <div class="form-group">
                            <label for="logo">Company Logo</label>
                            <input type="file" name="logo" id="logo" class="form-control" accept="image/*">
                        </div>

                        <hr>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fa fa-save"></i> Create Company
                            </button>
                            <a href="{{ route('admin.interstate.companies.index') }}" class="btn btn-default btn-lg">
                                <i class="fa fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Help Panel -->
        <div class="col-lg-4">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-info-circle"></i> Help
                    </h3>
                </div>
                <div class="panel-body">
                    <h4>Company Types</h4>
                    <ul>
                        <li><strong>Last Mile:</strong> For local/city deliveries only</li>
                        <li><strong>Interstate:</strong> For long-distance trucking</li>
                        <li><strong>Both:</strong> Full service provider</li>
                    </ul>
                    <hr>
                    <h4>Important Notes</h4>
                    <ul>
                        <li>Company will be created with <strong>Pending</strong> status</li>
                        <li>Admin approval required before company can operate</li>
                        <li>Registration number must be unique</li>
                        <li>Email must be unique</li>
                    </ul>
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
    });

    // Set initial selection
    var initialType = $('input[name="company_type"]:checked').val();
    if(initialType) {
        $('.company-type-option[data-value="' + initialType + '"]').addClass('active');
    }
});
</script>
@endsection
