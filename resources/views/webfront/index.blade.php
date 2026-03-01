@extends('admin.layouts.web_header');

<style>
    .flickity-page-dots .dot.is-selected {
        background-color: #2bc9de;
    }

    #welcome:before {
        position: absolute;
        content: " ";
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: block;
        z-index: 0;
        background-color: #ffffff;
    }

    .nav-link.home {
        background: var(--logo-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .nav-link.home::before {
        content: "";
        position: absolute;
        left: .75rem;
        right: .75rem;
        bottom: .25rem;
        border-top: 2px solid #3dd845;
    }

    /* ─── Hero Video/Banner Slider ─── */
    .hero-slider-wrap {
        position: relative;
        width: 100%;
        overflow: hidden;
        background: #0f172a;
        min-height: 420px;
    }
    .hero-slide {
        display: none;
        position: relative;
        width: 100%;
    }
    .hero-slide.active { display: block; }
    .hero-slide video {
        width: 100%;
        max-height: 520px;
        object-fit: cover;
        display: block;
    }
    .hero-slide img {
        width: 100%;
        max-height: 520px;
        object-fit: cover;
        display: block;
        opacity: .88;
    }
    .hero-slide-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to right, rgba(0,0,0,.65) 0%, rgba(0,0,0,.15) 60%, transparent 100%);
        display: flex;
        align-items: center;
        padding: 0 60px;
    }
    .hero-slide-caption { color: #fff; max-width: 540px; }
    .hero-slide-caption h2 { font-size: 2.4rem; font-weight: 800; line-height: 1.2; text-shadow: 0 2px 12px rgba(0,0,0,.5); }
    .hero-slide-caption p  { font-size: 1.05rem; opacity: .9; margin-top: 12px; }
    .hero-slide-caption .btn { margin-top: 20px; border-radius: 30px; padding: 10px 28px; font-weight: 600; }

    /* arrows */
    .hero-prev, .hero-next {
        position: absolute; top: 50%; transform: translateY(-50%); z-index: 20;
        background: rgba(0,0,0,.45); border: 2px solid rgba(255,255,255,.3);
        color: #fff; width: 44px; height: 44px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: .25s; font-size: 1rem;
    }
    .hero-prev { left: 16px; } .hero-next { right: 16px; }
    .hero-prev:hover, .hero-next:hover { background: rgba(43,201,222,.8); border-color: transparent; }

    /* dots */
    .hero-dots {
        position: absolute; bottom: 14px; left: 50%; transform: translateX(-50%);
        display: flex; gap: 8px; z-index: 20;
    }
    .hero-dot {
        width: 10px; height: 10px; border-radius: 50%;
        background: rgba(255,255,255,.45); cursor: pointer; transition: .3s;
    }
    .hero-dot.active { background: #2bc9de; transform: scale(1.35); }

    /* fallback (no banners) */
    .hero-static-banner {
        background: var(--logo-gradient);
        min-height: 420px;
        display: flex;
        align-items: center;
    }
</style>

{{-- ═══════════════════════════════════════
     HERO — VIDEO / IMAGE SLIDER (TOP)
═══════════════════════════════════════ --}}
@php
    $heroBanners = \App\Models\Banner::active()
        ->where(function($q) {
            $q->where('position', 'shop')
              ->orWhere('position', 'both');
        })
        ->orderBy('sort_order')
        ->get();
@endphp

@if($heroBanners->isNotEmpty())
<div class="hero-slider-wrap mt-10" id="heroSlider">
    <button class="hero-prev" onclick="heroSlide(-1)" aria-label="Previous">
        <i class="fas fa-chevron-left"></i>
    </button>
    <button class="hero-next" onclick="heroSlide(1)" aria-label="Next">
        <i class="fas fa-chevron-right"></i>
    </button>

    @foreach($heroBanners as $i => $banner)
    <div class="hero-slide {{ $i === 0 ? 'active' : '' }}" data-index="{{ $i }}">
        @if($banner->video_url)
            <video autoplay muted loop playsinline>
                <source src="{{ $banner->video_url }}">
                <img src="{{ $banner->image_url }}" alt="{{ $banner->title ?? '' }}">
            </video>
        @elseif($banner->image)
            <img src="{{ $banner->image_url }}" alt="{{ $banner->title ?? 'FETCH Banner' }}">
        @else
            <div style="min-height:420px;background:linear-gradient(135deg,#2bc9de,#0f172a)"></div>
        @endif

        <div class="hero-slide-overlay">
            <div class="hero-slide-caption">
                @if($banner->title)
                    <h2>{{ $banner->title }}</h2>
                @endif
                @if($banner->description)
                    <p>{{ $banner->description }}</p>
                @endif
                @if($banner->button_text && $banner->button_link)
                    <a href="{{ $banner->button_link }}" class="btn btn-primary">
                        {{ $banner->button_text }}
                    </a>
                @endif
            </div>
        </div>
    </div>
    @endforeach

    <div class="hero-dots">
        @foreach($heroBanners as $i => $banner)
            <span class="hero-dot {{ $i === 0 ? 'active' : '' }}"
                  onclick="heroGoTo({{ $i }})" aria-label="Slide {{ $i+1 }}"></span>
        @endforeach
    </div>
</div>

<script>
(function () {
    let cur = 0;
    const slides = document.querySelectorAll('#heroSlider .hero-slide');
    const dots   = document.querySelectorAll('#heroSlider .hero-dot');
    let timer;

    window.heroGoTo = function (n) {
        slides[cur]?.classList.remove('active');
        dots[cur]?.classList.remove('active');
        cur = ((n % slides.length) + slides.length) % slides.length;
        slides[cur]?.classList.add('active');
        dots[cur]?.classList.add('active');
    };

    window.heroSlide = function (dir) {
        clearInterval(timer);
        heroGoTo(cur + dir);
        timer = setInterval(() => heroGoTo(cur + 1), 5500);
    };

    if (slides.length > 1) {
        timer = setInterval(() => heroGoTo(cur + 1), 5500);
    }
})();
</script>

@else
{{-- ═══════════════════════════════════════
     CUSTOM VIDEO SLIDER (Video Banner)
     Add your videos here - displays when no banners configured
═══════════════════════════════════════ --}}
<div class="hero-slider-wrap mt-10" id="customHeroSlider">
    <button class="hero-prev" onclick="customHeroSlide(-1)"><i class="fas fa-chevron-left"></i></button>
    <button class="hero-next" onclick="customHeroSlide(1)"><i class="fas fa-chevron-right"></i></button>

    {{-- Slide 1: Video with App Download --}}
    <div class="hero-slide active" data-index="0">
        <video autoplay muted loop playsinline poster="{{ asset($p.$data->bannerimage ?? 'img/pattern-1.svg') }}">
            <source src="{{ asset('assetsweb/video/hero-video.mp4') }}" type="video/mp4">
        </video>
        <div class="hero-slide-overlay">
            <div class="hero-slide-caption">
                <h2>It's time to change your ride experience!<br>Download the Tagxi app Today</h2>
                <div class="mt-4">
                    <a href="{{ url($data->userioslink ?? '#') }}" target="_blank" class="btn btn-light me-2">
                        <i class="fab fa-apple"></i> App Store
                    </a>
                    <a href="{{ url($data->userandroidlink ?? '#') }}" target="_blank" class="btn btn-outline-light">
                        <i class="fab fa-google-play"></i> Google Play
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Slide 2: Image Alternative --}}
    <div class="hero-slide" data-index="1">
        <img src="{{ asset($p.$data->bannerimage ?? 'img/pattern-1.svg') }}" alt="Tagxi Banner">
        <div class="hero-slide-overlay">
            <div class="hero-slide-caption">
                <h2>Safe. Reliable. Affordable.</h2>
                <p>Join thousands of happy riders today</p>
                <a href="{{ url('signupride') }}" class="btn btn-primary">Sign Up Now</a>
            </div>
        </div>
    </div>

    <div class="hero-dots">
        <span class="hero-dot active" onclick="customHeroGoTo(0)"></span>
        <span class="hero-dot" onclick="customHeroGoTo(1)"></span>
    </div>
