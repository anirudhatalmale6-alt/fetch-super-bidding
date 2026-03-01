<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<style>
/* ============================================
   PREMIUM SHOP STYLES - Modern Ecommerce Grid
   ============================================ */

:root {
    --primary-color: #4F46E5;
    --primary-dark: #4338CA;
    --secondary-color: #F59E0B;
    --accent-color: #10B981;
    --dark-bg: #0F172A;
    --light-bg: #F8FAFC;
    --card-shadow: 0 10px 40px rgba(0,0,0,0.08);
    --hover-shadow: 0 20px 60px rgba(0,0,0,0.15);
}

/* Hero Section */
.shop-hero-section {
    position: relative;
    min-height: 500px;
    overflow: hidden;
    background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
    margin-top: 70px; /* Account for fixed navbar */
}

.shop-hero-slider-wrap {
    position: relative;
    width: 100%;
    height: 500px;
    overflow: hidden;
}

.shop-hero-slide {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    transform: scale(1.05);
}

.shop-hero-slide.active { opacity: 1; transform: scale(1); }
.shop-hero-slide video, .shop-hero-slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.shop-hero-slide-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, rgba(15,23,42,0.85) 0%, rgba(15,23,42,0.3) 60%, transparent 100%);
    display: flex;
    align-items: center;
    padding: 0 8%;
}

.shop-hero-slide-caption {
    color: #fff;
    max-width: 600px;
    animation: slideInLeft 0.8s ease-out;
}

@keyframes slideInLeft {
    from { opacity: 0; transform: translateX(-30px); }
    to { opacity: 1; transform: translateX(0); }
}

.shop-hero-slide-caption h1 {
    font-size: 3rem;
    font-weight: 800;
    line-height: 1.1;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, #fff 0%, #A5B4FC 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.shop-hero-slide-caption p {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 1.5rem;
}

.shop-hero-slide-caption .btn {
    padding: 14px 36px;
    font-weight: 600;
    border-radius: 50px;
    box-shadow: 0 4px 20px rgba(79, 70, 229, 0.4);
}

.shop-hero-prev, .shop-hero-next {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 20;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    color: #fff;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.shop-hero-prev { left: 24px; }
.shop-hero-next { right: 24px; }
.shop-hero-prev:hover, .shop-hero-next:hover {
    background: var(--primary-color);
    transform: translateY(-50%) scale(1.1);
}

.shop-hero-dots {
    position: absolute;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 10px;
    z-index: 20;
}

.shop-hero-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    cursor: pointer;
    transition: all 0.3s ease;
}

.shop-hero-dot.active {
    background: var(--primary-color);
    transform: scale(1.4);
}

/* Default Banner */
.shop-hero-default {
    min-height: 500px;
    background: linear-gradient(135deg, #0F172A 0%, #4F46E5 50%, #7C3AED 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.shop-hero-default::before {
    content: '';
    position: absolute;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    top: -100px;
    right: -100px;
    animation: pulse 4s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.2); opacity: 0.8; }
}

.shop-hero-default-content {
    text-align: center;
    color: #fff;
    z-index: 2;
}

.shop-hero-default-content h1 {
    font-size: 3rem;
    font-weight: 800;
    margin-bottom: 1rem;
}

.shop-hero-default-content p {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 2rem;
}

/* Search & Filter Bar */
.shop-toolbar {
    background: #fff;
    padding: 20px 0;
    border-bottom: 1px solid #E5E7EB;
    position: sticky;
    top: 70px;
    z-index: 100;
}

.shop-toolbar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
}

.shop-search-mini {
    flex: 1;
    max-width: 400px;
    position: relative;
}

.shop-search-mini input {
    width: 100%;
    padding: 12px 20px 12px 45px;
    border: 2px solid #E5E7EB;
    border-radius: 50px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.shop-search-mini input:focus {
    outline: none;
    border-color: var(--primary-color);
}

.shop-search-mini i {
    position: absolute;
    left: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: #9CA3AF;
}

.shop-filter-group-mini {
    display: flex;
    align-items: center;
    gap: 15px;
}

.shop-filter-mini {
    padding: 10px 20px;
    border: 2px solid #E5E7EB;
    border-radius: 50px;
    background: #fff;
    color: #6B7280;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.shop-filter-mini:hover, .shop-filter-mini.active {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.shop-view-toggle {
    display: flex;
    gap: 8px;
}

.shop-view-btn {
    width: 40px;
    height: 40px;
    border: 2px solid #E5E7EB;
    border-radius: 10px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #6B7280;
}

.shop-view-btn:hover, .shop-view-btn.active {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: rgba(79, 70, 229, 0.05);
}

/* Products Grid Section */
.shop-grid-section {
    padding: 50px 0;
    background: var(--light-bg);
    min-height: 60vh;
}

.shop-grid-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
    flex-wrap: wrap;
    gap: 20px;
}

