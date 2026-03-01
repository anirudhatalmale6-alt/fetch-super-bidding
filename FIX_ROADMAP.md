# FIX ROADMAP
## FETCH Platform — Structured Repair Plan

**Date:** February 27, 2026  
**Status:** Ready for Implementation  
**Priority:** Critical to Low

---

## PRIORITY MATRIX

| Priority | Issues | Effort | Impact |
|----------|--------|--------|--------|
| **P0 - Critical** | Cart stub, Product filter | 2 hours | High |
| **P1 - High** | Shop views, Checkout wiring | 1 week | High |
| **P2 - Medium** | Public shop, Bidding UI | 1 week | Medium |
| **P3 - Low** | Enhancements, Polish | Ongoing | Low |

---

## P0 - CRITICAL FIXES (Do First)

### Fix 1: Product Audience Filter
**File:** `app/Http/Controllers/Api/V1/Shop/ProductController.php`  
**Line:** 19-21  
**Issue:** Uses 'companies' instead of 'company'

**Current Code:**
```php
->where(function ($q) {
    $q->where('target_audience', 'all')
      ->orWhere('target_audience', 'companies');  // WRONG
})
```

**Fix:**
```php
->where(function ($q) {
    $q->where('target_audience', 'all')
      ->orWhere('target_audience', 'company');    // CORRECT
})
```

**Also in:** Line 88-91 (featured method)

**Risk:** LOW - Simple string fix  
**Dependencies:** None  
**Testing:** Verify products appear for companies

---

### Fix 2: CompanyShopController addToCart Stub
**File:** `app/Http/Controllers/Web/Company/CompanyShopController.php`  
**Line:** 100-113  
**Issue:** Method returns success but does nothing

**Current Code:**
```php
public function addToCart(Request $request)
{
    $company = $this->getAuthenticatedCompany();
    if (!$company) {
        return response()->json(['success' => false, 'message' => 'Company not found'], 403);
    }

    // TODO: Implement cart functionality
    // For now, return success
    return response()->json([
        'success' => true, 
        'message' => 'Added to cart successfully'
    ]);
}
```

**Fix:** Integrate with Cart API
```php
public function addToCart(Request $request)
{
    $company = $this->getAuthenticatedCompany();
    if (!$company) {
        return response()->json(['success' => false, 'message' => 'Company not found'], 403);
    }

    $validated = $request->validate([
        'product_id' => 'required|exists:products,id',
        'quantity' => 'required|integer|min:1',
    ]);

    $product = Product::findOrFail($validated['product_id']);
    
    if (!$product->status) {
        return response()->json(['success' => false, 'message' => 'Product not available'], 400);
    }

    // Check if item already in cart
    $cartItem = Cart::forCompany($company->id)
        ->where('product_id', $product->id)
        ->first();

    if ($cartItem) {
        $cartItem->update(['quantity' => $cartItem->quantity + $validated['quantity']]);
    } else {
        Cart::create([
            'company_id' => $company->id,
            'user_id' => auth()->id(),
            'product_id' => $product->id,
            'quantity' => $validated['quantity'],
            'unit_price' => $product->final_price,
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Added to cart successfully',
        'cart_count' => Cart::getCartCount($company->id),
    ]);
}
```

**Risk:** LOW - Uses existing Cart model  
**Dependencies:** Cart model exists  
**Testing:** Add item to cart, verify in database

---

## P1 - HIGH PRIORITY FIXES

### Fix 3: Create Company Shop Index View
**File:** `resources/views/company/shop/index.blade.php` (NEW)  
**Purpose:** Product grid for company shop

**Features Needed:**
- Banner/slider at top (from Admin)
- Product grid with cards
- Filter by category
- Search functionality
- Add to cart buttons

**Template Structure:**
```blade
@extends('company.layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Banner Slider -->
    <div class="shop-banner mb-4">
        @include('company.shop.partials.banner')
    </div>
    
    <!-- Filters -->
    <div class="shop-filters mb-4">
        <!-- Category filter, search -->
    </div>
    
    <!-- Product Grid -->
    <div class="product-grid row">
        @foreach($products as $product)
            @include('company.shop.partials.product-card', ['product' => $product])
        @endforeach
    </div>
    
    <!-- Pagination -->
    {{ $products->links() }}
</div>
@endsection
```

**Risk:** MEDIUM - New view creation  
**Dependencies:** Product model, Banner model  
**Testing:** Visual check, add to cart test

---