</div>

<script>
(function () {
    let cur = 0;
    const slides = document.querySelectorAll('#customHeroSlider .hero-slide');
    const dots = document.querySelectorAll('#customHeroSlider .hero-dot');
    let timer;

    window.customHeroGoTo = function (n) {
        slides[cur]?.classList.remove('active');
        dots[cur]?.classList.remove('active');
        cur = ((n % slides.length) + slides.length) % slides.length;
        slides[cur]?.classList.add('active');
        dots[cur]?.classList.add('active');
    };

    window.customHeroSlide = function (dir) {
        clearInterval(timer);
        customHeroGoTo(cur + dir);
        timer = setInterval(() => customHeroGoTo(cur + 1), 5500);
    };

    if (slides.length > 1) {
        timer = setInterval(() => customHeroGoTo(cur + 1), 5500);
    }
})();
</script>

{{-- Fallback: original static banner when no banners are configured --}}
<div id="home" class="container-fluid mt-10">
    <div class="row bg-cover hero-static-banner" data-jarallax data-speed=".8"
         style="background: {{ $data->firstrowbgcolor ?? '#2bc9de' }} !important;background-image: url(img/pattern-1.svg)">
        <div class="col-md-6 p-0">
            <img src="{{ asset($p.$data->bannerimage) }}" alt="" class="w-100">
        </div>
        <div class="col-md-6 py-10 py-md-0 m-auto">
            <div class="text-center">
                @if($data)
                    <h1 class="text-white">{!! $data->description !!}</h1>
                @endif
                <div class="row">
                    <div class="col-md-6 mb-5 text-md-right">
                        <a href="{{ url($data->userioslink) }}" target="_blank">
                            <img src="{{ asset($p.$data->playstoreicon1) }}" alt="" class="w-50 w-md-75 wow slideInLeft">
                        </a>
                    </div>
                    <div class="col-md-6 mb-5 text-md-left">
                        <a href="{{ url($data->userandroidlink) }}" target="_blank">
                            <img src="{{ asset($p.$data->playstoreicon2) }}" alt="" class="w-50 w-md-75 wow slideInRight">
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════
     FEATURE CARDS (unchanged)