.shop-grid-header h2 {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1F2937;
}

.shop-result-count {
    color: #6B7280;
    font-size: 0.95rem;
}

/* Product Grid - Masonry Style */
.shop-products-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
}

.shop-products-grid.grid-3 {
    grid-template-columns: repeat(3, 1fr);
}

.shop-products-grid.grid-2 {
    grid-template-columns: repeat(2, 1fr);
}

/* Product Card - Modern Design */
.shop-grid-card {
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--card-shadow);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.shop-grid-card:hover {
    transform: translateY(-10px);
    box-shadow: var(--hover-shadow);
}

.shop-grid-card-img {
    position: relative;
    height: 220px;
    overflow: hidden;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

.shop-grid-card-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.shop-grid-card:hover .shop-grid-card-img img {
    transform: scale(1.08);
}

.shop-grid-card-badges {
    position: absolute;
    top: 12px;
    left: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.shop-grid-card-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
}

.shop-grid-card-badge.sale {
    background: #EF4444;
    color: #fff;
}

.shop-grid-card-badge.new {
    background: var(--accent-color);
    color: #fff;
}

.shop-grid-card-badge.hot {
    background: var(--secondary-color);
    color: #fff;
}

.shop-grid-card-actions {
    position: absolute;
    top: 12px;
    right: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    opacity: 0;
    transform: translateX(15px);
    transition: all 0.3s ease;
}

.shop-grid-card:hover .shop-grid-card-actions {
    opacity: 1;
    transform: translateX(0);
}

.shop-grid-card-action {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    color: #6B7280;
    font-size: 0.9rem;
}

.shop-grid-card-action:hover {
    background: var(--primary-color);
    color: #fff;
    transform: scale(1.1);
}

.shop-grid-card-body {
    padding: 20px;
}

.shop-grid-card-cat {
    font-size: 0.7rem;
    color: var(--primary-color);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 6px;
}

.shop-grid-card-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1F2937;
    margin-bottom: 8px;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.shop-grid-card-desc {
    font-size: 0.85rem;
    color: #6B7280;
    margin-bottom: 15px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.shop-grid-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 1px solid #F3F4F6;
}

.shop-grid-card-price {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--primary-color);
}

.shop-grid-card-price .old-price {
    font-size: 0.85rem;
    color: #9CA3AF;
    text-decoration: line-through;
    margin-right: 6px;
}

.shop-grid-card-btn {
    padding: 8px 18px;
    background: var(--primary-color);
    color: #fff;
    border: none;
    border-radius: 20px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.85rem;
}

.shop-grid-card-btn:hover {
    background: var(--primary-dark);
    transform: scale(1.05);
}

/* Featured Section - Dark Theme */
.shop-featured-section {
    padding: 80px 0;
    background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
    position: relative;
}

.shop-featured-section::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    top: 0;
    left: 0;
}

.shop-featured-section .shop-grid-header h2 {
    color: #fff;
}

.shop-featured-section .shop-result-count {
    color: rgba(255,255,255,0.7);
}

/* New Arrivals */
.shop-arrivals-section {
    padding: 80px 0;
    background: #fff;
}

/* Category Showcase */
.shop-categories-section {
    padding: 80px 0;
    background: var(--light-bg);
}

.shop-category-card {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.4s ease;
}

.shop-category-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--hover-shadow);
}

.shop-category-card img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.shop-category-card::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 60%);
}

.shop-category-card-content {
    position: relative;
    z-index: 2;
    text-align: center;
    color: #fff;
}

.shop-category-card-content h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.shop-category-card-content p {
    font-size: 0.9rem;
    opacity: 0.8;
}

/* Pagination */
.shop-pagination-wrap {
    display: flex;
    justify-content: center;
    margin-top: 50px;
}

