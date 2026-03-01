@php
use App\Models\Cart;
use App\Models\Interstate\TruckingCompany;

$cartCount = 0;
if (auth('web')->check()) {
    $truckingCompany = TruckingCompany::where('user_id', auth('web')->id())->first();
    if ($truckingCompany) {
        $cartCount = Cart::getCartCount($truckingCompany->id);
    }
}
@endphp
<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>{{ isset($pageTitle) ? $pageTitle . ' | ' : '' }}{{ app_name() ?? 'FETCH' }} — Company Portal</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Bootstrap 4 -->
    <link rel="stylesheet" href="{{ asset('assets/vendor_components/bootstrap/dist/css/bootstrap.css') }}">
    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset('assets/vendor_components/select2/dist/css/select2.min.css') }}">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- SweetAlert -->
    <link rel="stylesheet" href="{{ asset('assets/vendor_components/sweetalert/sweetalert.css') }}">
    <!-- Toast -->
    <link rel="stylesheet" href="{{ asset('assets/vendor_components/jquery-toast-plugin-master/src/jquery.toast.css') }}">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
    <!-- AdminLTE 3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <!-- Toastr -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <!-- Bootstrap extend & master style -->
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap-extend.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/master_style.css') }}">

    <style>
        :root {
            --brand-primary: #F97316;
            --brand-dark:    #1E293B;
            --brand-light:   #FFF7ED;
        }

        /* ── Sidebar ── */
        .main-sidebar { background: var(--brand-dark); }
        .brand-link    { background: var(--brand-dark); border-bottom: 1px solid rgba(255,255,255,.1); }
        .brand-text    { color:#fff!important; font-weight:700; letter-spacing:.5px; }

        .sidebar .nav-link         { color: rgba(255,255,255,.75) !important; border-radius:6px; margin:2px 8px; }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active  { background: var(--brand-primary)!important; color:#fff!important; }
        .sidebar .nav-link i       { color: rgba(255,255,255,.6)!important; }
        .sidebar .nav-link:hover i,
        .sidebar .nav-link.active i{ color:#fff!important; }

        .sidebar-mini .sidebar .nav-sidebar > .nav-item > .nav-link > p { font-size:.82rem; }

        .nav-treeview > .nav-item > .nav-link { padding-left:2.4rem!important; }

        /* ── Topbar ── */
        .main-header.navbar { background: #fff; border-bottom: 2px solid var(--brand-primary); }
        .main-header .nav-link { color: var(--brand-dark)!important; }

        /* ── Content ── */
        .content-wrapper { background: #F8FAFC; }

        /* ── Cards ── */
        .card { border:none; box-shadow:0 1px 8px rgba(0,0,0,.07); border-radius:10px; }
        .card-header { border-bottom:1px solid #EEF2FF; }

        /* ── Stat cards ── */
        .stat-card { border-radius:12px; padding:20px; display:flex; align-items:center; gap:16px; color:#fff; }
        .stat-card .stat-icon { font-size:2rem; opacity:.85; }
        .stat-card .stat-num  { font-size:1.7rem; font-weight:700; line-height:1; }
        .stat-card .stat-lbl  { font-size:.78rem; opacity:.85; }

        /* ── Alerts ── */
        .alert { border-radius:8px; }

        /* ── Sidebar badge ── */
        .badge-sidebar { font-size:.65rem; }

        /* ── Orange gradient header ── */
        .page-top-bar { background: linear-gradient(135deg, var(--brand-primary) 0%, #EA580C 100%);
                        color:#fff; padding:24px 30px 20px; border-radius:10px; margin-bottom:20px; }
        .page-top-bar h4 { margin:0; font-weight:700; }
        .page-top-bar p  { margin:0; opacity:.85; font-size:.88rem; }
    </style>

    @yield('extra-css')
</head>

<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- ─── Navbar ─── -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left: sidebar toggle + breadcrumb -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
            @hasSection('breadcrumb')
                <li class="nav-item d-none d-sm-inline-block">
                    @yield('breadcrumb')
                </li>
            @endif
        </ul>

        <!-- Right: notifications, profile -->
        <ul class="navbar-nav ml-auto">

            {{-- Notifications Bell --}}
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-bell fa-lg"></i>
                    <span class="badge badge-warning navbar-badge" id="notif-badge" style="display:none"></span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" id="notif-dropdown">
                    <span class="dropdown-item dropdown-header" id="notif-header">Notifications</span>
                    <div class="dropdown-divider"></div>
                    <div id="notif-list" style="max-height:280px;overflow-y:auto">
                        <a href="#" class="dropdown-item text-muted text-center">No new notifications</a>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="{{ route('company.notifications.index') }}" class="dropdown-item dropdown-footer">See All Notifications</a>
                </div>
            </li>

            {{-- Cart (shop) --}}
            <li class="nav-item">
                <a class="nav-link" href="{{ route('company.shop.cart') }}" title="Cart">
                    <i class="fas fa-shopping-cart fa-lg"></i>
                    @if($cartCount > 0)
                        <span class="badge badge-danger navbar-badge" id="header-cart-count">{{ $cartCount }}</span>
                    @else
                        <span class="badge badge-danger navbar-badge" id="header-cart-count" style="display: none;"></span>
                    @endif
                </a>
            </li>

            {{-- Profile --}}
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="fas fa-user-circle fa-lg" style="color:var(--brand-primary)"></i>
                    <span class="ml-1 d-none d-md-inline" style="font-size:.85rem;font-weight:600">
                        {{ auth('web')->user()->name ?? 'Company User' }}
                    </span>
                    <i class="fas fa-caret-down ml-1"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a href="{{ route('company.profile.index') }}" class="dropdown-item">
                        <i class="fas fa-user mr-2 text-warning"></i> My Profile
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ route('company.logout') }}"
                       class="dropdown-item"
                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="fas fa-sign-out-alt mr-2 text-danger"></i> Logout
                    </a>
                    <form id="logout-form" action="{{ route('company.logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </div>
            </li>
        </ul>
    </nav>
    <!-- ─── /Navbar ─── -->

    <!-- ─── Sidebar ─── -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="{{ route('company.dashboard') }}" class="brand-link" style="text-align:center;">
            <span class="brand-text font-weight-bold" style="font-size:1.15rem;">
                <i class="fas fa-truck-moving mr-1" style="color:var(--brand-primary)"></i>
                FETCH
            </span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user panel -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <div class="img-circle" style="background:var(--brand-primary);width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:50%;">
                        <i class="fas fa-building" style="color:#fff;font-size:.85rem"></i>
                    </div>
                </div>
                <div class="info">
                    <span class="d-block text-white" style="font-size:.8rem;font-weight:600;line-height:1.4;">
                        {{ auth('web')->user()->name ?? 'Company' }}
                    </span>
                    <small class="text-muted" style="font-size:.7rem;">Company Portal</small>
                </div>
            </div>

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                    {{-- Dashboard --}}
                    <li class="nav-item">
                        <a href="{{ route('company.dashboard') }}"
                           class="nav-link {{ request()->routeIs('company.dashboard') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                    {{-- SHOP --}}
                    <li class="nav-header" style="color:rgba(255,255,255,.35);font-size:.65rem;letter-spacing:1px;padding:10px 16px 4px;">EQUIPMENT STORE</li>

                    <li class="nav-item {{ request()->routeIs('company.shop*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('company.shop*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-store"></i>
                            <p>Shop<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('company.shop.index') }}" class="nav-link {{ request()->routeIs('company.shop.index') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>Browse Products</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('company.shop.cart') }}" class="nav-link {{ request()->routeIs('company.shop.cart') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>My Cart</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('company.shop.orders') }}" class="nav-link {{ request()->routeIs('company.shop.orders*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>My Orders</p>
                                </a>
                            </li>
                        </ul>
                    </li>

                    {{-- PACKAGE MANAGEMENT --}}
                    <li class="nav-item {{ request()->routeIs('company.packages*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('company.packages*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-box"></i>
                            <p>Package Management<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('company.packages.index') }}" class="nav-link {{ request()->routeIs('company.packages.index') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>All Packages</p>
                                </a>
                            </li>
                        </ul>
                    </li>

                    {{-- LOGISTICS --}}
                    <li class="nav-header" style="color:rgba(255,255,255,.35);font-size:.65rem;letter-spacing:1px;padding:10px 16px 4px;">LOGISTICS</li>

                    <li class="nav-item {{ request()->routeIs('company.goods*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('company.goods*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-boxes"></i>
                            <p>Goods Management<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('company.goods.index') }}" class="nav-link {{ request()->routeIs('company.goods.index') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>Received Goods</p>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="nav-item {{ request()->routeIs('company.bids*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('company.bids*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-gavel"></i>
                            <p>Bids<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('company.bids.index') }}" class="nav-link {{ request()->routeIs('company.bids.index') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>All Bids</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('company.bids.active') }}" class="nav-link {{ request()->routeIs('company.bids.active') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>Active Bids</p>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="nav-item">
                        <a href="{{ route('company.deliveries.index') }}"
                           class="nav-link {{ request()->routeIs('company.deliveries*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-truck"></i>
                            <p>Deliveries</p>
                        </a>
                    </li>

                    {{-- COMPANY --}}
                    <li class="nav-header" style="color:rgba(255,255,255,.35);font-size:.65rem;letter-spacing:1px;padding:10px 16px 4px;">COMPANY</li>

                    <li class="nav-item">
                        <a href="{{ route('company.hubs.index') }}"
                           class="nav-link {{ request()->routeIs('company.hubs*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-warehouse"></i>
                            <p>My Hubs</p>
                        </a>
                    </li>

                    <li class="nav-item {{ request()->routeIs('company.routes*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('company.routes*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-route"></i>
                            <p>Routes<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('company.routes.index') }}" class="nav-link {{ request()->routeIs('company.routes.index') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>My Routes</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('company.routes.create') }}" class="nav-link {{ request()->routeIs('company.routes.create') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i><p>Add Route</p>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="nav-item">
                        <a href="{{ route('company.profile.index') }}"
                           class="nav-link {{ request()->routeIs('company.profile*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-user-cog"></i>
                            <p>Profile &amp; Settings</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="{{ route('company.notifications.index') }}"
                           class="nav-link {{ request()->routeIs('company.notifications*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-bell"></i>
                            <p>Notifications</p>
                        </a>
                    </li>

                    <div class="nav-item" style="margin-top:auto;padding:16px 8px;">
                        <a href="#" class="nav-link text-danger"
                           onclick="event.preventDefault(); document.getElementById('logout-sidebar').submit();">
                            <i class="nav-icon fas fa-sign-out-alt"></i>
                            <p>Logout</p>
                        </a>
                        <form id="logout-sidebar" action="{{ route('company.logout') }}" method="POST" class="d-none">@csrf</form>
                    </div>

                </ul>
            </nav>
        </div>
    </aside>
    <!-- ─── /Sidebar ─── -->

    <!-- ─── Content Wrapper ─── -->
    <div class="content-wrapper">
        <!-- Page Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2 align-items-center">
                    <div class="col-sm-6">
                        <h4 class="m-0" style="font-weight:700;color:var(--brand-dark)">
                            {{ $pageTitle ?? 'Dashboard' }}
                        </h4>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item">
                                <a href="{{ route('company.dashboard') }}">Home</a>
                            </li>
                            @yield('breadcrumb-items')
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Session Alerts -->
        <div class="container-fluid" style="padding-top:0">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('warning') }}
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-times-circle mr-2"></i>
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </section>
    </div>
    <!-- ─── /Content Wrapper ─── -->

    <!-- Footer -->
    <footer class="main-footer text-sm">
        <strong>© {{ date('Y') }} <a href="#">FETCH</a>.</strong>
        All rights reserved.
        <div class="float-right d-none d-sm-inline-block">
            <b>Company Portal</b> v1.0
        </div>
    </footer>

</div>
<!-- /wrapper -->

<!-- jQuery -->
<script src="{{ asset('assets/vendor_components/jquery/dist/jquery.js') }}"></script>
<!-- jQuery UI -->
<script src="{{ asset('assets/vendor_components/jquery-ui/jquery-ui.js') }}"></script>
<!-- Popper -->
<script src="{{ asset('assets/vendor_components/popper/dist/popper.min.js') }}"></script>
<!-- Bootstrap 4 -->
<script src="{{ asset('assets/vendor_components/bootstrap/dist/js/bootstrap.js') }}"></script>
<!-- Select2 -->
<script src="{{ asset('assets/vendor_components/select2/dist/js/select2.full.js') }}"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="{{ asset('assets/vendor_components/sweetalert/sweetalert.min.js') }}"></script>
<!-- Toastr -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<!-- AdminLTE 3 -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>

<script>
    // CSRF setup for AJAX
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    // Auto-dismiss alerts after 5s
    setTimeout(function() {
        $('.alert').fadeOut('slow', function() { $(this).remove(); });
    }, 5000);

    // Toastr defaults
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: 'toast-top-right',
        timeOut: 4000
    };

    // Load notification count
    function loadNotificationCount() {
        $.get('{{ route("company.notifications.unread-count") }}', function(res) {
            if (res.count > 0) {
                $('#notif-badge').text(res.count).show();
                $('#notif-header').text(res.count + ' New Notification' + (res.count > 1 ? 's' : ''));
            } else {
                $('#notif-badge').hide();
                $('#notif-header').text('Notifications');
            }
        }).fail(function() {});
    }

    $(document).ready(function() {
        // Initialize select2
        $('.select2').select2({ theme: 'bootstrap4' });

        // Load initial notification count
        loadNotificationCount();

        // Refresh every 60 seconds
        setInterval(loadNotificationCount, 60000);
    });
</script>

@yield('extra-js')
</body>
</html>