═══════════════════════════════════════ --}}
@if($data)

<section class="py-10 bg-light">
    <div class="container">
        <div class="row">

            <div class="col-md-4 m-auto">
                <div class=" dan-card-30 card lift p-5 mb-md-0 ">
                    <div class=" card-img-top position-relative mx-auto " style=" max-width: 120px; ">
                        <img class=" img-fluid " src="{{asset($p.$data->firstrowimage1)}}" alt=" ... ">
                    </div>
                    <div class=" card-body text-center ">
                        <h6 class=" mb-4 text-dark ">
                            {!! $data->firstrowheadtext1 !!}
                        </h6>
                        <p class=" mb-0 text-gray-500 ">
                            {!! $data->firstrowsubtext1 !!}
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-4 m-auto">
                <div class=" dan-card-30 card lift p-5 mb-md-0 ">
                    <div class=" card-img-top position-relative mx-auto " style=" max-width: 120px; ">
                        <img class=" img-fluid " src="{{asset($p.$data->firstrowimage2)}}" alt=" ... ">
                    </div>
                    <div class=" card-body text-center ">
                        <h6 class=" mb-4 text-dark ">
                            {!! $data->firstrowheadtext2 !!}
                        </h6>
                        <p class=" mb-0 text-gray-500 ">
                            {!! $data->firstrowsubtext2 !!}
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-4 m-auto">
                <div class=" dan-card-30 card lift p-5 mb-md-0 ">
                    <div class=" card-img-top position-relative mx-auto " style=" max-width: 120px; ">
                        <img class=" img-fluid " src="{{asset($p.$data->firstrowimage3)}} " alt=" ... ">
                    </div>
                    <div class=" card-body text-center ">
                        <h6 class=" mb-4 text-dark ">
                            {!! $data->firstrowheadtext3 !!}
                        </h6>
                        <p class=" mb-0 text-gray-500 ">
                            {!! $data->firstrowsubtext3 !!}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container dan-slider-2 py-8">
    <div class="row position-relative align-items-center">
        <div class="col-md-5 position-static order-2 order-md-2">
            <div class="position-static flickity-buttons-lg" id="sliderArrivals" data-flickity='{"pageDots": true}'>
                <div class="col-12">
                    <div class="card">
                        <h2>{!! $data->secondrowheadtext1 !!}</h2>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card">
                        <h2>{!! $data->secondrowheadtext2 !!}</h2>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card">
                        <h2>{!! $data->secondrowheadtext3 !!}</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-7 order-1 order-md-1">
            <div data-flickity='{"fade": true, "asNavFor": "#sliderArrivals", "draggable": false}'>
                <div class="w-100">
                    <img src="{{asset($p.$data->secondrowimage1)}}" alt="..." class="w-100">
                </div>
                <div class="w-100">
                    <img src="{{asset($p.$data->secondrowimage2)}}" alt="..." class="w-100">
                </div>
                <div class="w-100">
                    <img src="{{asset($p.$data->secondrowimage3)}}" alt="..." class="w-100">
                </div>
            </div>
        </div>
    </div>
