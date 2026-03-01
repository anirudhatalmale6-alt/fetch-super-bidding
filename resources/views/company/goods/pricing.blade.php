@extends('company.layouts.app')

@section('title', 'Add/Edit Pricing')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row page-header">
        <div class="col-lg-12">
            <h2 class="page-title">
                <i class="fa fa-tag"></i> Add Pricing
                <small>{{ $item->item_number }}</small>
            </h2>
            <ol class="breadcrumb">
                <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li><a href="{{ route('company.goods.index') }}">Fleet Goods</a></li>
                <li class="active">Add Pricing</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <!-- Item Details Card -->
        <div class="col-lg-4">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-info-circle"></i> Item Details</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Item Number:</strong></td>
                            <td>{{ $item->item_number }}</td>
                        </tr>
                        <tr>
                            <td><strong>Request:</strong></td>
                            <td>{{ $item->request->request_number ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Category:</strong></td>
                            <td><span class="label label-default">{{ $item->getCategoryLabel() }}</span></td>
                        </tr>
                        <tr>
                            <td><strong>Description:</strong></td>
                            <td>{{ $item->description ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Dimensions:</strong></td>
                            <td>{{ $item->length_cm }} × {{ $item->width_cm }} × {{ $item->height_cm }} cm</td>
                        </tr>
                        <tr>
                            <td><strong>Chargeable Weight:</strong></td>
                            <td><strong class="text-primary">{{ $item->chargeable_weight_kg }} kg</strong></td>
                        </tr>
                        <tr>
                            <td><strong>Quantity:</strong></td>
                            <td>{{ $item->quantity }}</td>
                        </tr>
                        <tr>
                            <td><strong>Declared Value:</strong></td>
                            <td><strong class="text-success">₦{{ number_format($item->declared_value, 2) }}</strong></td>
                        </tr>
                        <tr>
                            <td><strong>Fragile:</strong></td>
                            <td>{!! $item->is_fragile ? '<span class="label label-danger">Yes</span>' : '<span class="label label-default">No</span>' !!}</td>
                        </tr>
                        <tr>
                            <td><strong>Insurance Required:</strong></td>
                            <td>{!! $item->requires_insurance ? '<span class="label label-warning">Yes</span>' : '<span class="label label-default">No</span>' !!}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pricing Form Card -->
        <div class="col-lg-8">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-calculator"></i> Set Pricing</h3>
                </div>
                <div class="panel-body">
                    <form action="{{ route('company.goods.save-pricing', $item->id) }}" method="POST" id="pricing-form">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="price_per_kg">Price per KG (₦)</label>
                                    <div class="input-group">
                                        <span class="input-group-addon">₦</span>
                                        <input type="number" 
                                               name="price_per_kg" 
                                               id="price_per_kg" 
                                               class="form-control" 
                                               step="0.01" 
                                               min="0"
                                               value="{{ old('price_per_kg', $suggestedPricePerKg) }}"
                                               required>
                                    </div>
                                    <p class="help-block">Suggested: ₦{{ number_format($suggestedPricePerKg, 2) }}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="insurance_rate">Insurance Rate (%)</label>
                                    <div class="input-group">
                                        <input type="number" 
                                               name="insurance_rate" 
                                               id="insurance_rate" 
                                               class="form-control" 
                                               step="0.01" 
                                               min="0"
                                               max="100"
                                               value="{{ old('insurance_rate', $suggestedInsuranceRate) }}"
                                               {{ $item->requires_insurance ? '' : 'readonly' }}
                                               required>
                                        <span class="input-group-addon">%</span>
                                    </div>
                                    <p class="help-block">
                                        @if($item->requires_insurance)
                                            Suggested: {{ $suggestedInsuranceRate }}%
                                        @else
                                            <span class="text-muted">Insurance not required for this item</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes (Optional)</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3" maxlength="500">{{ old('notes') }}</textarea>
                            <p class="help-block">Add any special notes about this pricing</p>
                        </div>

                        <hr>

                        <!-- Price Preview -->
                        <div class="row">
                            <div class="col-md-12">
                                <h4><i class="fa fa-eye"></i> Price Preview</h4>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <tr>
                                            <td>Base Price Calculation:</td>
                                            <td><strong>{{ $item->chargeable_weight_kg }} kg</strong> × <strong id="preview-price-per-kg">₦{{ number_format($suggestedPricePerKg, 2) }}</strong> × <strong>{{ $item->quantity }} qty</strong></td>
                                            <td class="text-right"><strong id="preview-base-price">₦{{ number_format($suggestedBasePrice, 2) }}</strong></td>
                                        </tr>
                                        @if($item->requires_insurance)
                                        <tr>
                                            <td>Insurance Fee:</td>
                                            <td><strong>₦{{ number_format($item->declared_value, 2) }}</strong> × <strong id="preview-insurance-rate">{{ $suggestedInsuranceRate }}%</strong></td>
                                            <td class="text-right"><strong id="preview-insurance-fee">₦{{ number_format($suggestedInsuranceFee, 2) }}</strong></td>
                                        </tr>
                                        @endif
                                        <tr class="success">
                                            <td colspan="2"><strong>Total Price</strong></td>
                                            <td class="text-right"><strong class="text-success" style="font-size: 1.2em;" id="preview-total">₦{{ number_format($suggestedTotal, 2) }}</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="form-group text-right">
                            <a href="{{ route('company.goods.index') }}" class="btn btn-default">
                                <i class="fa fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fa fa-save"></i> Save Pricing
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    const chargeableWeight = {{ $item->chargeable_weight_kg }};
    const quantity = {{ $item->quantity }};
    const declaredValue = {{ $item->declared_value }};
    const requiresInsurance = {{ $item->requires_insurance ? 'true' : 'false' }};

    function updatePreview() {
        const pricePerKg = parseFloat($('#price_per_kg').val()) || 0;
        const insuranceRate = parseFloat($('#insurance_rate').val()) || 0;

        // Calculate base price
        const basePrice = chargeableWeight * pricePerKg * quantity;
        
        // Calculate insurance
        let insuranceFee = 0;
        if (requiresInsurance) {
            insuranceFee = declaredValue * (insuranceRate / 100);
        }

        const total = basePrice + insuranceFee;

        // Update preview
        $('#preview-price-per-kg').text('₦' + pricePerKg.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#preview-base-price').text('₦' + basePrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#preview-insurance-rate').text(insuranceRate.toFixed(2) + '%');
        $('#preview-insurance-fee').text('₦' + insuranceFee.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#preview-total').text('₦' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    }

    // Update on input change
    $('#price_per_kg, #insurance_rate').on('input', updatePreview);
});
</script>
@endsection
