@extends('company.layouts.app')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Profile</h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Company Information</h3>
                        </div>
                        <form action="{{ route('company.profile.update') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="card-body">
                                @if(session('success'))
                                    <div class="alert alert-success">{{ session('success') }}</div>
                                @endif

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Company Name <span class="text-danger">*</span></label>
                                            <input type="text" name="company_name" class="form-control @error('company_name') is-invalid @enderror" value="{{ old('company_name', $owner->company_name) }}" required>
                                            @error('company_name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Owner Name <span class="text-danger">*</span></label>
                                            <input type="text" name="owner_name" class="form-control @error('owner_name') is-invalid @enderror" value="{{ old('owner_name', $owner->owner_name) }}" required>
                                            @error('owner_name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Email <span class="text-danger">*</span></label>
                                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $owner->email) }}" required>
                                            @error('email')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Mobile <span class="text-danger">*</span></label>
                                            <input type="text" name="mobile" class="form-control @error('mobile') is-invalid @enderror" value="{{ old('mobile', $owner->mobile) }}" required>
                                            @error('mobile')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Phone</label>
                                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $owner->phone) }}">
                                            @error('phone')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Tax Number</label>
                                            <input type="text" name="tax_number" class="form-control @error('tax_number') is-invalid @enderror" value="{{ old('tax_number', $owner->tax_number) }}">
                                            @error('tax_number')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Address <span class="text-danger">*</span></label>
                                    <textarea name="address" class="form-control @error('address') is-invalid @enderror" rows="2" required>{{ old('address', $owner->address) }}</textarea>
                                    @error('address')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>City <span class="text-danger">*</span></label>
                                            <input type="text" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $owner->city) }}" required>
                                            @error('city')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Postal Code <span class="text-danger">*</span></label>
                                            <input type="text" name="postal_code" class="form-control @error('postal_code') is-invalid @enderror" value="{{ old('postal_code', $owner->postal_code) }}" required>
                                            @error('postal_code')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <h5>Bank Details</h5>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Bank Name</label>
                                            <input type="text" name="bank_name" class="form-control @error('bank_name') is-invalid @enderror" value="{{ old('bank_name', $owner->bank_name) }}">
                                            @error('bank_name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Account Number</label>
                                            <input type="text" name="account_no" class="form-control @error('account_no') is-invalid @enderror" value="{{ old('account_no', $owner->account_no) }}">
                                            @error('account_no')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>IFSC Code</label>
                                            <input type="text" name="ifsc" class="form-control @error('ifsc') is-invalid @enderror" value="{{ old('ifsc', $owner->ifsc) }}">
                                            @error('ifsc')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                </div>

                                @if($company)
                                <hr>
                                <h5>Trucking Company Details</h5>

                                <div class="form-group">
                                    <label>Hub Address</label>
                                    <textarea name="hub_address" class="form-control @error('hub_address') is-invalid @enderror" rows="2">{{ old('hub_address', $company->hub_address) }}</textarea>
                                    @error('hub_address')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Fleet Size</label>
                                            <input type="number" name="fleet_size" class="form-control @error('fleet_size') is-invalid @enderror" value="{{ old('fleet_size', $company->fleet_size) }}">
                                            @error('fleet_size')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Insurance %</label>
                                            <input type="number" step="0.01" name="insurance_percentage" class="form-control @error('insurance_percentage') is-invalid @enderror" value="{{ old('insurance_percentage', $company->insurance_percentage) }}">
                                            @error('insurance_percentage')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Service Description</label>
                                    <textarea name="service_description" class="form-control @error('service_description') is-invalid @enderror" rows="3">{{ old('service_description', $company->service_description) }}</textarea>
                                    @error('service_description')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                </div>
                                @endif
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                <a href="{{ route('company.dashboard') }}" class="btn btn-default">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Logo Upload -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Company Logo</h3>
                        </div>
                        <div class="card-body text-center">
                            @if($owner->logo)
                                <img src="{{ Storage::url($owner->logo) }}" alt="Company Logo" class="img-fluid mb-3" style="max-height: 150px;">
                            @else
                                <div class="mb-3">
                                    <i class="fas fa-building fa-5x text-muted"></i>
                                </div>
                                <p class="text-muted">No logo uploaded</p>
                            @endif
                            <input type="file" name="logo" form="logoForm" class="form-control-file" accept="image/*">
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Quick Links</h3>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <a href="{{ route('company.profile.changePassword') }}">
                                        <i class="fas fa-key mr-2"></i> Change Password
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="{{ route('company.profile.documents') }}">
                                        <i class="fas fa-file-alt mr-2"></i> Manage Documents
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="{{ route('company.profile.settings') }}">
                                        <i class="fas fa-cog mr-2"></i> Settings
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="{{ route('company.notifications.index') }}">
                                        <i class="fas fa-bell mr-2"></i> Notifications
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
