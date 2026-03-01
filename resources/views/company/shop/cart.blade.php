@extends('company.layouts.app')

@section('title', 'Shopping Cart')

@php
use App\Models\Cart;

$cartItems = Cart::with('product')
    ->forCompany($company->id)
    ->get();

$subtotal = $cartItems->sum('total_price');
$itemCount = $cartItems->sum('quantity');
@endphp

@section('extra-css')
<style>
    .cart-item {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 20px;
    }
    .cart-item-image {
        width: 100px;
        height: 100px;
        background: #f8fafc;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .cart-item-image img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    .cart-item-details {
        flex: 1;
    }
    .cart-item-name {
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 5px;
    }
    .cart-item-price {
        color: #F97316;
        font-weight: 700;
        font-size: 1.1rem;
    }
    .quantity-control {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .quantity-btn {
        width: 36px;
        height: 36px;
        border: 1px solid #e2e8f0;
        background: white;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    .quantity-btn:hover {
        background: #f1f5f9;
    }
    .quantity-input {
        width: 50px;
        text-align: center;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        height: 36px;
    }
    .remove-btn {
        color: #ef4444;
        background: none;
        border: none;
        padding: 8px;
        cursor: pointer;
        transition: color 0.2s;
    }
    .remove-btn:hover {
        color: #dc2626;
    }
    .cart-summary {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        position: sticky;
        top: 20px;
    }
    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .summary-row.total {
        border-bottom: none;
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
    }
    .checkout-btn {
        background: #F97316;
        color: white;
        border: none;
        padding: 15px;
        border-radius: 8px;
        width: 100%;
        font-weight: 600;
        font-size: 1.1rem;
        margin-top: 20px;
        transition: background 0.2s;
    }
    .checkout-btn:hover {
        background: #EA580C;
    }
    .checkout-btn:disabled {
        background: #cbd5e1;
        cursor: not-allowed;
    }
    .continue-shopping {
        color: #64748b;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 15px;
    }
    .continue-shopping:hover {
        color: #F97316;
    }
    .empty-cart {
        text-align: center;
        padding: 60px 20px;
    }
    .empty-cart i {
        font-size: 5rem;
        color: #e2e8f0;
        margin-bottom: 20px;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="page-top-bar mb-4">
        <h4><i class="fas fa-shopping-cart mr-2"></i>Shopping Cart</h4>
        <p>{{ $itemCount }} item{{ $itemCount != 1 ? 's' : '' }} in your cart</p>
    </div>

    @if($cartItems->count() > 0)
    <div class="row">
        <!-- Cart Items -->
        <div class="col-lg-8">
            <div id="cart-items-container">
                @foreach($cartItems as $item)
                <div class="cart-item" data-cart-id="{{ $item->id }}">
                    <!-- Product Image -->
                    <div class="cart-item-image">
                        @if($item->product && !empty($item->product->images) && is_array($item->product->images))
                            <img src="{{ $item->product->images[0] }}" alt="{{ $item->product->name }}">
                        @else
                            <i class="fas fa-image fa-2x text-muted"></i>
                        @endif
                    </div>

                    <!-- Product Details -->
                    <div class="cart-item-details">
                        <div class="cart-item-name">
                            {{ $item->product ? $item->product->name : 'Unknown Product' }}
                        </div>
                        <div class="cart-item-price">
                            ₦{{ number_format($item->unit_price, 2) }} each
                        </div>
                    </div>

                    <!-- Quantity Control -->
                    <div class="quantity-control">
                        <button class="quantity-btn" onclick="updateQuantity({{ $item->id }}, -1)" 
                                {{ $item->quantity <= 1 ? 'disabled' : '' }}>
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" class="quantity-input" value="{{ $item->quantity }}" 
                               min="1" readonly data-cart-id="{{ $item->id }}">
                        <button class="quantity-btn" onclick="updateQuantity({{ $item->id }}, 1)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>

                    <!-- Item Total -->
                    <div class="cart-item-price" style="min-width: 120px; text-align: right;">
                        ₦{{ number_format($item->total_price, 2) }}
                    </div>

                    <!-- Remove Button -->
                    <button class="remove-btn" onclick="removeItem({{ $item->id }})" title="Remove item">
                        <i class="fas fa-trash-alt fa-lg"></i>
                    </button>
                </div>
                @endforeach
            </div>

            <a href="{{ route('company.shop.index') }}" class="continue-shopping">
                <i class="fas fa-arrow-left"></i>
                Continue Shopping
            </a>
        </div>

        <!-- Cart Summary -->
        <div class="col-lg-4">
            <div class="cart-summary">
                <h5 class="mb-4" style="font-weight: 600;">Order Summary</h5>
                
                <div class="summary-row">
                    <span>Subtotal ({{ $itemCount }} items)</span>
                    <span id="cart-subtotal">₦{{ number_format($subtotal, 2) }}</span>
                </div>
                
                <div class="summary-row">
                    <span>Tax</span>
                    <span>Calculated at checkout</span>
                </div>
                
                <div class="summary-row">
                    <span>Delivery</span>
                    <span>Calculated at checkout</span>
                </div>
                
                <div class="summary-row total">
                    <span>Estimated Total</span>
                    <span id="cart-total">₦{{ number_format($subtotal, 2) }}</span>
                </div>

                <a href="{{ route('company.shop.checkout') }}" class="checkout-btn d-block text-center text-decoration-none">
                    Proceed to Checkout
                </a>
            </div>
        </div>
    </div>
    @else
    <!-- Empty Cart -->
    <div class="empty-cart">
        <i class="fas fa-shopping-cart"></i>
        <h4>Your cart is empty</h4>
        <p class="text-muted mb-4">Looks like you haven't added any items yet.</p>
        <a href="{{ route('company.shop.index') }}" class="btn btn-primary btn-lg" style="background: #F97316; border-color: #F97316;">
            <i class="fas fa-store mr-2"></i>Browse Products
        </a>
    </div>
    @endif
</div>
@endsection

@section('extra-js')
<script>
function updateQuantity(cartId, change) {
    const input = $(`.quantity-input[data-cart-id="${cartId}"]`);
    const currentQty = parseInt(input.val());
    const newQty = currentQty + change;
    
    if (newQty < 1) return;
    
    // Disable controls during update
    input.closest('.cart-item').find('button').prop('disabled', true);
    
    $.ajax({
        url: `/api/v1/shop/cart/${cartId}`,
        method: 'PUT',
        data: {
            _token: '{{ csrf_token() }}',
            quantity: newQty
        },
        success: function(response) {
            if (response.success) {
                location.reload(); // Reload to update totals
            } else {
                toastr.error(response.message || 'Failed to update quantity');
            }
        },
        error: function(xhr) {
            let message = 'Failed to update quantity';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            toastr.error(message);
        },
        complete: function() {
            input.closest('.cart-item').find('button').prop('disabled', false);
        }
    });
}

function removeItem(cartId) {
    if (!confirm('Are you sure you want to remove this item?')) return;
    
    const itemRow = $(`.cart-item[data-cart-id="${cartId}"]`);
    itemRow.fadeOut(300);
    
    $.ajax({
        url: `/api/v1/shop/cart/${cartId}`,
        method: 'DELETE',
        data: {
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            if (response.success) {
                toastr.success('Item removed from cart');
                updateCartCount(response.cart_count);
                
                // Remove from DOM
                itemRow.remove();
                
                // Reload if cart is empty
                if (response.cart_count === 0) {
                    location.reload();
                }
            } else {
                toastr.error(response.message || 'Failed to remove item');
                itemRow.fadeIn();
            }
        },
        error: function(xhr) {
            let message = 'Failed to remove item';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            toastr.error(message);
            itemRow.fadeIn();
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
