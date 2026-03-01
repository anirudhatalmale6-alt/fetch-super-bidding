@extends('admin.layouts.app')

@section('title', $pageTitle)

@section('content')

<section class="content">
    <!-- Banner Section -->
    @if($bannerProduct && ($bannerProduct->banner_image || $bannerProduct->banner_video_url))
    <div class="row mb-4">
        <div class="col-12">
            <div class="store-banner" style="position: relative; border-radius: 10px; overflow: hidden;">
                @if($bannerProduct->banner_video_url)
                    <video autoplay muted loop style="width: 100%; max-height: 300px; object-fit: cover;">
                        <source src="{{ $bannerProduct->banner_video_url }}" type="video/mp4">
                    </video>
                @else
                    <img src="{{ $bannerProduct->banner_image }}" alt="Store Banner" 
                         style="width: 100%; max-height: 300px; object-fit: cover;">
                @endif
                <div class="banner-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
                     background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.5)); display: flex; 
                     align-items: center; justify-content: center;">
                    <div class="text-center text-white">
                        <h1 class="display-4 font-weight-bold">Equipment Store</h1>
                        <p class="lead">Quality gear for your fleet</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Featured Products -->
    @if($featuredProducts->count() > 0)
    <div class="row mb-4">
        <div class="col-12">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title"><i class="fa fa-star text-warning mr-2"></i>Featured Products</h4>
                </div>
                <div class="box-body">
                    <div class="row">
                        @foreach($featuredProducts as $product)
                        <div class="col-md-4 col-lg-2 mb-3">
                            <div class="product-card card h-100" style="border-radius: 10px; overflow: hidden; 
                                 box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.3s;">
                                <div class="position-relative">
                                    <img src="{{ $product->first_image }}" class="card-img-top" 
                                         alt="{{ $product->name }}" style="height: 150px; object-fit: cover;">
                                    @if($product->has_discount)
                                        <span class="badge badge-danger" style="position: absolute; top: 10px; right: 10px;">
                                            -{{ $product->discount_percentage }}%
                                        </span>
                                    @endif
                                    @if($product->is_featured)
                                        <span class="badge badge-warning" style="position: absolute; top: 10px; left: 10px;">
                                            <i class="fa fa-star"></i>
                                        </span>
                                    @endif
                                </div>
                                <div class="card-body p-3">
                                    <h6 class="card-title mb-1" style="font-size: 14px; font-weight: 600;">
                                        {{ Str::limit($product->name, 30) }}
                                    </h6>
                                    <p class="text-muted mb-2" style="font-size: 12px;">{{ $product->category }}</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            @if($product->has_discount)
                                                <del class="text-muted" style="font-size: 12px;">${{ number_format($product->price, 2) }}</del>
                                                <span class="text-success font-weight-bold">${{ number_format($product->final_price, 2) }}</span>
                                            @else
                                                <span class="font-weight-bold">${{ number_format($product->price, 2) }}</span>
                                            @endif
                                        </div>
                                        <a href="{{ route('company.store.show', $product->id) }}" class="btn btn-primary btn-sm">
                                            View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- All Products -->
    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">All Products</h4>
                    <div class="box-tools pull-right">
                        <select id="category-filter" class="form-control input-sm" style="width: 150px; display: inline-block;">
                            <option value="">All Categories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                        <select id="sort-filter" class="form-control input-sm" style="width: 150px; display: inline-block;">
                            <option value="latest">Latest</option>
                            <option value="price_low">Price: Low to High</option>
                            <option value="price_high">Price: High to Low</option>
                            <option value="name">Name</option>
                        </select>
                    </div>
                </div>
                <div class="box-body">
                    <div id="products-grid" class="row">
                        @include('admin.company.store._products_grid', ['products' => $allProducts])
                    </div>
                </div>
                @if($allProducts->hasPages())
                <div class="box-footer">
                    <div class="pull-right">
                        {{ $allProducts->links() }}
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</section>

<style>
.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15) !important;
}
</style>

<script>
$(document).ready(function() {
    // Category filter
    $('#category-filter').on('change', function() {
        fetchProducts();
    });

    // Sort filter
    $('#sort-filter').on('change', function() {
        fetchProducts();
    });

    function fetchProducts() {
        let category = $('#category-filter').val();
        let sort = $('#sort-filter').val();

        $.ajax({
            url: '{{ route('company.store.fetch') }}',
            type: 'GET',
            data: {
                category: category,
                sort: sort
            },
            success: function(response) {
                $('#products-grid').html(response.html);
            }
        });
    }
});
</script>

@endsection
