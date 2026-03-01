@extends('admin.layouts.web_app')

@section('title', 'Track Delivery - Request #' . $request->request_number)

@section('content')
<style>
    .leg-timeline {
        position: relative;
        padding: 20px 0;
    }
    .leg-timeline::before {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        left: 30px;
        width: 4px;
        background: #e0e0e0;
        border-radius: 2px;
    }
    .leg-item {
        position: relative;
        padding-left: 70px;
        margin-bottom: 30px;
    }
    .leg-item:last-child {
        margin-bottom: 0;
    }
    .leg-icon {
        position: absolute;
        left: 0;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
        z-index: 1;
    }
    .leg-icon.completed {
        background: #28a745;
    }
    .leg-icon.active {
        background: #007bff;
        animation: pulse 2s infinite;
    }
    .leg-icon.pending {
        background: #6c757d;
    }
    .leg-icon.cancelled {
        background: #dc3545;
    }
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    .leg-card {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        background: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .leg-card.active {
        border-color: #007bff;
        box-shadow: 0 4px 12px rgba(0,123,255,0.15);
    }
    .leg-card.completed {
        border-color: #28a745;
    }
    .leg-status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .price-breakdown {
        background: #f8f9fa;
        border-radius: 6px;
        padding: 15px;
        margin-top: 15px;
    }
    .price-row {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        border-bottom: 1px dashed #dee2e6;
    }
    .price-row:last-child {
        border-bottom: none;
        font-weight: bold;
        font-size: 1.1em;
        color: #28a745;
    }
    .progress-bar-custom {
        height: 8px;
        border-radius: 4px;
        background: #e0e0e0;
        overflow: hidden;
        margin: 10px 0;
    }
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #007bff, #28a745);
        transition: width 0.5s ease;
    }
    .payment-status-paid {
        color: #28a745;
    }
    .payment-status-pending {
        color: #ffc107;
    }
    .leg-connector {
        position: absolute;
        left: 28px;
        top: 60px;
        bottom: -30px;
        width: 4px;
        background: #28a745;
        z-index: 0;
    }
    .leg-item:last-child .leg-connector {
        display: none;
    }
</style>

