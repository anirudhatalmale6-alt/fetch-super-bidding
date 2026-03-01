@php
use App\Models\Cart;
use App\Models\User;

$cartCount = 0;
if (auth()->check()) {
    $cartCount = Cart::where('user_id', auth()->id())->sum('quantity');
}
@endphp
<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>{{ isset($pageTitle) ? $pageTitle . ' | ' : '' }}{{ app_name() ?? 'FETCH' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/fontawesome-free/css/all.min.css') }}">
    <!-- Bootstrap 4 -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/bootstrap/css/bootstrap.min.css') }}">
    <!-- Toastr -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/toastr/toastr.min.css') }}">

    <style>
        :root {
            --brand-primary: #F97316;
            --brand-dark: #1E293B;
            --brand-light: #FFF7ED;
            --success: #22C55E;
            --danger: #EF4444;
            --warning: #F59E0B;
            --info: #3B82F6;
        }

        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #F8FAFC;
            min-height: 100vh;
        }

        /* Navbar Styles */
        .user-navbar {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 12px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .user-navbar .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--brand-primary) !important;
        }

        .user-navbar .nav-link {
            color: #64748B;
            font-weight: 500;
            padding: 8px 16px !important;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .user-navbar .nav-link:hover,
        .user-navbar .nav-link.active {
            color: var(--brand-primary);
            background: var(--brand-light);
        }

        .cart-icon {
            position: relative;
        }

        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--brand-primary);
            color: #fff;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--brand-primary) 0%, #EA580C 100%);
            color: #fff;
            padding: 40px 0;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-weight: 700;
            margin: 0;
        }

        /* Cards */
        .fetch-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: none;
            transition: all 0.3s ease;
        }

        .fetch-card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        /* Buttons */
        .btn-brand {
            background: var(--brand-primary);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-brand:hover {
            background: #EA580C;
            color: #fff;
            transform: translateY(-2px);
        }

        .btn-outline-brand {
            background: transparent;
            color: var(--brand-primary);
            border: 2px solid var(--brand-primary);
            padding: 10px 22px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-outline-brand:hover {
            background: var(--brand-primary);
            color: #fff;
        }

        /* Stats Cards */
        .stat-card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.primary { background: var(--brand-light); color: var(--brand-primary); }
        .stat-icon.success { background: #DCFCE7; color: var(--success); }
        .stat-icon.info { background: #DBEAFE; color: var(--info); }
        .stat-icon.warning { background: #FEF3C7; color: var(--warning); }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--brand-dark);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #64748B;
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-badge.pending { background: #FEF3C7; color: #92400E; }
        .status-badge.processing { background: #DBEAFE; color: #1E40AF; }
        .status-badge.shipped { background: #E0E7FF; color: #3730A3; }
        .status-badge.delivered { background: #DCFCE7; color: #166534; }
        .status-badge.cancelled { background: #FEE2E2; color: #991B1B; }
        .status-badge.paid { background: #DCFCE7; color: #166534; }
        .status-badge.unpaid { background: #FEE2E2; color: #991B1B; }

        /* Footer */
        .user-footer {
            background: var(--brand-dark);
            color: #fff;
            padding: 40px 0 20px;
            margin-top: 60px;
        }

        .user-footer a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: color 0.2s;
        }

        .user-footer a:hover {
            color: var(--brand-primary);
        }

        /* Sidebar Navigation */
        .user-sidebar {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }

        .user-sidebar .nav-link {
            color: #64748B;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 4px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-sidebar .nav-link:hover,
        .user-sidebar .nav-link.active {
            background: var(--brand-light);
            color: var(--brand-primary);
        }

        .user-sidebar .nav-link i {
            width: 20px;
            text-align: center;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 4rem;
            color: #CBD5E1;
            margin-bottom: 20px;
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #E2E8F0;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 24px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 4px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #CBD5E1;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #CBD5E1;
        }

        .timeline-item.active::before {
            background: var(--brand-primary);
            box-shadow: 0 0 0 2px var(--brand-primary);
        }

        .timeline-item.completed::before {
            background: var(--success);
            box-shadow: 0 0 0 2px var(--success);
        }

        /* Tracking Map Placeholder */
        .tracking-map {
            background: linear-gradient(135deg, #1E293B 0%, #334155 100%);
            border-radius: 16px;
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
    </style>

    @yield('extra-css')
</head>

<body>
    <!-- Navigation -->
    <nav class="user-navbar">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <a class="navbar-brand" href="{{ route('user.dashboard') }}">
                    <i class="fas fa-truck-moving mr-2"></i>FETCH
                </a>

                <div class="d-flex align-items-center">
                    <a href="{{ route('user.dashboard') }}" class="nav-link {{ request()->routeIs('user.dashboard') ? 'active' : '' }}">
                        <i class="fas fa-home mr-1"></i>Dashboard
                    </a>
                    <a href="{{ route('user.orders') }}" class="nav-link {{ request()->routeIs('user.orders*') ? 'active' : '' }}">
                        <i class="fas fa-box mr-1"></i>Orders
                    </a>
                    <a href="{{ route('user.shipments') }}" class="nav-link {{ request()->routeIs('user.shipments*') ? 'active' : '' }}">
                        <i class="fas fa-shipping-fast mr-1"></i>Shipments
                    </a>
                    <a href="{{ route('user.delivery.book') }}" class="nav-link {{ request()->routeIs('user.delivery*') ? 'active' : '' }}">
                        <i class="fas fa-plus-circle mr-1"></i>Book Delivery
                    </a>
                    <a href="{{ route('user.cart') }}" class="nav-link cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        @if($cartCount > 0)
                            <span class="cart-badge">{{ $cartCount }}</span>
                        @endif
                    </a>

                    <div class="dropdown ml-3">
                        <a href="#" class="nav-link" data-toggle="dropdown">
                            <img src="{{ auth()->user()->profile_image ?? asset('assets/images/default-avatar.png') }}"
                                 class="rounded-circle" width="36" height="36" alt="Profile">
                            <span class="ml-2 d-none d-md-inline">{{ auth()->user()->name }}</span>
                            <i class="fas fa-chevron-down ml-1 small"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a href="{{ route('user.profile') }}" class="dropdown-item">
                                <i class="fas fa-user mr-2 text-warning"></i>My Profile
                            </a>
                            <a href="{{ route('user.settings') }}" class="dropdown-item">
                                <i class="fas fa-cog mr-2 text-info"></i>Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="{{ route('logout') }}" class="dropdown-item text-danger"
                               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Session Alerts -->
    <div class="container mt-3">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        @endif
    </div>

    <!-- Main Content -->
    <main>
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="user-footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="font-weight-bold mb-3"><i class="fas fa-truck-moving mr-2"></i>FETCH</h5>
                    <p class="text-muted">Your trusted logistics partner for seamless deliveries and shipments across the nation.</p>
                </div>
                <div class="col-md-2 mb-4">
                    <h6 class="font-weight-bold mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="{{ route('user.dashboard') }}">Dashboard</a></li>
                        <li><a href="{{ route('user.orders') }}">My Orders</a></li>
                        <li><a href="{{ route('user.shipments') }}">Track Shipment</a></li>
                    </ul>
                </div>
                <div class="col-md-2 mb-4">
                    <h6 class="font-weight-bold mb-3">Support</h6>
                    <ul class="list-unstyled">
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">FAQs</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h6 class="font-weight-bold mb-3">Contact</h6>
                    <p class="text-muted mb-1"><i class="fas fa-envelope mr-2"></i>support@fetch.com</p>
                    <p class="text-muted mb-1"><i class="fas fa-phone mr-2"></i>+234 800 FETCH</p>
                </div>
            </div>
            <hr class="border-secondary">
            <div class="text-center text-muted">
                <small>&copy; {{ date('Y') }} FETCH. All rights reserved.</small>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="{{ asset('assets/plugins/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/toastr/toastr.min.js') }}"></script>

    <script>
        // Toastr defaults
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 4000
        };

        // Auto-dismiss alerts
        setTimeout(function() {
            $('.alert').fadeOut('slow', function() { $(this).remove(); });
        }, 5000);
    </script>

    @yield('extra-js')
</body>
</html>
