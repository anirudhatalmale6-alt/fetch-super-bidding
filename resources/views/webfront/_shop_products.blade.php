@forelse($products as $product)
<div class="col-md-4 mb-4">
    <div class="product-card">
        <div class="product-image-wrapper">
            <img src="{{ $product->first_image }}" alt="{{ $product->name }}">
            @if($product->has_discount)
                <span class="product-badge badge-discount">-{{ $product->discount_percentage }}%</span>
            @endif
            @if($product->is_featured)
                <span class="product-badge badge-featured"><i class="fa fa-star"></i></span>
            @endif
            @if($product->stock_quantity <= 0)
                <div class="position-absolute w-100 h-100 d-flex align-items-center justify-content-center" 
                     style="background: rgba(0,0,0,0.7); top: 0; left: 0;">
                    <span class="badge badge-secondary" style="font-size: 14px;">Out of Stock</span>
                </div>
            @endif
        </div>
        <div class="p-4">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge badge-info">{{ $product->category }}</span>
                @if($product->stock_quantity > 0)
                    <small class="text-success"><i class="fa fa-check-circle"></i> In Stock</small>
                @else
                    <small class="text-danger"><i class="fa fa-times-circle"></i> Out of Stock</small>
                @endif
            </div>
            <h5 class="font-weight-bold mb-2">{{ Str::limit($product->name, 35) }}</h5>
            <p class="text-muted small mb-3" style="height: 40px; overflow: hidden;">
                {{ Str::limit(strip_tags($product->description), 80) }}
            </p>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    @if($product->has_discount)
                        <del class="text-muted small">${{ number_format($product->price, 2) }}</del>
                        <span class="text-success font-weight-bold ml-2" style="font-size: 18px;">${{ number_format($product->final_price, 2) }}</span>
                    @else
                        <span class="font-weight-bold" style="font-size: 18px;">${{ number_format($product->price, 2) }}</span>
                    @endif
                </div>
                <a href="{{ route('shop.show', $product->id) }}" class="btn btn-primary btn-sm rounded-pill px-4">
                    View
                </a>
            </div>
        </div>
    </div>
</div>
@empty
<div class="col-12 text-center py-5">
    <i class="fa fa-shopping-basket fa-4x text-muted mb-4"></i>
    <h3 class="text-muted">No products found</h3>
    <p class="text-muted">Try adjusting your filters or search criteria</p>
</div>
@endforelse

<style>
.product-card {
    border: none;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    background: white;
    height: 100%;
}
.product-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.2);
}
.product-image-wrapper {
    position: relative;
    overflow: hidden;
    height: 200px;
}
.product-image-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}
.product-card:hover .product-image-wrapper img {
    transform: scale(1.1);
}
.product-badge {
    position: absolute;
    top: 15px;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.badge-discount {
    right: 15px;
    background: #ff4757;
    color: white;
}
.badge-featured {
    left: 15px;
    background: #ffa502;
    color: white;
}
</style>