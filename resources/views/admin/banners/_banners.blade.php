<div class="box-body">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Preview</th>
                    <th>Title</th>
                    <th>Position</th>
                    <th>Sort Order</th>
                    <th>Schedule</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($banners as $banner)
                <tr>
                    <td>{{ $banner->id }}</td>
                    <td>
                        @if($banner->image)
                            <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" 
                                 style="width: 80px; height: 50px; object-fit: cover; border-radius: 4px;">
                        @elseif($banner->video_url)
                            <div style="width: 80px; height: 50px; background: #333; color: white; display: flex; align-items: center; justify-content: center; border-radius: 4px;">
                                <i class="fa fa-video-camera"></i>
                            </div>
                        @else
                            <div style="width: 80px; height: 50px; background: #eee; display: flex; align-items: center; justify-content: center; border-radius: 4px;">
                                <span class="text-muted">No Media</span>
                            </div>
                        @endif
                    </td>
                    <td>
                        <strong>{{ Str::limit($banner->title, 40) }}</strong>
                        @if($banner->description)
                            <br><small class="text-muted">{{ Str::limit($banner->description, 50) }}</small>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-info">{{ $banner->position_label }}</span>
                    </td>
                    <td>
                        <span class="badge badge-secondary">{{ $banner->sort_order }}</span>
                    </td>
                    <td>
                        @if($banner->start_date || $banner->end_date)
                            <small>
                                @if($banner->start_date)
                                    From: {{ $banner->start_date->format('M d, Y') }}<br>
                                @endif
                                @if($banner->end_date)
                                    To: {{ $banner->end_date->format('M d, Y') }}
                                @endif
                            </small>
                        @else
                            <span class="text-muted">Always active</span>
                        @endif
                    </td>
                    <td>
                        <button class="btn btn-sm {{ $banner->is_active ? 'btn-success' : 'btn-danger' }} toggle-status" 
                                data-url="{{ route('banners.toggle-status', $banner->id) }}">
                            {{ $banner->status_label }}
                        </button>
                    </td>
                    <td>
                        <a href="{{ route('banners.edit', $banner->id) }}" class="btn btn-sm btn-info" title="Edit">
                            <i class="fa fa-edit"></i>
                        </a>
                        <button class="btn btn-sm btn-danger sweet-delete" 
                                data-url="{{ route('banners.destroy', $banner->id) }}" title="Delete">
                            <i class="fa fa-trash"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center">No banners found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($banners->hasPages())
<div class="box-footer">
    <div class="pull-right">
        {{ $banners->links() }}
    </div>
</div>
@endif
