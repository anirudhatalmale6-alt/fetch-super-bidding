@forelse($products as $product)
<div class="col-md-4 col-lg-3 mb-4">
    <div class="product-card card h-100" style="border-radius: 10px; overflow: hidden; 
         box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.3s;">
        <div class="position-relative">
            <img src="{{ $product->first_image }}" class="card-img-top" 
                 alt="{{ $product->name }}" style="height: 200px; object-fit: cover;">
            @if($product->has_discount)
                <span class="badge badge-danger" style="position: absolute; top: 10px; right: 10px; font-size: 14px;">
                    -{{ $product->discount_percentage }}%
                </span>
            @endif
            @if($product->is_featured)
                <span class="badge badge-warning" style="position: absolute; top: 10px; left: 10px;">
                    <i class="fa fa-star"></i> Featured
                </span>
            @endif
            @if($product->stock_quantity <= 0)
                <div class="out-of-stock-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
                     background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center;">
                    <span class="badge badge-secondary" style="font-size: 16px;">Out of Stock</span>
                </div>
            @endif
        </div>
        <div class="card-body p-3">
            <span class="badge badge-info mb-2">{{ $product->category }}</span>
            <h5 class="card-title mb-2" style="font-size: 16px; font-weight: 600;">
                {{ Str::limit($product->name, 40) }}
            </h5>
            <p class="text-muted mb-3" style="font-size: 13px; height: 40px; overflow: hidden;">
                {{ Str::limit(strip_tags($product->description), 60) }}
            </p>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    @if($product->has_discount)
                        <del class="text-muted" style="font-size: 14px;">${{ number_format($product->price, 2) }}</del><br>
                        <span class="text-success font-weight-bold" style="font-size: 18px;">${{ number_format($product->final_price, 2) }}</span>
                    @else
                        <span class="font-weight-bold" style="font-size: 18px;">${{ number_format($product->price, 2) }}</span>
                    @endif
                </div>
                <span class="text-muted" style="font-size: 12px;">
                    @if($product->stock_quantity > 0)
                        <i class="fa fa-check-circle text-success"></i> In Stock
                    @else
                        <i class="fa fa-times-circle text-danger"></i> Out of Stock
                    @endif
                </span>
            </div>
            <a href="{{ route('company.store.show', $product->id) }}" class="btn btn-primary btn-block">
                <i class="fa fa-eye mr-2"></i>View Details
            </a>
        </div>
    </div>
</div>
@empty
<div class="col-12 text-center py-5">
    <i class="fa fa-shopping-basket fa-3x text-muted mb-3"></i>
    <h4 class="text-muted">No products found</h4>
    <p class="text-muted">Check back later for new items!</p>
</div>
@endforelse

<style>
.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}
</style>