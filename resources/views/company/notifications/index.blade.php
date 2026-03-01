@extends('company.layouts.app')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Notifications</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="{{ route('company.notifications.preferences') }}" class="btn btn-primary">
                        <i class="fas fa-cog"></i> Preferences
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">All Notifications</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-sm btn-default" onclick="markAllAsRead()">
                                    Mark All as Read
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            @if($notifications->count() > 0)
                            <div class="list-group list-group-flush">
                                @foreach($notifications as $notification)
                                <div class="list-group-item d-flex justify-content-between align-items-center {{ $notification->read ? '' : 'bg-light' }}" id="notification-{{ $notification->id }}">
                                    <div class="d-flex align-items-center">
                                        <div class="mr-3">
                                            @if($notification->read)
                                                <i class="fas fa-envelope-open text-muted"></i>
                                            @else
                                                <i class="fas fa-envelope text-primary"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <h6 class="mb-1">{{ $notification->title }}</h6>
                                            <p class="mb-1">{{ $notification->message }}</p>
                                            <small class="text-muted">
                                                {{ $notification->created_at->diffForHumans() }}
                                                @if($notification->type)
                                                    <span class="badge badge-info ml-2">{{ $notification->type }}</span>
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                    <div>
                                        @if(!$notification->read)
                                        <button class="btn btn-sm btn-outline-primary mr-2" onclick="markAsRead({{ $notification->id }})">
                                            Mark as Read
                                        </button>
                                        @endif
                                        <form action="{{ route('company.notifications.destroy', $notification->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this notification?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @else
                            <div class="text-center py-5">
                                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No notifications yet</p>
                            </div>
                            @endif
                        </div>
                        @if($notifications->hasPages())
                        <div class="card-footer">
                            {{ $notifications->links() }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
    function markAsRead(id) {
        fetch(`{{ url('company/notifications') }}/${id}/mark-read`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notification = document.getElementById(`notification-${id}`);
                notification.classList.remove('bg-light');
                notification.querySelector('.fa-envelope').classList.replace('fa-envelope', 'fa-envelope-open');
                notification.querySelector('.btn-outline-primary').remove();
            }
        });
    }

    function markAllAsRead() {
        fetch('{{ route('company.notifications.markAllAsRead') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    // Poll for new notifications every 30 seconds
    setInterval(() => {
        fetch('{{ route('company.notifications.unreadCount') }}')
            .then(response => response.json())
            .then(data => {
                const badge = document.getElementById('notification-badge');
                if (badge) {
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'inline';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            });
    }, 30000);
</script>
@endsection