### Fix 4: Create Product Card Partial
**File:** `resources/views/company/shop/partials/product-card.blade.php` (NEW)

**Features:**
- Product image
- Name and description
- Price (with discount if applicable)
- Add to cart button
- Stock indicator

**Risk:** LOW - UI component  
**Dependencies:** Fix 3  
**Testing:** Visual rendering, responsive check

---

### Fix 5: Create Cart View
**File:** `resources/views/company/shop/cart.blade.php` (NEW)  
**Purpose:** Shopping cart display and management

**Features Needed:**
- Cart items table
- Quantity adjusters
- Remove item buttons
- Subtotal calculation
- Proceed to checkout button
- Empty cart state

**Risk:** MEDIUM - New view  
**Dependencies:** Cart API  
**Testing:** Modify quantities, remove items, verify totals

---

### Fix 6: Create Checkout View
**File:** `resources/views/company/shop/checkout.blade.php` (NEW)  
**Purpose:** Checkout process

**Features Needed:**
- Order summary (items, subtotal)
- Delivery options (metro/interstate)
- Delivery address form
- Payment method selection
- Place order button

**Risk:** MEDIUM - Complex form  
**Dependencies:** Delivery options API  
**Testing:** Complete checkout flow

---

### Fix 7: Wire Up Cart in Layout
**File:** `resources/views/company/layouts/app.blade.php`  
**Line:** Around 122-128

**Current:** Cart icon with hardcoded check
**Fix:** Live cart count from database

```php
@php
$cartCount = auth()->check() && auth()->user()->truckingCompany 
    ? \App\Models\Cart::getCartCount(auth()->user()->truckingCompany->id) 
    : 0;
@endphp
```

**Risk:** LOW - Query addition  
**Dependencies:** Fix 2  
**Testing:** Verify count updates

---

## P2 - MEDIUM PRIORITY FIXES

### Fix 8: Public Shop Controller
**File:** `app/Http/Controllers/Web/ShopController.php` (NEW)  
**Purpose:** Public-facing shop for website

**Methods:**
- `index()` - Product listing
- `show($id)` - Product detail
- `cart()` - Cart for guests (session-based)
- `checkout()` - Guest checkout

**Risk:** MEDIUM - New controller  
**Dependencies:** Product model  
**Testing:** Guest browsing, cart persistence

---

### Fix 9: Public Shop Routes
**File:** `routes/web.php`  
**Add:**
```php
// Public Shop Routes
Route::get('/shop', 'ShopController@index')->name('shop.index');
Route::get('/shop/product/{slug}', 'ShopController@show')->name('shop.product');
Route::get('/shop/cart', 'ShopController@cart')->name('shop.cart');
Route::get('/shop/checkout', 'ShopController@checkout')->name('shop.checkout');
```

**Risk:** LOW - Route definitions  
**Dependencies:** Fix 8  
**Testing:** Route accessibility

---

### Fix 10: Public Shop Views
**Files:**
- `resources/views/shop/index.blade.php`
- `resources/views/shop/show.blade.php`
- `resources/views/shop/cart.blade.php`

**Risk:** MEDIUM - Multiple views  
**Dependencies:** Fix 8, 9  
**Testing:** Full guest browsing flow

---

### Fix 11: Company Bidding Web Interface
**File:** `app/Http/Controllers/Web/Company/CompanyBidController.php` (ENHANCE)

**Current Methods:**
- `index()` - Basic
- `available()` - Stub
- `create()` - Stub

**Enhancement:** Full implementation
- Fetch available bid requests
- Display bid form
- Handle bid submission
- Show bid history

**Risk:** MEDIUM - API integration  
**Dependencies:** Interstate bidding API  
**Testing:** Place bid, verify in system

---

### Fix 12: Bidding Views
**Files:**
- `resources/views/company/bids/index.blade.php`
- `resources/views/company/bids/available.blade.php`
- `resources/views/company/bids/create.blade.php`

**Features:**
- List of available interstate requests
- Bid submission form
- Bid history table

**Risk:** MEDIUM - New views  
**Dependencies:** Fix 11  
**Testing:** End-to-end bid placement

---

## P3 - LOW PRIORITY FIXES

### Fix 13: Enhanced Product Search
**File:** `app/Http/Controllers/Web/Company/CompanyShopController.php`  
**Add:** Search functionality to index

**Risk:** LOW - Enhancement  
**Dependencies:** None  
**Testing:** Search accuracy

---

