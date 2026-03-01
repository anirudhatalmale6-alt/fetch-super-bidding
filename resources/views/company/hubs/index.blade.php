@extends('company.layouts.app')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Manage Hubs</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('company.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Hubs</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Your Hubs</h3>
                            <div class="card-tools">
                                <a href="{{ route('company.hubs.create') }}" class="btn btn-sm btn-success">
                                    <i class="fas fa-plus"></i> Add Hub
                                </a>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0">
                            @if($hubs->count() > 0)
                            <table class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Address</th>
                                        <th>City</th>
                                        <th>State</th>
                                        <th>Phone</th>
                                        <th>Primary</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($hubs as $hub)
                                    <tr>
                                        <td>
                                            <strong>{{ $hub->name }}</strong>
                                            @if($hub->is_primary)
                                                <span class="badge badge-primary">Primary</span>
                                            @endif
                                        </td>
                                        <td>{{ Str::limit($hub->address, 30) }}</td>
                                        <td>{{ $hub->city }}</td>
                                        <td>{{ $hub->state }}</td>
                                        <td>{{ $hub->phone }}</td>
                                        <td>
                                            @if($hub->is_primary)
                                                <span class="badge badge-success">Yes</span>
                                            @else
                                                <span class="badge badge-secondary">No</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($hub->is_active)
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-secondary">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('company.hubs.edit', $hub->id) }}" class="btn btn-sm btn-info">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('company.hubs.destroy', $hub->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @else
                            <div class="text-center py-5">
                                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                <h5>No Hubs Yet</h5>
                                <p class="text-muted">Add your first hub to get started.</p>
                                <a href="{{ route('company.hubs.create') }}" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Add Hub
                                </a>
                            </div>
                            @endif
                        </div>
                        @if($hubs->hasPages())
                        <div class="card-footer">
                            {{ $hubs->links() }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
