@extends('company.layouts.app')

@section('title', 'Pending Pricing - Fleet Goods')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row page-header">
        <div class="col-lg-12">
            <h2 class="page-title">
                <i class="fa fa-clock-o"></i> Pending Pricing
                <small>Goods awaiting your pricing</small>
            </h2>
            <ol class="breadcrumb">
                <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li><a href="{{ route('company.goods.index') }}">Fleet Goods</a></li>
                <li class="active">Pending Pricing</li>
            </ol>
        </div>
    </div>

    <!-- Bulk Actions -->
    <div class="row mb-3">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4>Bulk Pricing</h4>
                            <p class="text-muted">Apply the same pricing to multiple items</p>
                        </div>
                        <div class="col-md-6">
                            <form id="bulk-pricing-form" class="form-inline">
                                <div class="form-group mr-2">
                                    <input type="number" name="bulk_price_per_kg" id="bulk_price_per_kg" 
                                           class="form-control" placeholder="Price per KG (₦)" step="0.01" min="0">
                                </div>
                                <div class="form-group mr-2">
                                    <input type="number" name="bulk_insurance_rate" id="bulk_insurance_rate" 
                                           class="form-control" placeholder="Insurance Rate (%)" step="0.01" min="0" max="100">
                                </div>
                                <button type="button" class="btn btn-warning" onclick="applyBulkPricing()">
                                    <i class="fa fa-magic"></i> Apply to Selected
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Goods List -->
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-list"></i> Goods Pending Pricing ({{ $goods->total() }})
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="pending-goods-table">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="select-all" onclick="toggleSelectAll()">
                                    </th>
                                    <th>Item #</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Weight</th>
                                    <th>Declared Value</th>
                                    <th>Insurance</th>
                                    <th>Received</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($goods as $item)
                                <tr>
                                    <td>
                                        <input type="checkbox" class="item-checkbox" value="{{ $item->id }}">
                                    </td>
                                    <td>
                                        <strong>{{ $item->item_number }}</strong>
                                        <br><small class="text-muted">Req: {{ $item->request->request_number ?? 'N/A' }}</small>
                                    </td>
                                    <td>{{ $item->description ?? 'No description' }}</td>
                                    <td>
                                        <span class="label label-default">{{ $item->getCategoryLabel() }}</span>
                                        @if($item->is_fragile)
                                            <br><span class="label label-danger">Fragile</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $item->chargeable_weight_kg }} kg</strong>
                                        <br><small class="text-muted">Vol: {{ $item->volumetric_weight_kg }} kg</small>
                                    </td>
                                    <td>₦{{ number_format($item->declared_value, 2) }}</td>
                                    <td>
                                        @if($item->requires_insurance)
                                            <span class="label label-warning">Required</span>
                                        @else
                                            <span class="label label-default">Not Required</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->created_at->diffForHumans() }}</td>
                                    <td>
                                        <a href="{{ route('company.goods.pricing', $item->id) }}" class="btn btn-warning btn-sm">
                                            <i class="fa fa-tag"></i> Add Pricing
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center">
                                        <div class="alert alert-success">
                                            <i class="fa fa-check-circle"></i> No pending items! All goods have been priced.
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="panel-footer">
                    {{ $goods->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Back Button -->
    <div class="row">
        <div class="col-lg-12">
            <a href="{{ route('company.goods.index') }}" class="btn btn-default">
                <i class="fa fa-arrow-left"></i> Back to All Goods
            </a>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function toggleSelectAll() {
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

function applyBulkPricing() {
    const selectedIds = [];
    document.querySelectorAll('.item-checkbox:checked').forEach(checkbox => {
        selectedIds.push(checkbox.value);
    });

    if (selectedIds.length === 0) {
        alert('Please select at least one item');
        return;
    }

    const pricePerKg = document.getElementById('bulk_price_per_kg').value;
    const insuranceRate = document.getElementById('bulk_insurance_rate').value;

    if (!pricePerKg || pricePerKg <= 0) {
        alert('Please enter a valid price per KG');
        return;
    }

    if (confirm('Apply pricing to ' + selectedIds.length + ' selected items?')) {
        $.ajax({
            url: '{{ route("company.goods.bulk-pricing") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                item_ids: selectedIds,
                price_per_kg: pricePerKg,
                insurance_rate: insuranceRate || 0
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }
        });
    }
}

$(document).ready(function() {
    $('#pending-goods-table').DataTable({
        pageLength: 25,
        order: [[7, 'asc']], // Sort by received date
        columnDefs: [
            { orderable: false, targets: [0, 8] } // Disable sorting for checkbox and actions
        ]
    });
});
</script>
@endsection
