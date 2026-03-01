@extends('company.layouts.app')

@section('title', 'Goods Details - ' . $item->item_number)

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row page-header">
        <div class="col-lg-12">
            <h2 class="page-title">
                <i class="fa fa-box"></i> Goods Details
                <small>{{ $item->item_number }}</small>
                <span class="badge {{ $item->getStatusBadgeClass() }} ml-2">{{ $item->getStatusLabel() }}</span>
            </h2>
            <ol class="breadcrumb">
                <li><a href="{{ route('company.dashboard') }}">Dashboard</a></li>
                <li><a href="{{ route('company.goods.index') }}">Fleet Goods</a></li>
                <li class="active">{{ $item->item_number }}</li>
            </ol>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Left Column - Item Details -->
        <div class="col-lg-8">
            <!-- Item Information -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-info-circle"></i> Item Information</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
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
                                    <td>{{ $item->getCategoryLabel() }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Description:</strong></td>
                                    <td>{{ $item->description ?? 'No description' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Chargeable Weight:</strong></td>
                                    <td>{{ $item->chargeable_weight_kg }} kg</td>
                                </tr>
                                <tr>
                                    <td><strong>Dimensions:</strong></td>
                                    <td>{{ $item->length_cm }} × {{ $item->width_cm }} × {{ $item->height_cm }} cm</td>
                                </tr>
                                <tr>
                                    <td><strong>Quantity:</strong></td>
                                    <td>{{ $item->quantity }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Declared Value:</strong></td>
                                    <td>₦{{ number_format($item->declared_value, 2) }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    @if($item->is_fragile)
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i> <strong>Fragile Item</strong> - Handle with care
                    </div>
                    @endif

                    @if($item->requires_insurance)
                    <div class="alert alert-info">
                        <i class="fa fa-shield"></i> <strong>Insurance Required</strong>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Pricing Information -->
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-money"></i> Pricing & Fees</h3>
                </div>
                <div class="panel-body">
                    @if($item->company_total_price)
                    <div class="row">
                        <div class="col-md-6">
                            <h4>Company Pricing</h4>
                            <table class="table">
                                <tr>
                                    <td>Price per KG:</td>
                                    <td class="text-right">₦{{ number_format($item->company_price_per_kg, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Base Price:</td>
                                    <td class="text-right">₦{{ number_format($item->company_base_price, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Insurance Fee:</td>
                                    <td class="text-right">₦{{ number_format($item->company_insurance_fee, 2) }}</td>
                                </tr>
                                <tr class="success">
                                    <td><strong>Company Total:</strong></td>
                                    <td class="text-right"><strong>₦{{ number_format($item->company_total_price, 2) }}</strong></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h4>Service Fees</h4>
                            @if($item->transportation_service_fee)
                            <table class="table">
                                <tr>
                                    <td>Transportation Fee:</td>
                                    <td class="text-right">₦{{ number_format($item->transportation_service_fee, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Insurance Fee:</td>
                                    <td class="text-right">₦{{ number_format($item->insurance_fee, 2) }}</td>
                                </tr>
                                <tr class="info">
                                    <td><strong>Total Service Fee:</strong></td>
                                    <td class="text-right"><strong>₦{{ number_format($item->total_service_fee, 2) }}</strong></td>
                                </tr>
                            </table>
                            @else
                            <div class="alert alert-warning">
                                <i class="fa fa-clock-o"></i> Service fees not yet set
                            </div>
                            @endif
                        </div>
                    </div>

                    @if($item->pricing_breakdown && isset($item->pricing_breakdown['company_notes']))
                    <div class="well">
                        <strong>Notes:</strong> {{ $item->pricing_breakdown['company_notes'] }}
                    </div>
                    @endif
                    @else
                    <div class="alert alert-warning">
                        <i class="fa fa-clock-o"></i> Pricing pending - <a href="{{ route('company.goods.pricing', $item->id) }}">Add pricing now</a>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Status Update History -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-history"></i> Status Updates</h3>
                </div>
                <div class="panel-body">
                    @if($item->statusUpdates && $item->statusUpdates->count() > 0)
                    <div class="timeline">
                        @foreach($item->statusUpdates as $update)
                        <div class="timeline-item">
                            <div class="timeline-badge {{ $update->status_type == 'departure' ? 'bg-primary' : ($update->status_type == 'arrival' ? 'bg-success' : 'bg-info') }}">
                                <i class="fa {{ $update->status_type == 'departure' ? 'fa-plane' : ($update->status_type == 'arrival' ? 'fa-flag-checkered' : 'fa-info') }}"></i>
                            </div>
                            <div class="timeline-panel">
                                <div class="timeline-heading">
                                    <h4 class="timeline-title">{{ ucfirst(str_replace('_', ' ', $update->status_type)) }}</h4>
                                    <p class="text-muted">
                                        <i class="fa fa-clock-o"></i> {{ $update->update_timestamp->format('M d, Y H:i') }}
                                    </p>
                                </div>
                                <div class="timeline-body">
                                    <p>{{ $update->message }}</p>
                                    @if($update->location_data && $update->location_data['address'])
                                    <p class="text-muted">
                                        <i class="fa fa-map-marker"></i> {{ $update->location_data['address'] }}
                                    </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> No status updates yet.
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right Column - Actions -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="panel-body">
                    <a href="{{ route('company.goods.pricing', $item->id) }}" class="btn btn-primary btn-block mb-2">
                        <i class="fa fa-edit"></i> Edit Pricing
                    </a>

                    @if($item->status != 'delivered')
                    <button type="button" class="btn btn-warning btn-block mb-2" data-toggle="modal" data-target="#statusModal">
                        <i class="fa fa-refresh"></i> Update Status
                    </button>
                    @endif

                    <button type="button" class="btn btn-success btn-block mb-2" data-toggle="modal" data-target="#statusUpdateModal">
                        <i class="fa fa-comment"></i> Add Status Update
                    </button>

                    @if(!$item->transportation_service_fee)
                    <button type="button" class="btn btn-info btn-block" data-toggle="modal" data-target="#feesModal">
                        <i class="fa fa-money"></i> Add Service Fees
                    </button>
                    @endif
                </div>
            </div>

            <!-- Request Details -->
            @if($item->request)
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-file-text"></i> Request Details</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Request #:</strong></td>
                            <td>{{ $item->request->request_number }}</td>
                        </tr>
                        <tr>
                            <td><strong>User:</strong></td>
                            <td>{{ $item->request->user->name ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Created:</strong></td>
                            <td>{{ $item->request->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
            @endif

            <!-- Leg Details -->
            @if($item->requestLeg)
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-road"></i> Leg Information</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Leg Type:</strong></td>
                            <td>{{ ucfirst(str_replace('_', ' ', $item->requestLeg->leg_type)) }}</td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>{{ ucfirst($item->requestLeg->status) }}</td>
                        </tr>
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusUpdateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('company.goods.add-status-update', $item->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-comment"></i> Add Status Update</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Update Type</label>
                        <select name="status_type" class="form-control" required>
                            <option value="location_update">Location Update</option>
                            <option value="departure">Departure</option>
                            <option value="arrival">Arrival</option>
                            <option value="custom">Custom Message</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" class="form-control" rows="3" placeholder="e.g., Item leaving Lagos for Abuja by 10am tomorrow" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Location Address (Optional)</label>
                        <input type="text" name="location_address" class="form-control" placeholder="e.g., Lagos Warehouse">
                    </div>
                    <div class="form-group">
                        <label>Update Timestamp</label>
                        <input type="datetime-local" name="update_timestamp" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Service Fees Modal -->
<div class="modal fade" id="feesModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('company.goods.save-fees', $item->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-money"></i> Add Service Fees</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Transportation Service Fee (₦)</label>
                        <input type="number" name="transportation_service_fee" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Insurance Fee (₦)</label>
                        <input type="number" name="insurance_fee" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Save Fees</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Set default timestamp
    var now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    $('input[name="update_timestamp"]').val(now.toISOString().slice(0,16));
});
</script>
@endsection
