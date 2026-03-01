@extends('admin.layouts.web_header')

@section('content')

<style>
.product-gallery {
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.main-image {
    height: 450px;
    object-fit: cover;
    width: 100%;
}
.thumbnail-image {
    height: 80px;
    object-fit: cover;
    cursor: pointer;
    border: 2px solid transparent;
    border-radius: 8px;
    transition: all 0.3s ease;
}
.thumbnail-image:hover,
.thumbnail-image.active {
    border-color: #667eea;
}
.product-info-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.price-tag {
    font-size: 32px;
    font-weight: 700;
}
.feature-list li {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}
.feature-list li:last-child {
    border-bottom: none;
}
.related-product-card {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}
.related-product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}
.video-container {
    position: relative;
    padding-bottom: 56.25%;
    height: 0;
    overflow: hidden;
    border-radius: 12px;
}
.video-container iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}
</style>

<!-- Breadcrumb -->
<div class="bg-light py-3">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 bg-transparent">
                <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('shop.index') }}">Shop</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($product->name, 30) }}</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Product Details -->
<section class="py-5">
    <div class="container">
        <div class="row">
            <!-- Product Images -->
            <div class="col-lg-6 mb-4">
                <div class="product-gallery">
                    @if($product->images && count($product->images) > 0)
                        <img src="{{ $product->images[0] }}" id="main-image" class="main-image" alt="{{ $product->name }}">
                        
                        @if(count($product->images) > 1)
                        <div class="p-3 bg-white">
                            <div class="row">
                                @foreach($product->images as $index => $image)
                                <div class="col-3">
                                    <img src="{{ $image }}" class="thumbnail-image {{ $index == 0 ? 'active' : '' }}" 
                                         onclick="changeImage('{{ $image }}', this)" alt="Thumbnail {{ $index + 1 }}">
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    @else
                        <img src="{{ asset('images/default-product.png') }}" class="main-image" alt="{{ $product->name }}">
                    @endif
                </div>
                
                @if($product->video_url)
                <div class="mt-4">
                    <h5 class="mb-3">Product Video</h5>
                    <div class="video-container">
                        <iframe src="{{ $product->video_url }}" frameborder="0" allowfullscreen></iframe>
                    </div>
                </div>
                @endif
            </div>

            <!-- Product Info -->
            <div class="col-lg-6">
                <div class="product-info-card">
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge badge-info mr-2">{{ $product->category }}</span>
                        @if($product->is_featured)
                            <span class="badge badge-warning mr-2"><i class="fa fa-star"></i> Featured</span>
                        @endif
                    </div>
                    
                    <h1 class="font-weight-bold mb-3">{{ $product->name }}</h1>
                    
                    <div class="mb-4">
                        @if($product->has_discount)
                            <span class="price-tag text-success">${{ number_format($product->final_price, 2) }}</span>
                            <del class="text-muted ml-2" style="font-size: 24px;">${{ number_format($product->price, 2) }}</del>
                            <span class="badge badge-danger ml-2" style="font-size: 14px;">Save {{ $product->discount_percentage }}%</span>
                        @else
                            <span class="price-tag text-primary">${{ number_format($product->price, 2) }}</span>
                        @endif
                    </div>
                    
                    <div class="mb-4">
                        @if($product->stock_quantity > 0)
                            <span class="badge badge-success" style="font-size: 14px; padding: 8px 15px;">
                                <i class="fa fa-check-circle mr-1"></i> In Stock ({{ $product->stock_quantity }} available)
                            </span>
                        @else
                            <span class="badge badge-danger" style="font-size: 14px; padding: 8px 15px;">
                                <i class="fa fa-times-circle mr-1"></i> Out of Stock
                            </span>
                        @endif
                    </div>
                    
                    <hr class="my-4">
                    
                    <h5 class="mb-3">Description</h5>
                    <p class="text-muted mb-4" style="line-height: 1.8;">
                        {{ $product->description ?: 'No description available for this product.' }}
                    </p>
                    
                    <hr class="my-4">
                    
                    <h5 class="mb-3">Product Details</h5>
                    <ul class="feature-list list-unstyled">
                        <li class="d-flex justify-content-between">
                            <span class="text-muted">SKU</span>
                            <span class="font-weight-bold">{{ $product->sku }}</span>
                        </li>
                        <li class="d-flex justify-content-between">
                            <span class="text-muted">Category</span>
                            <span class="font-weight-bold">{{ $product->category }}</span>
                        </li>
                        <li class="d-flex justify-content-between">
                            <span class="text-muted">Target Audience</span>
                            <span class="font-weight-bold">{{ ucfirst($product->target_audience) }}</span>
                        </li>
                    </ul>
                    
                    <hr class="my-4">
                    
                    <div class="d-flex flex-wrap">
                        @if($product->stock_quantity > 0)
                            <a href="mailto:sales@example.com?subject=Pre-order: {{ urlencode($product->name) }}&body=I would like to pre-order {{ urlencode($product->name) }} (SKU: {{ $product->sku }})" 
                               class="btn btn-success btn-lg rounded-pill px-5 mr-3 mb-2">
                                <i class="fa fa-envelope mr-2"></i>Contact to Pre-order
                            </a>
                        @else
                            <button class="btn btn-secondary btn-lg rounded-pill px-5 mr-3 mb-2" disabled>
                                <i class="fa fa-times-circle mr-2"></i>Out of Stock
                            </button>
                        @endif
                        
                        <a href="{{ route('shop.index') }}" class="btn btn-outline-primary btn-lg rounded-pill px-4 mb-2">
                            <i class="fa fa-arrow-left mr-2"></i>Back to Shop
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Related Products -->
@if($relatedProducts->count() > 0)
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="font-weight-bold mb-5 text-center">Related Products</h2>
        <div class="row">
            @foreach($relatedProducts as $related)
            <div class="col-md-3 mb-4">
                <div class="related-product-card bg-white">
                    <div class="position-relative">
                        <img src="{{ $related->first_image }}" style="height: 200px; object-fit: cover; width: 100%;" alt="{{ $related->name }}">
                        @if($related->has_discount)
                            <span class="badge badge-danger" style="position: absolute; top: 10px; right: 10px;">
                                -{{ $related->discount_percentage }}%
                            </span>
                        @endif
                    </div>
                    <div class="p-3">
                        <span class="badge badge-secondary mb-2">{{ $related->category }}</span>
                        <h6 class="font-weight-bold mb-2">{{ Str::limit($related->name, 30) }}</h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-success font-weight-bold">${{ number_format($related->final_price, 2) }}</span>
                            <a href="{{ route('shop.show', $related->id) }}" class="btn btn-sm btn-primary rounded-pill">View</a>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- More Products -->