<div class="container-fluid" style="padding: 30px 0;">
    <div class="row">
        <div class="col-lg-8 col-lg-offset-2">
            <!-- Header -->
            <div class="text-center mb-4">
                <h2><i class="fa fa-truck"></i> Track Your Delivery</h2>
                <p class="text-muted">Request #{{ $request->request_number }}</p>
                
                <!-- Overall Progress -->
                <div class="row mt-4">
                    <div class="col-md-6 col-md-offset-3">
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: {{ $progressPercentage }}%"></div>
                        </div>
                        <p><strong>{{ $completedLegs }} of {{ $totalLegs }}</strong> legs completed ({{ round($progressPercentage) }}%)</p>
                    </div>
                </div>
            </div>

            <!-- Legs Timeline -->
            <div class="leg-timeline">
                @foreach($legs as $leg)
                <div class="leg-item">
                    <!-- Connector Line (completed legs) -->
                    @if($leg->is_completed)
                        <div class="leg-connector"></div>
                    @endif

                    <!-- Icon -->
                    <div class="leg-icon {{ $leg->status_class }}">
                        @if($leg->is_completed)
                            <i class="fa fa-check"></i>
                        @elseif($leg->is_active)
                            <i class="fa fa-spinner fa-spin"></i>
                        @else
                            <i class="fa fa-clock-o"></i>
                        @endif
                    </div>

                    <!-- Card -->
                    <div class="leg-card {{ $leg->status_class }}">
                        <div class="row">
                            <div class="col-md-8">
                                <h4>
                                    <strong>Leg {{ $leg->leg_number }}:</strong> {{ $leg->display_name }}
                                    @if($leg->is_active)
                                        <span class="leg-status-badge" style="background: #007bff; color: white;">In Progress</span>
                                    @elseif($leg->is_completed)
                                        <span class="leg-status-badge" style="background: #28a745; color: white;">Completed</span>
                                    @else
                                        <span class="leg-status-badge" style="background: #6c757d; color: white;">Pending</span>
                                    @endif
                                </h4>
                                <p class="text-muted">
                                    <i class="fa fa-map-marker"></i> {{ $leg->pickup_location['address'] ?? 'Pickup Location' }}
                                    <i class="fa fa-arrow-right mx-2"></i>
                                    <i class="fa fa-map-marker"></i> {{ $leg->drop_location['address'] ?? 'Drop Location' }}
                                </p>
                                
                                @if($leg->provider_name)
                                    <p><i class="fa fa-user"></i> Provider: <strong>{{ $leg->provider_name }}</strong></p>
                                @endif

                                @if($leg->completed_at)
                                    <p class="text-success">
                                        <i class="fa fa-check-circle"></i> Completed on {{ $leg->completed_at->format('M d, Y H:i') }}
                                    </p>
                                @endif
                            </div>

                            <div class="col-md-4 text-right">
                                <!-- Payment Status -->
                                @if($leg->payment_status === 'paid')
                                    <p class="payment-status-paid">
                                        <i class="fa fa-check-circle"></i> Paid
                                        <br><small>₦{{ number_format($leg->paid_amount, 2) }}</small>
                                    </p>
                                @elseif($leg->is_active && $leg->payment_status !== 'paid')
                                    <p class="payment-status-pending">
                                        <i class="fa fa-clock-o"></i> Payment Required
                                    </p>
                                    <button class="btn btn-primary btn-sm" onclick="payForLeg({{ $leg->id }})">
                                        <i class="fa fa-credit-card"></i> Pay Now
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- Price Breakdown -->
                        @if($leg->total_leg_price > 0)
                        <div class="price-breakdown">
                            <h5><i class="fa fa-money"></i> Price Breakdown</h5>
                            <div class="price-row">
                                <span>Provider Base Price:</span>
                                <span>₦{{ number_format($leg->provider_base_price, 2) }}</span>
                            </div>
                            @if($leg->insurance_fee > 0)
                            <div class="price-row">
                                <span>Insurance Fee:</span>
                                <span>₦{{ number_format($leg->insurance_fee, 2) }}</span>
                            </div>
                            @endif
                            @if($leg->platform_commission > 0)
                            <div class="price-row">
                                <span>Platform Commission:</span>
                                <span>₦{{ number_format($leg->platform_commission, 2) }}</span>
                            </div>
                            @endif
                            <div class="price-row">
                                <span>Total Leg Price:</span>
                                <span>₦{{ number_format($leg->total_leg_price, 2) }}</span>
                            </div>
                        </div>
                        @endif

                        <!-- Goods Items (for interstate legs) -->
                        @if($leg->goods_items && count($leg->goods_items) > 0)
                        <div class="mt-3">
                            <h5><i class="fa fa-cubes"></i> Goods Items ({{ count($leg->goods_items) }})</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Item #</th>
                                            <th>Description</th>
                                            <th>Weight</th>
                                            <th>Value</th>
                                            <th>Company Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($leg->goods_items as $item)
                                        <tr>
                                            <td>{{ $item->item_number }}</td>
                                            <td>{{ $item->description ?? 'N/A' }}</td>
                                            <td>{{ $item->chargeable_weight_kg }} kg</td>
                                            <td>₦{{ number_format($item->declared_value, 2) }}</td>
                                            <td>
                                                @if($item->company_total_price)
                                                    ₦{{ number_format($item->company_total_price, 2) }}
                                                @else
                                                    <span class="text-warning">Pending</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif

                        <!-- Action Buttons for Active Leg -->
                        @if($leg->is_active)
                        <div class="mt-3 text-center">
                            <button class="btn btn-info" onclick="trackLiveLocation({{ $leg->id }})">
                                <i class="fa fa-map-marker"></i> Track Live Location
                            </button>
                            <button class="btn btn-default" onclick="contactProvider({{ $leg->id }})">
                                <i class="fa fa-phone"></i> Contact Provider
                            </button>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Summary Card -->
            <div class="panel panel-default mt-4">
                <div class="panel-heading">
                    <h4><i class="fa fa-file-text-o"></i> Delivery Summary</h4>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-4">
                            <p><strong>Request Number:</strong> {{ $request->request_number }}</p>
                            <p><strong>Status:</strong> <span class="label label-primary">{{ ucwords(str_replace('_', ' ', $request->status)) }}</span></p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Total Paid:</strong> ₦{{ number_format($totalPaid, 2) }}</p>
                            <p><strong>Remaining:</strong> ₦{{ number_format($remainingBalance, 2) }}</p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Created:</strong> {{ $request->created_at->format('M d, Y') }}</p>
                            @if($request->completed_at)
                                <p><strong>Completed:</strong> {{ $request->completed_at->format('M d, Y H:i') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function payForLeg(legId) {
    // Redirect to payment page
    window.location.href = '/interstate/payment/leg/' + legId;
}

function trackLiveLocation(legId) {
    // Open tracking modal or page
    window.open('/interstate/track/leg/' + legId, '_blank');
}

function contactProvider(legId) {
    // Open contact modal
    $.get('/api/v1/interstate/legs/' + legId + '/contact', function(response) {
        if (response.success) {
            alert('Provider Contact:\nName: ' + response.provider_name + '\nPhone: ' + response.provider_phone);
        }
    });
}

// Auto-refresh every 30 seconds for active deliveries
@if($hasActiveLeg)
setInterval(function() {
    location.reload();
}, 30000);
@endif
</script>
@endsection
