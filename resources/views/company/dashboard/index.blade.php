@extends('company.layouts.app')

@section('extra-css')
<link rel="stylesheet" href="{{ asset('assets/plugins/chart.js/Chart.min.css') }}">
<style>
    /* ── Gradient stat cards ── */
    .stat-card-orange  { background: linear-gradient(135deg,#F97316,#EA580C); }
    .stat-card-blue    { background: linear-gradient(135deg,#3B82F6,#1D4ED8); }
    .stat-card-green   { background: linear-gradient(135deg,#22C55E,#15803D); }
    .stat-card-purple  { background: linear-gradient(135deg,#A855F7,#7E22CE); }
    .stat-card-teal    { background: linear-gradient(135deg,#14B8A6,#0F766E); }
    .stat-card-rose    { background: linear-gradient(135deg,#F43F5E,#BE123C); }
    .stat-card-amber   { background: linear-gradient(135deg,#F59E0B,#B45309); }
    .stat-card-indigo  { background: linear-gradient(135deg,#6366F1,#4338CA); }

    .stat-card { border-radius:14px; padding:22px 20px; color:#fff; box-shadow:0 4px 20px rgba(0,0,0,.15); }
    .stat-card .icon-circle { width:54px;height:54px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center; }
    .stat-card .icon-circle i { font-size:1.5rem; color:#fff; }
    .stat-card .stat-num { font-size:2rem;font-weight:800;line-height:1;margin:8px 0 2px; }
    .stat-card .stat-lbl { font-size:.78rem;opacity:.85;text-transform:uppercase;letter-spacing:.5px; }

    /* ── Banner slider ── */
    .banner-slider { border-radius:14px;overflow:hidden;position:relative;background:#1E293B;min-height:200px; }
    .banner-slide  { display:none;position:relative; }
    .banner-slide.active { display:block; }
    .banner-slide img  { width:100%;height:220px;object-fit:cover;opacity:.85; }
    .banner-slide video { width:100%;height:220px;object-fit:cover; }
    .banner-caption { position:absolute;bottom:0;left:0;right:0;padding:20px 24px;background:linear-gradient(transparent,rgba(0,0,0,.7));color:#fff; }
    .banner-caption h5 { margin:0;font-size:1.1rem;font-weight:700; }
    .banner-caption p  { margin:4px 0 0;font-size:.82rem;opacity:.85; }
    .banner-dots { position:absolute;bottom:8px;right:12px;display:flex;gap:5px;z-index:10; }
    .banner-dot  { width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.5);cursor:pointer;transition:.3s; }
    .banner-dot.active { background:#F97316;transform:scale(1.3); }
    .slider-prev,.slider-next { position:absolute;top:50%;transform:translateY(-50%);z-index:10;
        background:rgba(0,0,0,.4);border:none;color:#fff;width:36px;height:36px;border-radius:50%;cursor:pointer;
        display:flex;align-items:center;justify-content:center;transition:.3s; }
    .slider-prev { left:10px; } .slider-next { right:10px; }
    .slider-prev:hover,.slider-next:hover { background:rgba(249,115,22,.8); }

    /* No-banner fallback */
    .banner-placeholder { background:linear-gradient(135deg,#F97316 0%,#EA580C 50%,#1E293B 100%);
        min-height:200px;border-radius:14px;display:flex;align-items:center;justify-content:center;color:#fff; }

    /* ── Tables ── */
    .table th { font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;color:#64748B; }
    .table td { vertical-align:middle;font-size:.88rem; }
    .badge-status { font-size:.72rem;padding:4px 10px;border-radius:20px;font-weight:600; }

    /* ── Empty states ── */
    .empty-state { text-align:center;padding:40px 20px;color:#94A3B8; }
    .empty-state i { font-size:2.5rem;margin-bottom:12px;display:block; }
</style>
@endsection

@section('content')

{{-- ═══════════════════════════════════════════════════════
     BANNER / VIDEO SLIDER
═══════════════════════════════════════════════════════ --}}
<div class="row mb-4">
    <div class="col-12">
        @if($banners->isNotEmpty())
        <div class="banner-slider" id="dashboardSlider">
            <button class="slider-prev" onclick="slideBanner(-1)"><i class="fas fa-chevron-left"></i></button>
            <button class="slider-next" onclick="slideBanner(1)"><i class="fas fa-chevron-right"></i></button>

            @foreach($banners as $i => $banner)
            <div class="banner-slide {{ $i === 0 ? 'active' : '' }}" data-index="{{ $i }}">
                @if($banner->video_url)
                    <video autoplay muted loop playsinline>
                        <source src="{{ $banner->video_url }}">
                        <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}">
                    </video>
                @elseif($banner->image)
                    <img src="{{ $banner->image_url }}" alt="{{ $banner->title ?? 'Banner' }}">
                @else
                    <div style="height:220px;background:linear-gradient(135deg,#F97316,#1E293B)"></div>
                @endif

                @if($banner->title || $banner->description)
                <div class="banner-caption">
                    @if($banner->title)<h5>{{ $banner->title }}</h5>@endif
                    @if($banner->description)<p>{{ $banner->description }}</p>@endif
                    @if($banner->button_text && $banner->button_link)
                        <a href="{{ $banner->button_link }}" class="btn btn-sm btn-warning mt-2" style="border-radius:20px">
                            {{ $banner->button_text }}
                        </a>
                    @endif
                </div>
                @endif
            </div>
            @endforeach

            <!-- Dots -->
            <div class="banner-dots">
                @foreach($banners as $i => $banner)
                    <span class="banner-dot {{ $i === 0 ? 'active' : '' }}" onclick="goToSlide({{ $i }})"></span>
                @endforeach
            </div>
        </div>
        @else
        <div class="banner-placeholder">
            <div class="text-center">
                <i class="fas fa-truck-moving fa-3x mb-3" style="opacity:.6"></i>
                <h4 class="mb-1" style="font-weight:700">Welcome to FETCH Company Portal</h4>
                <p style="opacity:.8">Manage your shipments, bids, and shop orders from one place.</p>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     STATS ROW 1
═══════════════════════════════════════════════════════ --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-card-orange">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-num">{{ number_format($stats['active_bids']) }}</div>
                    <div class="stat-lbl">Active Bids</div>
                </div>
                <div class="icon-circle"><i class="fas fa-gavel"></i></div>
            </div>
            <div class="mt-2" style="font-size:.75rem;opacity:.8">
                <a href="{{ route('company.bids.active') }}" style="color:#fff;text-decoration:none">
                    View active bids <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-card-green">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-num">{{ number_format($stats['won_bids']) }}</div>
                    <div class="stat-lbl">Won Bids</div>
                </div>
                <div class="icon-circle"><i class="fas fa-trophy"></i></div>
            </div>
            <div class="mt-2" style="font-size:.75rem;opacity:.8">All time accepted bids</div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-card-blue">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-num">{{ number_format($stats['active_shipments']) }}</div>
                    <div class="stat-lbl">Active Shipments</div>
                </div>
                <div class="icon-circle"><i class="fas fa-shipping-fast"></i></div>
            </div>
            <div class="mt-2" style="font-size:.75rem;opacity:.8">In progress right now</div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-card-teal">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-num">{{ number_format($stats['completed_deliveries']) }}</div>
                    <div class="stat-lbl">Completed</div>
                </div>
                <div class="icon-circle"><i class="fas fa-check-double"></i></div>
            </div>
            <div class="mt-2" style="font-size:.75rem;opacity:.8">Total deliveries done</div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     STATS ROW 2
═══════════════════════════════════════════════════════ --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-card-purple">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-num">{{ number_format($stats['shop_orders']) }}</div>
                    <div class="stat-lbl">Shop Orders</div>
                </div>
                <div class="icon-circle"><i class="fas fa-shopping-cart"></i></div>
            </div>
            <div class="mt-2" style="font-size:.75rem;opacity:.8">
                <a href="{{ route('company.shop.orders') }}" style="color:#fff;text-decoration:none">
                    View orders <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-card-rose">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-num">{{ number_format($stats['pending_approvals']) }}</div>
                    <div class="stat-lbl">Awaiting Approval</div>
                </div>
                <div class="icon-circle"><i class="fas fa-clock"></i></div>
            </div>
            <div class="mt-2" style="font-size:.75rem;opacity:.8">Requests needing your action</div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-card-amber">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-num">₦{{ number_format($stats['total_revenue'], 0) }}</div>
                    <div class="stat-lbl">Total Revenue</div>
                </div>
                <div class="icon-circle"><i class="fas fa-naira-sign"></i></div>
            </div>
            <div class="mt-2" style="font-size:.75rem;opacity:.8">Earnings from accepted bids</div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-card-indigo">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-num">{{ $stats['rating'] ?: '—' }}</div>
                    <div class="stat-lbl">Company Rating</div>
                </div>
                <div class="icon-circle"><i class="fas fa-star"></i></div>
            </div>
            <div class="mt-2" style="font-size:.75rem;opacity:.8">
                @if($stats['rating'])
                    @for($s = 1; $s <= 5; $s++)
                        <i class="fas fa-star{{ $s <= $stats['rating'] ? '' : '-half-alt' }}" style="font-size:.7rem;color:rgba(255,255,255,.9)"></i>
                    @endfor
                @else
                    No rating yet
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     CHARTS + QUICK ACTIONS
═══════════════════════════════════════════════════════ --}}
<div class="row g-3 mb-4">
    {{-- Revenue Chart --}}
    <div class="col-xl-8 col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 font-weight-bold" style="color:#1E293B">
                    <i class="fas fa-chart-line mr-2 text-warning"></i>Monthly Revenue (Last 6 Months)
                </h6>
                <span class="badge badge-light">{{ $bidSuccessRate }}% success rate</span>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="100"></canvas>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="col-xl-4 col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0 font-weight-bold" style="color:#1E293B">
                    <i class="fas fa-bolt mr-2 text-warning"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="d-grid gap-2">
                    <a href="{{ route('company.shop.index') }}" class="btn btn-warning btn-block mb-2" style="border-radius:8px">
                        <i class="fas fa-store mr-2"></i>Browse Shop
                    </a>
                    <a href="{{ route('company.goods.index') }}" class="btn btn-primary btn-block mb-2" style="border-radius:8px">
                        <i class="fas fa-boxes mr-2"></i>Manage Goods
                    </a>
                    <a href="{{ route('company.bids.index') }}" class="btn btn-success btn-block mb-2" style="border-radius:8px">
                        <i class="fas fa-gavel mr-2"></i>View All Bids
                    </a>
                    <a href="{{ route('company.hubs.index') }}" class="btn btn-info btn-block mb-2" style="border-radius:8px">
                        <i class="fas fa-warehouse mr-2"></i>Manage Hubs
                    </a>
                    <a href="{{ route('company.routes.index') }}" class="btn btn-secondary btn-block" style="border-radius:8px">
                        <i class="fas fa-route mr-2"></i>My Routes
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     RECENT ACTIVITY TABLES
═══════════════════════════════════════════════════════ --}}
<div class="row g-3 mb-4">
    {{-- Recent Bids --}}
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 font-weight-bold" style="color:#1E293B">
                    <i class="fas fa-gavel mr-2 text-warning"></i>Recent Bids
                </h6>
                <a href="{{ route('company.bids.index') }}" class="btn btn-xs btn-outline-warning" style="border-radius:20px;font-size:.75rem;padding:3px 12px">View All</a>
            </div>
            <div class="card-body p-0">
                @if($recentBids->isEmpty())
                    <div class="empty-state">
                        <i class="fas fa-gavel"></i>
                        <p>No bids yet. Available routes will appear here.</p>
                    </div>
                @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr>
                            <th>Request #</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr></thead>
                        <tbody>
                        @foreach($recentBids as $bid)
                        <tr>
                            <td><a href="#">{{ $bid->request?->request_number ?? 'N/A' }}</a></td>
                            <td>₦{{ number_format($bid->bid_amount ?? $bid->proposed_amount ?? 0, 0) }}</td>
                            <td>
                                @php
                                    $badgeMap = ['pending'=>'warning','accepted'=>'success','rejected'=>'danger','expired'=>'secondary'];
                                    $color = $badgeMap[$bid->status] ?? 'secondary';
                                @endphp
                                <span class="badge badge-{{ $color }} badge-status">{{ ucfirst($bid->status) }}</span>
                            </td>
                            <td class="text-muted">{{ $bid->created_at->format('d M') }}</td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Recent Shipments --}}
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 font-weight-bold" style="color:#1E293B">
                    <i class="fas fa-shipping-fast mr-2 text-warning"></i>Recent Shipments
                </h6>
                <a href="{{ route('company.bids.index') }}" class="btn btn-xs btn-outline-warning" style="border-radius:20px;font-size:.75rem;padding:3px 12px">View All</a>
            </div>
            <div class="card-body p-0">
                @if($recentShipments->isEmpty())
                    <div class="empty-state">
                        <i class="fas fa-shipping-fast"></i>
                        <p>No active shipments at the moment.</p>
                    </div>
                @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr>
                            <th>Request #</th>
                            <th>Route</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr></thead>
                        <tbody>
                        @foreach($recentShipments as $shipment)
                        <tr>
                            <td>{{ $shipment->request_number }}</td>
                            <td>
                                <small class="text-muted">
                                    {{ $shipment->originHub?->city ?? '—' }} →
                                    {{ $shipment->destinationHub?->city ?? '—' }}
                                </small>
                            </td>
                            <td>
                                <span class="badge badge-info badge-status">{{ ucwords(str_replace('_',' ',$shipment->interstate_status ?? 'pending')) }}</span>
                            </td>
                            <td class="text-muted">{{ $shipment->created_at->format('d M') }}</td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Recent Shop Orders --}}
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 font-weight-bold" style="color:#1E293B">
                    <i class="fas fa-shopping-bag mr-2 text-warning"></i>Recent Shop Orders
                </h6>
                <a href="{{ route('company.shop.orders') }}" class="btn btn-xs btn-outline-warning" style="border-radius:20px;font-size:.75rem;padding:3px 12px">View All</a>
            </div>
            <div class="card-body p-0">
                @if($recentOrders->isEmpty())
                    <div class="empty-state">
                        <i class="fas fa-shopping-bag"></i>
                        <p>No orders yet. <a href="{{ route('company.shop.index') }}">Browse the shop</a> to place your first order.</p>
                    </div>
                @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr>
                            <th>Order #</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th></th>
                        </tr></thead>
                        <tbody>
                        @foreach($recentOrders as $order)
                        <tr>
                            <td><strong>{{ $order->order_number }}</strong></td>
                            <td>₦{{ number_format($order->total_amount, 0) }}</td>
                            <td>
                                @php $pColor = ['pending'=>'warning','paid'=>'success','failed'=>'danger'][$order->payment_status] ?? 'secondary'; @endphp
                                <span class="badge badge-{{ $pColor }} badge-status">{{ ucfirst($order->payment_status) }}</span>
                            </td>
                            <td>
                                @php $sColor = ['pending'=>'warning','processing'=>'info','shipped'=>'primary','delivered'=>'success','cancelled'=>'danger'][$order->status] ?? 'secondary'; @endphp
                                <span class="badge badge-{{ $sColor }} badge-status">{{ ucfirst($order->status) }}</span>
                            </td>
                            <td class="text-muted">{{ $order->created_at->format('d M Y') }}</td>
                            <td>
                                <a href="{{ route('company.shop.orders.detail', $order->id) }}" class="btn btn-xs btn-outline-secondary" style="border-radius:20px;font-size:.72rem;padding:2px 10px">View</a>
                            </td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

@section('extra-js')
<script src="{{ asset('assets/plugins/chart.js/Chart.bundle.min.js') }}"></script>
<script>
// ── Revenue Chart ──────────────────────────────────────────
(function () {
    const labels  = @json(array_column($monthlyRevenue, 'month'));
    const amounts = @json(array_column($monthlyRevenue, 'revenue'));

    const ctx = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue (₦)',
                data: amounts,
                backgroundColor: 'rgba(249,115,22,.7)',
                borderColor: '#F97316',
                borderWidth: 2,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => '₦' + ctx.parsed.y.toLocaleString()
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => '₦' + v.toLocaleString()
                    }
                }
            }
        }
    });
})();

// ── Banner Slider ──────────────────────────────────────────
let currentSlide = 0;
const slides = document.querySelectorAll('.banner-slide');
const dots   = document.querySelectorAll('.banner-dot');
let autoTimer;

function goToSlide(n) {
    slides[currentSlide]?.classList.remove('active');
    dots[currentSlide]?.classList.remove('active');
    currentSlide = (n + slides.length) % slides.length;
    slides[currentSlide]?.classList.add('active');
    dots[currentSlide]?.classList.add('active');
}

function slideBanner(dir) {
    clearInterval(autoTimer);
    goToSlide(currentSlide + dir);
    autoTimer = setInterval(() => goToSlide(currentSlide + 1), 5000);
}

if (slides.length > 1) {
    autoTimer = setInterval(() => goToSlide(currentSlide + 1), 5000);
}
</script>
@endsection