@if($moreProducts->count() > 0)
<section class="py-5">
    <div class="container">
        <h2 class="font-weight-bold mb-5 text-center">You May Also Like</h2>
        <div class="row">
            @foreach($moreProducts as $more)
            <div class="col-md-3 mb-4">
                <div class="related-product-card bg-white">
                    <div class="position-relative">
                        <img src="{{ $more->first_image }}" style="height: 200px; object-fit: cover; width: 100%;" alt="{{ $more->name }}">
                        @if($more->has_discount)
                            <span class="badge badge-danger" style="position: absolute; top: 10px; right: 10px;">
                                -{{ $more->discount_percentage }}%
                            </span>
                        @endif
                    </div>
                    <div class="p-3">
                        <span class="badge badge-info mb-2">{{ $more->category }}</span>
                        <h6 class="font-weight-bold mb-2">{{ Str::limit($more->name, 30) }}</h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-success font-weight-bold">${{ number_format($more->final_price, 2) }}</span>
                            <a href="{{ route('shop.show', $more->id) }}" class="btn btn-sm btn-outline-primary rounded-pill">View</a>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@extends('admin.layouts.web_footer')

<script>
function changeImage(src, element) {
    document.getElementById('main-image').src = src;
    document.querySelectorAll('.thumbnail-image').forEach(img => img.classList.remove('active'));
    element.classList.add('active');
}
</script>

@endsection