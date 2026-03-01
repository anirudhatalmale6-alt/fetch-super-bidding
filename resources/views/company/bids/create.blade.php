@extends('company.layouts.app')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Place Bid</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('company.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('company.bids.available') }}">Available Requests</a></li>
                        <li class="breadcrumb-item active">Place Bid</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- Request Details -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-primary">
                            <h3 class="card-title">Request Details</h3>
                        </div>
                        <div class="card-body">
                            <h5>#{{ $deliveryRequest->request_number }}</h5>
                            <hr>
                            <p><strong><i class="fas fa-map-marker-alt text-danger"></i> Pickup:</strong><br>
                            {{ $deliveryRequest->pickup_address }}</p>
                            
                            <p><strong><i class="fas fa-flag-checkered text-success"></i> Dropoff:</strong><br>
                            {{ $deliveryRequest->drop_address }}</p>
                            
                            <hr>
                            <h6>Package Summary</h6>
                            <ul class="list-group list-group-flush">
                                @foreach($deliveryRequest->packages as $package)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    {{ $package->package_type }}
                                    <span class="badge badge-primary badge-pill">{{ $package->weight }} kg</span>
                                </li>
                                @endforeach
                            </ul>
                            <hr>
                            <p class="mb-0"><strong>Total Weight:</strong> {{ $deliveryRequest->packages->sum('weight') }} kg</p>
                            <p class="mb-0"><strong>Total Packages:</strong> {{ $deliveryRequest->packages->count() }}</p>
                        </div>
                    </div>
                </div>

                <!-- Bid Form -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Submit Your Bid</h3>
                        </div>
                        <form action="{{ route('company.bids.submit') }}" method="POST">
                            @csrf
                            <input type="hidden" name="request_id" value="{{ $deliveryRequest->id }}">
                            
                            <div class="card-body">
                                @if($errors->any())
                                    <div class="alert alert-danger">
                                        <ul class="mb-0">
                                            @foreach($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="transport_fee">Transport Fee ($) <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                                <input type="number" step="0.01" min="0" name="transport_fee" id="transport_fee" 
                                                    class="form-control @error('transport_fee') is-invalid @enderror" 
                                                    value="{{ old('transport_fee') }}" required>
                                                @error('transport_fee')
                                                    <span class="invalid-feedback">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <small class="form-text text-muted">Your fee for transportation services</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="insurance_fee">Insurance Fee ($) <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                                <input type="number" step="0.01" min="0" name="insurance_fee" id="insurance_fee" 
                                                    class="form-control @error('insurance_fee') is-invalid @enderror" 
                                                    value="{{ old('insurance_fee', $company->insurance_rate_percent ?? 0) }}" required>
                                                @error('insurance_fee')
                                                    <span class="invalid-feedback">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <small class="form-text text-muted">Insurance coverage fee</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="estimated_delivery_days">Estimated Delivery (Days) <span class="text-danger">*</span></label>
                                            <input type="number" min="1" max="30" name="estimated_delivery_days" id="estimated_delivery_days" 
                                                class="form-control @error('estimated_delivery_days') is-invalid @enderror" 
                                                value="{{ old('estimated_delivery_days', 3) }}" required>
                                            @error('estimated_delivery_days')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Total Bid Amount</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                                <input type="text" id="total_bid" class="form-control" readonly value="0.00">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="notes">Additional Notes</label>
                                    <textarea name="notes" id="notes" rows="4" 
                                        class="form-control @error('notes') is-invalid @enderror" 
                                        placeholder="Any special conditions, requirements, or notes...">{{ old('notes') }}</textarea>
                                    @error('notes')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="alert alert-info">
                                    <h5><i class="fas fa-info-circle"></i> Important</h5>
                                    <ul class="mb-0">
                                        <li>Your bid cannot be changed once the user accepts it</li>
                                        <li>You can withdraw your bid until it is accepted</li>
                                        <li>Make sure your pricing includes all applicable taxes</li>
                                        <li>Delivery time is estimated and may vary based on conditions</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-gavel"></i> Submit Bid
                                </button>
                                <a href="{{ route('company.bids.available') }}" class="btn btn-default btn-lg">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
    // Calculate total bid amount
    function calculateTotal() {
        const transportFee = parseFloat(document.getElementById('transport_fee').value) || 0;
        const insuranceFee = parseFloat(document.getElementById('insurance_fee').value) || 0;
        const total = transportFee + insuranceFee;
        document.getElementById('total_bid').value = total.toFixed(2);
    }

    document.getElementById('transport_fee').addEventListener('input', calculateTotal);
    document.getElementById('insurance_fee').addEventListener('input', calculateTotal);

    // Initial calculation
    calculateTotal();
</script>
@endsection