.shop-pagination {
    display: flex;
    gap: 8px;
}

.shop-pagination a {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-weight: 600;
    background: #fff;
    color: #374151;
    box-shadow: var(--card-shadow);
    transition: all 0.3s ease;
}

.shop-pagination a:hover, .shop-pagination a.active {
    background: var(--primary-color);
    color: #fff;
}

/* Empty State */
.shop-empty-state {
    text-align: center;
    padding: 80px 20px;
    grid-column: 1 / -1;
}

.shop-empty-state i {
    font-size: 4rem;
    color: #D1D5DB;
    margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 1200px) {
    .shop-products-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 992px) {
    .shop-products-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .shop-hero-slide-caption h1, .shop-hero-default-content h1 {
        font-size: 2rem;
    }
    
    .shop-hero-section, .shop-hero-slider-wrap, .shop-hero-default {
        min-height: 350px;
    }
    
    .shop-products-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .shop-grid-card-img {
        height: 160px;
    }
    
    .shop-toolbar-inner {
        flex-direction: column;
        align-items: stretch;
    }
    
    .shop-search-mini {
        max-width: 100%;
    }
    
    .shop-filter-group-mini {
        justify-content: space-between;
    }
}
</style>

{{-- Hero Slider --}}
@if($shopBanners->isNotEmpty())
<div class="shop-hero-section">
    <div class="shop-hero-slider-wrap" id="shopHeroSlider">
        <button class="shop-hero-prev" onclick="shopHeroSlide(-1)"><i class="fas fa-chevron-left"></i></button>
        <button class="shop-hero-next" onclick="shopHeroSlide(1)"><i class="fas fa-chevron-right"></i></button>

        @foreach($shopBanners as $i => $banner)
        <div class="shop-hero-slide {{ $i === 0 ? 'active' : '' }}">
            @if($banner->video_url)
                <video autoplay muted loop playsinline poster="{{ $banner->image_url }}">
                    <source src="{{ $banner->video_url }}" type="video/mp4">
                </video>
            @else
                <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}">
            @endif
            <div class="shop-hero-slide-overlay">
                <div class="shop-hero-slide-caption">
                    @if($banner->title)
                        <h1 data-aos="fade-up">{{ $banner->title }}</h1>
                    @endif
                    @if($banner->description)
                        <p data-aos="fade-up" data-aos-delay="200">{{ $banner->description }}</p>
                    @endif
                    @if($banner->button_text && $banner->button_link)
                        <a href="{{ $banner->button_link }}" class="btn btn-primary" data-aos="fade-up" data-aos-delay="300">
                            {{ $banner->button_text }} <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    @endif
                </div>
            </div>
        </div>
        @endforeach

        <div class="shop-hero-dots">
            @foreach($shopBanners as $i => $banner)
                <span class="shop-hero-dot {{ $i === 0 ? 'active' : '' }}" onclick="shopHeroGoTo({{ $i }})"></span>
            @endforeach
        </div>
    </div>
</div>
@else
{{-- Default Banner --}}
<div class="shop-hero-section">
    <div class="shop-hero-default">
        <div class="shop-hero-default-content">
            <h1 data-aos="fade-up">Equipment Store</h1>
            <p data-aos="fade-up" data-aos-delay="200">Premium gear for drivers and fleet owners</p>
            <div data-aos="fade-up" data-aos-delay="400">
                <a href="#products" class="btn btn-primary btn-lg mr-3"><i class="fas fa-shopping-bag mr-2"></i>Shop Now</a>
                <a href="#featured" class="btn btn-outline-light btn-lg"><i class="fas fa-star mr-2"></i>Featured</a>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Search & Filter Toolbar --}}
<div class="shop-toolbar">
    <div class="container">
        <div class="shop-toolbar-inner">
            <div class="shop-search-mini">
                <i class="fas fa-search"></i>
                <input type="text" id="search-input" placeholder="Search products...">
            </div>
            
            <div class="shop-filter-group-mini">
                <button class="shop-filter-mini active" data-category="">All</button>
                @foreach($categories as $cat)
                    <button class="shop-filter-mini" data-category="{{ $cat }}">{{ $cat }}</button>
                @endforeach
            </div>
            
            <div class="shop-view-toggle">
                <button class="shop-view-btn active" data-cols="4" title="4 Columns"><i class="fas fa-th"></i></button>
                <button class="shop-view-btn" data-cols="3" title="3 Columns"><i class="fas fa-th-large"></i></button>
            </div>
        </div>
    </div>
