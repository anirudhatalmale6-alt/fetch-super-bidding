@extends('company.layouts.app')

@section('title', 'Edit Bid')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Bid #{{ $bid->id }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('company.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('company.bids.history') }}">Bid History</a></li>
                        <li class="breadcrumb-item active">Edit Bid</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- Request Details Sidebar -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-primary">
                            <h3 class="card-title">Request #{{ $bid->request->request_number ?? $bid->request_id }}</h3>
                        </div>
                        <div class="card-body">
                            @if($bid->request)
                            <p><strong><i class="fas fa-map-marker-alt text-danger mr-1"></i>Pickup:</strong><br>
                            {{ $bid->request->pick_address }}</p>

                            <p><strong><i class="fas fa-flag-checkered text-success mr-1"></i>Dropoff:</strong><br>
                            {{ $bid->request->drop_address }}</p>

                            @if($bid->request->packages)
                            <hr>
                            <h6>Packages</h6>
                            <ul class="list-group list-group-flush">
                                @foreach($bid->request->packages as $package)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    {{ $package->package_type ?? 'Package' }}
                                    <span class="badge badge-primary badge-pill">{{ $package->weight }} kg</span>
                                </li>
                                @endforeach
                            </ul>
                            <p class="mt-2 mb-0"><strong>Total Weight:</strong> {{ $bid->request->packages->sum('weight') }} kg</p>
                            @endif
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Edit Form -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-edit mr-2"></i>Update Your Bid</h3>
                        </div>
                        <form action="{{ route('company.bids.update', $bid->id) }}" method="POST">
                            @csrf
                            @method('PUT')

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
                                            <label>Transport Fee (&#8358;) <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">&#8358;</span>
                                                </div>
                                                <input type="number" step="0.01" min="0" name="transportation_fee" id="transportation_fee"
                                                    class="form-control @error('transportation_fee') is-invalid @enderror"
                                                    value="{{ old('transportation_fee', $bid->transportation_fee) }}" required>
                                                @error('transportation_fee')
                                                    <span class="invalid-feedback">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Insurance Fee (&#8358;) <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">&#8358;</span>
                                                </div>
                                                <input type="number" step="0.01" min="0" name="insurance_fee" id="insurance_fee"
                                                    class="form-control @error('insurance_fee') is-invalid @enderror"
                                                    value="{{ old('insurance_fee', $bid->insurance_fee) }}" required>
                                                @error('insurance_fee')
                                                    <span class="invalid-feedback">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Estimated Delivery (Hours) <span class="text-danger">*</span></label>
                                            <input type="number" min="1" max="720" name="estimated_delivery_hours" id="estimated_delivery_hours"
                                                class="form-control @error('estimated_delivery_hours') is-invalid @enderror"
                                                value="{{ old('estimated_delivery_hours', $bid->estimated_delivery_hours) }}" required>
                                            @error('estimated_delivery_hours')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Total Bid Amount</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">&#8358;</span>
                                                </div>
                                                <input type="text" id="total_bid" class="form-control font-weight-bold" readonly
                                                    value="{{ number_format($bid->total_bid_amount, 2) }}">
                                            </div>
                                            <small class="text-muted">Auto-calculated</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="bid_notes" rows="3"
                                        class="form-control @error('bid_notes') is-invalid @enderror"
                                        placeholder="Any special conditions or notes...">{{ old('bid_notes', $bid->bid_notes) }}</textarea>
                                    @error('bid_notes')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Updating your bid will mark it as revised. The client will be notified of the change.
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i>Update Bid
                                </button>
                                <a href="{{ route('company.bids.show', $bid->id) }}" class="btn btn-secondary ml-2">Cancel</a>
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
    function calculateTotal() {
        const transport = parseFloat(document.getElementById('transportation_fee').value) || 0;
        const insurance = parseFloat(document.getElementById('insurance_fee').value) || 0;
        document.getElementById('total_bid').value = (transport + insurance).toFixed(2);
    }
    document.getElementById('transportation_fee').addEventListener('input', calculateTotal);
    document.getElementById('insurance_fee').addEventListener('input', calculateTotal);
    calculateTotal();
</script>
@endsection
