@extends('company.layouts.app')

@section('title', 'Equipment Shop')

@section('extra-css')
<style>
    .shop-banner {
        background: linear-gradient(135deg, #1E293B 0%, #334155 100%);
        border-radius: 12px;
        overflow: hidden;
        position: relative;
    }
    .shop-banner img {
        width: 100%;
        height: 300px;
        object-fit: cover;
    }
    .shop-banner .banner-content {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 30px;
        background: linear-gradient(transparent, rgba(0,0,0,0.8));
        color: white;
    }
    .product-card {
        border: none;
        border-radius: 12px;
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
        background: white;
    }
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    .product-image {
        height: 200px;
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .product-image img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    .product-info {
        padding: 20px;
    }
    .product-price {
        font-size: 1.25rem;
        font-weight: 700;
        color: #F97316;
    }
    .product-price .original {
        font-size: 0.9rem;
        color: #94a3b8;
        text-decoration: line-through;
        margin-left: 8px;
    }
    .category-filter {
        background: white;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .category-btn {
        border: 1px solid #e2e8f0;
        background: white;
        color: #64748b;
        padding: 8px 16px;
        border-radius: 20px;
        margin: 4px;
        transition: all 0.2s;
    }
    .category-btn:hover,
    .category-btn.active {
        background: #F97316;
        color: white;
        border-color: #F97316;
    }
    .add-to-cart-btn {
        background: #F97316;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        width: 100%;
        font-weight: 600;
        transition: background 0.2s;
    }
    .add-to-cart-btn:hover {
        background: #EA580C;
    }
    .add-to-cart-btn:disabled {
        background: #cbd5e1;
        cursor: not-allowed;
    }
    .stock-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .stock-badge.in-stock {
        background: #dcfce7;
        color: #166534;
    }
    .stock-badge.low-stock {
        background: #fef3c7;
        color: #92400e;
    }
    .stock-badge.out-of-stock {
        background: #fee2e2;
        color: #991b1b;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Shop Banner -->
    @if($banners->count() > 0)
    <div class="shop-banner mb-4">
        @php $mainBanner = $banners->first(); @endphp
        @if($mainBanner->media_type === 'video' && $mainBanner->video_url)
            <video autoplay muted loop style="width: 100%; height: 300px; object-fit: cover;">
                <source src="{{ $mainBanner->video_url }}" type="video/mp4">
            </video>
        @else
            <img src="{{ $mainBanner->image_url ?? asset('images/default-banner.jpg') }}" alt="Shop Banner">
        @endif
        <div class="banner-content">
            <h2>Equipment & Supplies</h2>
            <p>Premium gear for your fleet</p>
        </div>
    </div>
    @endif

    <!-- Search & Filter Section -->
    <div class="category-filter shadow-sm">
        <div class="row align-items-center">
            <div class="col-md-6">
                <form action="{{ route('company.shop.index') }}" method="GET" class="d-flex">
                    <input type="text" name="search" class="form-control" placeholder="Search products..." 
                           value="{{ request('search') }}" style="border-radius: 8px 0 0 8px;">
                    <button type="submit" class="btn btn-primary" style="background: #F97316; border-color: #F97316; border-radius: 0 8px 8px 0;">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            <div class="col-md-6 text-md-right mt-3 mt-md-0">
                <a href="{{ route('company.shop.cart') }}" class="btn btn-outline-primary">
                    <i class="fas fa-shopping-cart mr-2"></i>View Cart
                </a>
            </div>
        </div>
        
        <!-- Category Filters -->
        @if($categories->count() > 0)
        <div class="mt-3">
            <a href="{{ route('company.shop.index') }}" class="category-btn {{ !request('category') ? 'active' : '' }}">All</a>
            @foreach($categories as $category)
                <a href="{{ route('company.shop.index', ['category' => $category]) }}" 
                   class="category-btn {{ request('category') == $category ? 'active' : '' }}">
                    {{ $category }}
                </a>
            @endforeach
        </div>
        @endif
    </div>

    <!-- Products Grid -->
    <div class="row">
        @forelse($products as $product)
        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="product-card shadow-sm position-relative">
                <!-- Stock Badge -->
                @if($product->stock_quantity > 10)
                    <span class="stock-badge in-stock">In Stock</span>
                @elseif($product->stock_quantity > 0)
                    <span class="stock-badge low-stock">Low Stock ({{ $product->stock_quantity }})</span>
                @else
                    <span class="stock-badge out-of-stock">Out of Stock</span>
                @endif

                <!-- Product Image -->
                <div class="product-image">
                    @if(!empty($product->images) && is_array($product->images) && count($product->images) > 0)
                        <img src="{{ $product->images[0] }}" alt="{{ $product->name }}">
                    @else
                        <div class="text-center text-muted">
                            <i class="fas fa-image fa-3x mb-2"></i>
                            <p>No Image</p>
                        </div>
                    @endif
                </div>

                <!-- Product Info -->
                <div class="product-info">
                    <span class="text-muted small">{{ $product->category }}</span>
                    <h5 class="mt-1 mb-2" style="font-weight: 600;">{{ $product->name }}</h5>
                    <p class="text-muted small mb-3" style="height: 40px; overflow: hidden;">
                        {{ Str::limit($product->description, 60) }}
                    </p>
                    
                    <div class="product-price mb-3">
                        ₦{{ number_format($product->final_price, 2) }}
                        @if($product->has_discount)
                            <span class="original">₦{{ number_format($product->price, 2) }}</span>
                        @endif
                    </div>

                    <button class="add-to-cart-btn" 
                            onclick="addToCart({{ $product->id }}, '{{ $product->name }}')"
                            {{ $product->stock_quantity <= 0 ? 'disabled' : '' }}>
                        <i class="fas fa-cart-plus mr-2"></i>
                        {{ $product->stock_quantity > 0 ? 'Add to Cart' : 'Out of Stock' }}
                    </button>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-5">
            <div class="empty-state">
                <i class="fas fa-store fa-3x mb-3 text-muted"></i>
                <h4>No Products Available</h4>
                <p class="text-muted">Check back later for new equipment and supplies.</p>
            </div>
        </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center mt-4">
        {{ $products->appends(request()->query())->links() }}
    </div>
</div>
@endsection

@section('extra-js')
<script>
function addToCart(productId, productName) {
    // Disable button to prevent double-click
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';

    $.ajax({
        url: '{{ route("company.shop.cart.add") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            product_id: productId,
            quantity: 1
        },
        success: function(response) {
            if (response.success) {
                // Show success message
                toastr.success(productName + ' added to cart!');
                
                // Update cart count in header
                updateCartCount(response.cart_count);
            } else {
                toastr.error(response.message || 'Failed to add to cart');
            }
        },
        error: function(xhr) {
            let message = 'Failed to add to cart';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            toastr.error(message);
        },
        complete: function() {
            // Re-enable button
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-cart-plus mr-2"></i>Add to Cart';
        }
    });
}

function updateCartCount(count) {
    const badge = $('#header-cart-count');
    if (count > 0) {
        badge.text(count).show();
    } else {
        badge.hide();
    }
}
</script>
@endsection