</div>

{{-- Main Products Grid --}}
<section class="shop-grid-section" id="products">
    <div class="container">
        <div class="shop-grid-header">
            <h2>All Products <span class="shop-result-count">({{ $allProducts->total() }} items)</span></h2>
            <select class="shop-filter-mini" id="sort-select" style="min-width: 180px;">
                <option value="latest">Sort: Latest</option>
                <option value="price_low">Price: Low to High</option>
                <option value="price_high">Price: High to Low</option>
                <option value="name_asc">Name: A-Z</option>
                <option value="name_desc">Name: Z-A</option>
            </select>
        </div>

        <div class="shop-products-grid" id="products-container">
            @forelse($allProducts as $product)
            <div class="shop-grid-card" data-aos="fade-up" data-aos-delay="{{ $loop->index * 50 }}">
                <div class="shop-grid-card-img">
                    <img src="{{ $product->first_image }}" alt="{{ $product->name }}">
                    
                    <div class="shop-grid-card-badges">
                        @if($product->has_discount)
                            <span class="shop-grid-card-badge sale">-{{ $product->discount_percentage }}%</span>
                        @endif
                        @if($product->is_featured)
                            <span class="shop-grid-card-badge hot">Hot</span>
                        @endif
                    </div>
                    
                    <div class="shop-grid-card-actions">
                        <button class="shop-grid-card-action" title="Quick View"><i class="fas fa-eye"></i></button>
                        <button class="shop-grid-card-action" title="Add to Wishlist"><i class="fas fa-heart"></i></button>
                    </div>
                </div>
                
                <div class="shop-grid-card-body">
                    <div class="shop-grid-card-cat">{{ $product->category ?? 'General' }}</div>
                    <h3 class="shop-grid-card-title">{{ Str::limit($product->name, 45) }}</h3>
                    <p class="shop-grid-card-desc">{{ Str::limit(strip_tags($product->description), 60) }}</p>
                    
                    <div class="shop-grid-card-footer">
                        <div class="shop-grid-card-price">
                            @if($product->has_discount)
                                <span class="old-price">${{ number_format($product->price, 2) }}</span>
                            @endif
                            ${{ number_format($product->final_price, 2) }}
                        </div>
                        <button class="shop-grid-card-btn" onclick="addToCart({{ $product->id }})">
                            <i class="fas fa-cart-plus mr-1"></i> Add
                        </button>
                    </div>
                </div>
            </div>
            @empty
            <div class="shop-empty-state">
                <i class="fas fa-shopping-bag"></i>
                <h3>No products found</h3>
                <p>Try adjusting your search or filters</p>
            </div>
            @endforelse
        </div>

        <div class="shop-pagination-wrap">
            {{ $allProducts->links() }}
        </div>
    </div>
</section>