</div>

<section class="slice slice-lg bg-gradient-primary bg-cover py-10"
         style="background: var(--logo-gradient);background-image: url('{{ asset($p.$data->afrbimage) }}')">
    <div class="container">
        <div class="mb-5 text-center">
            <h3 class="text-white mt-4">{!! $data->afrheadtext !!}</h3>
        </div>
        <div class="row row-grid align-items-center">
            <div class="col-lg-4">
                <div class="d-flex align-items-start mb-5">
                    <div class="pr-4">
                        <div class="icon icon-shape bg-white text-primary box-shadow-3 rounded-circle"
                             style="background: {{ $data->hdriverdownloadcolor }} !important;">1</div>
                    </div>
                    <div class="icon-text">
                        <h5 class="h5 text-white">{!! $data->afrstext1 !!}</h5>
                        <p class="mb-0 text-white"><br><br></p>
                    </div>
                </div>
                <div class="d-flex align-items-start">
                    <div class="pr-4">
                        <div class="icon icon-shape bg-white text-primary box-shadow-3 rounded-circle"
                             style="background: {{ $data->hdriverdownloadcolor }} !important;">2</div>
                    </div>
                    <div class="icon-text">
                        <h5 class="text-white">{!! $data->afrstext2 !!}</h5>
                        <p class="mb-0 text-white"><br><br></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="position-relative" style="z-index: 10;">
                    <img alt="Image placeholder" src="{{ asset($p.$data->afrlimage) }}" class="img-center img-fluid">
                </div>
            </div>
            <div class="col-lg-4">
                <div class="d-flex align-items-start mb-5">
                    <div class="pr-4">
                        <div class="icon icon-shape bg-white text-primary box-shadow-3 rounded-circle"
                             style="background: {{ $data->hdriverdownloadcolor }} !important;">3</div>
                    </div>
                    <div class="icon-text">
                        <h5 class="text-white">{!! $data->afrstext3 !!}</h5>
                        <p class="mb-0 text-white"><br><br></p>
                    </div>
                </div>
                <div class="d-flex align-items-start">
                    <div class="pr-4">
                        <div class="icon icon-shape bg-white text-primary box-shadow-3 rounded-circle"
                             style="background: {{ $data->hdriverdownloadcolor }} !important;">4</div>
                    </div>
                    <div class="icon-text">
                        <h5 class="text-white">{!! $data->afrstext4 !!}</h5>
                        <p class="mb-0 text-white"><br><br></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 m-auto">
            <div class="row">
                <div class="col-md-6 mb-5 text-md-right">
                    <a href="{{ url($data->driverioslink) }}" target="_blank">
                        <img src="{{ asset($p.$data->playstoreicon1) }}" alt="" class="w-50 w-md-50 wow slideInLeft">
                    </a>
                </div>
                <div class="col-md-6 mb-5 text-md-left">
                    <a href="{{ url($data->driverandroidlink) }}" target="_blank">
                        <img src="{{ asset($p.$data->playstoreicon2) }}" alt="" class="w-50 w-md-50 wow slideInRight">
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

@endif
@extends('admin.layouts.web_footer')
