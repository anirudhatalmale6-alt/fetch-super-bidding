@extends('company.layouts.app')

@section('title', 'Fleet Goods Management')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row page-header">
        <div class="col-lg-12">
            <h2 class="page-title">
                <i class="fa fa-truck"></i> Fleet Goods Management
                <small>{{ $company->company_name }}</small>
            </h2>
            <ol class="breadcrumb">
                <li><a href="{{ route('company.dashboard') }}">Dashboard</a></li>
                <li class="active">Fleet Goods</li>
            </ol>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="fa fa-clock-o fa-3x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge">{{ $stats['pending_pricing'] }}</div>
                            <div>Pending Pricing</div>
                        </div>
                    </div>
                </div>
                <a href="{{ route('company.goods.pending') }}">
                    <div class="panel-footer">
                        <span class="pull-left">View Details</span>
                        <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                        <div class="clearfix"></div>
                    </div>
                </a>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="fa fa-check fa-3x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge">{{ $stats['priced'] }}</div>
                            <div>Priced Items</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="fa fa-road fa-3x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge">{{ $stats['in_transit'] }}</div>
                            <div>In Transit</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="fa fa-check-circle fa-3x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge">{{ $stats['delivered'] }}</div>
                            <div>Delivered</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Goods List -->
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-list"></i> All Goods in Fleet
                    </h3>
                    <div class="panel-tools">
                        <a href="{{ route('company.goods.pending') }}" class="btn btn-warning btn-sm">
                            <i class="fa fa-clock-o"></i> Pending Pricing
                        </a>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="goods-table">
                            <thead>
                                <tr>
                                    <th>Item #</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Dimensions (L×W×H)</th>
                                    <th>Weight</th>
                                    <th>Declared Value</th>
                                    <th>Company Pricing</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($goods as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item->item_number }}</strong>
                                        <br><small class="text-muted">Req: {{ $item->request->request_number ?? 'N/A' }}</small>
                                    </td>
                                    <td>{{ $item->description ?? 'No description' }}</td>
                                    <td>
                                        <span class="label label-default">{{ $item->getCategoryLabel() }}</span>
                                        @if($item->is_fragile)
                                            <span class="label label-danger">Fragile</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->length_cm }}×{{ $item->width_cm }}×{{ $item->height_cm }} cm</td>
                                    <td>
                                        <strong>{{ $item->chargeable_weight_kg }} kg</strong>
                                        <br><small class="text-muted">Vol: {{ $item->volumetric_weight_kg }} kg</small>
                                    </td>
                                    <td>₦{{ number_format($item->declared_value, 2) }}</td>
                                    <td>
                                        @if($item->status === 'pending_pricing')
                                            <span class="text-warning"><i class="fa fa-clock-o"></i> Pending</span>
                                        @else
                                            <strong>₦{{ number_format($item->company_total_price ?? 0, 2) }}</strong>
                                            <br><small class="text-muted">Base: ₦{{ number_format($item->company_base_price ?? 0, 2) }}</small>
                                            @if($item->company_insurance_fee > 0)
                                                <br><small class="text-muted">Ins: ₦{{ number_format($item->company_insurance_fee, 2) }}</small>
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $item->getStatusBadgeClass() }}">
                                            {{ $item->getStatusLabel() }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            @if($item->status === 'pending_pricing')
                                                <a href="{{ route('company.goods.pricing', $item->id) }}" class="btn btn-warning btn-sm">
                                                    <i class="fa fa-tag"></i> Add Pricing
                                                </a>
                                            @else
                                                <a href="{{ route('company.goods.show', $item->id) }}" class="btn btn-info btn-sm">
                                                    <i class="fa fa-eye"></i> View
                                                </a>
                                                <a href="{{ route('company.goods.pricing', $item->id) }}" class="btn btn-default btn-sm">
                                                    <i class="fa fa-edit"></i> Edit
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center">
                                        <div class="alert alert-info">
                                            <i class="fa fa-info-circle"></i> No goods assigned to your fleet yet.
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
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    $('#goods-table').DataTable({
        pageLength: 25,
        order: [[0, 'desc']]
    });
});
</script>
@endsection