{{-- Featured Products --}}
@if($featuredProducts->count() > 0)
<section class="shop-featured-section" id="featured">
    <div class="container position-relative">
        <div class="shop-grid-header">
            <h2><i class="fas fa-star mr-2"></i>Featured Products</h2>
        </div>
        
        <div class="shop-products-grid">
            @foreach($featuredProducts as $product)
            <div class="shop-grid-card" data-aos="fade-up" data-aos-delay="{{ $loop->index * 50 }}">
                <div class="shop-grid-card-img">
                    <img src="{{ $product->first_image }}" alt="{{ $product->name }}">
                    <div class="shop-grid-card-badges">
                        <span class="shop-grid-card-badge hot"><i class="fas fa-star mr-1"></i>Featured</span>
                    </div>
                    <div class="shop-grid-card-actions">
                        <button class="shop-grid-card-action"><i class="fas fa-eye"></i></button>
                        <button class="shop-grid-card-action"><i class="fas fa-heart"></i></button>
                    </div>
                </div>
                <div class="shop-grid-card-body">
                    <div class="shop-grid-card-cat">{{ $product->category ?? 'General' }}</div>
                    <h3 class="shop-grid-card-title">{{ Str::limit($product->name, 40) }}</h3>
                    <div class="shop-grid-card-footer">
                        <div class="shop-grid-card-price">
                            @if($product->has_discount)
                                <span class="old-price">${{ number_format($product->price, 2) }}</span>
                            @endif
                            ${{ number_format($product->final_price, 2) }}
                        </div>
                        <button class="shop-grid-card-btn" onclick="addToCart({{ $product->id }})">
                            <i class="fas fa-cart-plus mr-1"></i> Add
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- New Arrivals --}}
@if($newArrivals->count() > 0)
<section class="shop-arrivals-section">
    <div class="container">
        <div class="shop-grid-header">
            <h2><i class="fas fa-bolt mr-2" style="color: var(--accent-color);"></i>New Arrivals</h2>
        </div>
        
        <div class="shop-products-grid">
            @foreach($newArrivals as $product)
            <div class="shop-grid-card" data-aos="fade-up" data-aos-delay="{{ $loop->index * 50 }}">
                <div class="shop-grid-card-img">
                    <img src="{{ $product->first_image }}" alt="{{ $product->name }}">
                    <div class="shop-grid-card-badges">
                        <span class="shop-grid-card-badge new">New</span>
                        @if($product->has_discount)
                            <span class="shop-grid-card-badge sale">-{{ $product->discount_percentage }}%</span>
                        @endif
                    </div>
                    <div class="shop-grid-card-actions">
                        <button class="shop-grid-card-action"><i class="fas fa-eye"></i></button>
                        <button class="shop-grid-card-action"><i class="fas fa-heart"></i></button>
                    </div>
                </div>
                <div class="shop-grid-card-body">
                    <div class="shop-grid-card-cat">{{ $product->category ?? 'General' }}</div>
                    <h3 class="shop-grid-card-title">{{ Str::limit($product->name, 40) }}</h3>
                    <div class="shop-grid-card-footer">
                        <div class="shop-grid-card-price">${{ number_format($product->final_price, 2) }}</div>
                        <button class="shop-grid-card-btn" onclick="addToCart({{ $product->id }})">
                            <i class="fas fa-cart-plus mr-1"></i> Add
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    // Initialize AOS
    AOS.init({ duration: 600, once: true, offset: 100 });

    // Hero Slider
    (function () {
        let cur = 0;
        const slides = document.querySelectorAll('#shopHeroSlider .shop-hero-slide');
        const dots = document.querySelectorAll('#shopHeroSlider .shop-hero-dot');
        let timer;

        if (slides.length > 0) {
            window.shopHeroGoTo = function (n) {
                slides[cur]?.classList.remove('active');
                dots[cur]?.classList.remove('active');
                cur = ((n % slides.length) + slides.length) % slides.length;
                slides[cur]?.classList.add('active');
                dots[cur]?.classList.add('active');
            };
            window.shopHeroSlide = function (dir) {
                clearInterval(timer);
                shopHeroGoTo(cur + dir);
                timer = setInterval(() => shopHeroGoTo(cur + 1), 5500);
            };
            timer = setInterval(() => shopHeroGoTo(cur + 1), 5500);
        }
    })();

    // Grid View Toggle
    document.querySelectorAll('.shop-view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.shop-view-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const cols = this.dataset.cols;
            document.querySelectorAll('.shop-products-grid').forEach(grid => {
                grid.className = 'shop-products-grid grid-' + cols;
            });
        });
    });

    // Filter & Search
    let currentCategory = '', currentSort = 'latest', searchQuery = '', searchTimeout;

    document.querySelectorAll('.shop-filter-mini[data-category]').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.shop-filter-mini[data-category]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentCategory = this.dataset.category;
            fetchProducts();
        });
    });

    document.getElementById('sort-select')?.addEventListener('change', function() {
        currentSort = this.value;
        fetchProducts();
    });

    document.getElementById('search-input')?.addEventListener('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchQuery = this.value;
            fetchProducts();
        }, 500);
    });

    function fetchProducts() {
        fetch('{{ route("shop.fetch") }}?category=' + currentCategory + '&sort=' + currentSort + '&search=' + searchQuery)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('products-container').innerHTML = data.html;
                    AOS.refresh();
                }
            });
    }

    function addToCart(id) {
        alert('Product added to cart!');
    }
</script>

@extends('admin.layouts.web_footer')