### Fix 14: Order Confirmation Page
**File:** `resources/views/company/shop/confirmation.blade.php` (NEW)  
**Purpose:** Post-purchase confirmation

**Risk:** LOW - UI enhancement  
**Dependencies:** Checkout flow  
**Testing:** Display after order

---

### Fix 15: Banner Display Component
**File:** `resources/views/company/shop/partials/banner.blade.php` (NEW)  
**Purpose:** Reusable banner slider

**Features:**
- Image/video support
- Auto-rotate
- Admin-controlled content

**Risk:** LOW - UI component  
**Dependencies:** Banner model  
**Testing:** Visual rendering

---

## IMPLEMENTATION ORDER

### Week 1: Critical Fixes
1. ✅ Fix product audience filter (2 hours)
2. ✅ Fix addToCart stub (4 hours)
3. ✅ Wire up cart count in layout (1 hour)
4. ✅ Create shop index view (1 day)
5. ✅ Create product card partial (4 hours)

### Week 2: Shop Completion
6. ✅ Create cart view (1 day)
7. ✅ Create checkout view (1 day)
8. ✅ JavaScript for cart operations (1 day)
9. ✅ Order placement integration (1 day)

### Week 3: Public Shop & Bidding
10. ✅ Public shop controller (1 day)
11. ✅ Public shop views (2 days)
12. ✅ Bidding web interface (2 days)

### Week 4: Polish
13. ✅ Testing & bug fixes (3 days)
14. ✅ Documentation update (1 day)
15. ✅ Performance optimization (1 day)

---

## FILES TO CREATE

### Controllers (1)
```
app/Http/Controllers/Web/ShopController.php (NEW - Public shop)
```

### Views (12)
```
resources/views/company/shop/
├── index.blade.php              (NEW)
├── cart.blade.php               (NEW)
├── checkout.blade.php           (NEW)
├── confirmation.blade.php       (NEW)
├── orders.blade.php             (NEW)
├── order_detail.blade.php       (NEW)
└── partials/
    ├── product-card.blade.php   (NEW)
    └── banner.blade.php         (NEW)

resources/views/company/bids/
├── index.blade.php              (NEW)
├── available.blade.php          (NEW)
├── create.blade.php             (NEW)
└── show.blade.php               (NEW)

resources/views/shop/            (Public website)
├── index.blade.php              (NEW)
├── show.blade.php               (NEW)
└── cart.blade.php               (NEW)
```

### JavaScript (1)
```
public/js/company-shop.js        (NEW - Cart operations)
```

---

## FILES TO MODIFY

### Critical (2)
1. `app/Http/Controllers/Api/V1/Shop/ProductController.php` - Fix audience filter
2. `app/Http/Controllers/Web/Company/CompanyShopController.php` - Fix addToCart

### Layout (1)
3. `resources/views/company/layouts/app.blade.php` - Live cart count

### Routes (2)
4. `routes/web.php` - Add public shop routes
5. `routes/web/company.php` - Already has shop routes

---

## TESTING CHECKLIST

### P0 Fixes
- [ ] Products with target_audience='company' appear in API
- [ ] Products with target_audience='all' appear in API
- [ ] Add to cart creates database record
- [ ] Cart count updates in header

### P1 Fixes
- [ ] Shop index displays products
- [ ] Product cards show correct info
- [ ] Add to cart from shop page works
- [ ] Cart view shows items
- [ ] Quantity updates work
- [ ] Remove from cart works
- [ ] Checkout calculates totals
- [ ] Order placement creates records

### P2 Fixes
- [ ] Public shop accessible without login
- [ ] Public product browsing works
- [ ] Bidding interface loads
- [ ] Bid submission works
- [ ] Bid history displays

---

## RISK MITIGATION

### Database Risks
- All cart operations use existing Cart model
- No schema changes required
- Rollback: Delete cart records if needed

### API Risks
- Shop API already tested and working
- Changes are to web layer only
- API backward compatibility maintained

### UI Risks
- New views follow existing patterns
- Uses same layout (AdminLTE)
- Can be disabled by removing routes

---

## SUCCESS CRITERIA

1. **Critical:** Product filter returns correct results
2. **Critical:** Cart adds items successfully
3. **High:** Company can browse shop
4. **High:** Company can complete purchase
5. **Medium:** Public shop is accessible
6. **Medium:** Company can place bids via web

---

**END OF FIX ROADMAP**

*This roadmap provides a clear path to bring the system to 95%+ completion. Follow the priority order to maximize impact.*
