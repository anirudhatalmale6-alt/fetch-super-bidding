@if($companyBanners->count() > 0 || $systemBanners->count() > 0)
<div id="companyBannerCarousel" class="carousel slide" data-ride="carousel" data-interval="5000">
    <ol class="carousel-indicators">
        @php $totalBanners = $companyBanners->count() + $systemBanners->count(); @endphp
        @for($i = 0; $i < $totalBanners; $i++)
        <li data-target="#companyBannerCarousel" data-slide-to="{{ $i }}" {{ $i === 0 ? 'class=active' : '' }}></li>
        @endfor
    </ol>
    <div class="carousel-inner">
        @foreach($companyBanners as $index => $banner)
        <div class="carousel-item {{ $index === 0 ? 'active' : '' }}">
            @if($banner->video_url)
            <video class="d-block w-100" autoplay muted loop playsinline style="max-height: 250px; object-fit: cover;">
                <source src="{{ $banner->video_url }}" type="video/mp4">
            </video>
            @elseif($banner->image_url)
            <img class="d-block w-100" src="{{ $banner->image_url }}" alt="{{ $banner->title }}" style="max-height: 250px; object-fit: cover;">
            @endif
            <div class="carousel-caption d-none d-md-block" style="background: rgba(0,0,0,0.6); border-radius: 8px;">
                @if($banner->title)
                <h5>{{ $banner->title }}</h5>
                @endif
                @if($banner->subtitle)
                <p>{{ $banner->subtitle }}</p>
                @endif
                @if($banner->link && $banner->button_text)
                <a href="{{ $banner->link }}" class="btn btn-primary btn-sm">{{ $banner->button_text }}</a>
                @elseif($banner->link)
                <a href="{{ $banner->link }}" class="btn btn-primary btn-sm">Learn More</a>
                @endif
            </div>
        </div>
        @endforeach
        
        @foreach($systemBanners as $index => $banner)
        <div class="carousel-item {{ $companyBanners->count() === 0 && $index === 0 ? 'active' : '' }}">
            @if($banner->media_type === 'video' && $banner->media_url)
            <video class="d-block w-100" autoplay muted loop playsinline style="max-height: 250px; object-fit: cover;">
                <source src="{{ $banner->media_url }}" type="video/mp4">
            </video>
            @elseif($banner->media_url)
            <img class="d-block w-100" src="{{ $banner->media_url }}" alt="{{ $banner->title }}" style="max-height: 250px; object-fit: cover;">
            @endif
            <div class="carousel-caption d-none d-md-block" style="background: rgba(0,0,0,0.6); border-radius: 8px;">
                @if($banner->title)
                <h5>{{ $banner->title }}</h5>
                @endif
                @if($banner->subtitle)
                <p>{{ $banner->subtitle }}</p>
                @endif
                @if($banner->link)
                <a href="{{ $banner->link }}" class="btn btn-primary btn-sm">Learn More</a>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @if($totalBanners > 1)
    <a class="carousel-control-prev" href="#companyBannerCarousel" role="button" data-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="sr-only">Previous</span>
    </a>
    <a class="carousel-control-next" href="#companyBannerCarousel" role="button" data-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="sr-only">Next</span>
    </a>
    @endif
</div>
@endif
