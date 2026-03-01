@extends('user.layouts.app')

@section('title', 'Shopping Cart')

@section('extra-css')
<style>
    .cart-item {
        display: flex;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid #F1F5F9;
        transition: background 0.2s;
    }
    .cart-item:hover {
        background: #F8FAFC;
    }
    .cart-item-image {
        width: 100px;
        height: 100px;
        border-radius: 12px;
        object-fit: cover;
        background: #F1F5F9;
    }
    .cart-item-details {
        flex: 1;
        margin-left: 20px;
    }
    .cart-item-name {
        font-weight: 600;
        font-size: 1.1rem;
        color: #1E293B;
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
        background: #F1F5F9;
        padding: 5px;
        border-radius: 10px;
    }
    .quantity-btn {
        width: 32px;
        height: 32px;
        border: none;
        background: #fff;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        color: #64748B;
    }
    .quantity-btn:hover {
        background: #F97316;
        color: #fff;
    }
    .quantity-input {
        width: 40px;
        text-align: center;
        border: none;
        background: transparent;
        font-weight: 600;
    }
    .remove-btn {
        color: #EF4444;
        background: #FEE2E2;
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        margin-left: 20px;
    }
    .remove-btn:hover {
        background: #EF4444;
        color: #fff;
    }
    .cart-summary {
        background: #fff;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        position: sticky;
        top: 100px;
    }
    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #F1F5F9;
    }
    .summary-row:last-child {
        border-bottom: none;
    }
    .summary-row.total {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1E293B;
        padding-top: 16px;
        margin-top: 8px;
        border-top: 2px solid #F1F5F9;
    }
    .promo-input {
        border: 2px solid #E2E8F0;
        border-radius: 10px;
        padding: 12px 16px;
        width: 100%;
        transition: border-color 0.2s;
    }
    .promo-input:focus {
        outline: none;
        border-color: #F97316;
    }
    .empty-cart {
        text-align: center;
        padding: 80px 20px;
    }
    .empty-cart-icon {
        width: 120px;
        height: 120px;
        background: #FFF7ED;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 24px;
    }
    .empty-cart-icon i {
        font-size: 3rem;
        color: #F97316;
    }
</style>
@endsection

@section('content')
<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-shopping-cart mr-3"></i>Shopping Cart</h1>
        <p class="mb-0 mt-2 opacity-75">Review your items and proceed to checkout</p>
    </div>
</div>

<div class="container">
    @if(isset($cartItems) && $cartItems->count() > 0)
    <div class="row">
        <!-- Cart Items -->
        <div class="col-lg-8">
            <div class="fetch-card mb-4">
                <div class="p-4 border-bottom">
                    <h5 class="font-weight-bold mb-0">{{ $cartItems->count() }} Item{{ $cartItems->count() > 1 ? 's' : '' }} in Cart</h5>
                </div>
                <div id="cart-items">
                    @foreach($cartItems as $item)
                    <div class="cart-item" data-cart-id="{{ $item->id }}">
                        <img src="{{ $item->product->first_image ?? asset('images/default-product.png') }}"
                             alt="{{ $item->product->name }}" class="cart-item-image">

                        <div class="cart-item-details">
                            <div class="cart-item-name">{{ $item->product->name }}</div>
                            <div class="text-muted small mb-2">{{ $item->product->category ?? 'General' }}</div>
                            <div class="cart-item-price">₦{{ number_format($item->unit_price, 2) }}</div>
                        </div>

                        <div class="quantity-control">
                            <button class="quantity-btn" onclick="updateQuantity({{ $item->id }}, -1)"
                                    {{ $item->quantity <= 1 ? 'disabled' : '' }}>
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="text" class="quantity-input" value="{{ $item->quantity }}" readonly>
                            <button class="quantity-btn" onclick="updateQuantity({{ $item->id }}, 1)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>

                        <div class="font-weight-bold ml-4" style="min-width: 100px; text-align: right;">
                            ₦{{ number_format($item->total_price, 2) }}
                        </div>

                        <button class="remove-btn" onclick="removeItem({{ $item->id }})" title="Remove item">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                    @endforeach
                </div>
            </div>

            <a href="{{ route('user.shop.index') }}" class="btn btn-outline-brand">
                <i class="fas fa-arrow-left mr-2"></i>Continue Shopping
            </a>
        </div>

        <!-- Cart Summary -->
        <div class="col-lg-4">
            <div class="cart-summary">
                <h5 class="font-weight-bold mb-4">Order Summary</h5>

                <!-- Promo Code -->
                <div class="mb-4">
                    <div class="input-group">
                        <input type="text" class="promo-input" placeholder="Enter promo code">
                        <div class="input-group-append">
                            <button class="btn btn-brand" style="border-radius: 0 10px 10px 0;">Apply</button>
                        </div>
                    </div>
                </div>

                <div class="summary-row">
                    <span class="text-muted">Subtotal</span>
                    <span class="font-weight-600">₦{{ number_format($subtotal, 2) }}</span>
                </div>
                <div class="summary-row">
                    <span class="text-muted">Shipping</span>
                    <span class="font-weight-600">
                        @if($shipping > 0)
                            ₦{{ number_format($shipping, 2) }}
                        @else
                            <span class="text-success">Free</span>
                        @endif
                    </span>
                </div>
                <div class="summary-row">
                    <span class="text-muted">Tax (7.5%)</span>
                    <span class="font-weight-600">₦{{ number_format($tax, 2) }}</span>
                </div>
                @if($discount > 0)
                <div class="summary-row">
                    <span class="text-muted">Discount</span>
                    <span class="font-weight-600 text-success">-₦{{ number_format($discount, 2) }}</span>
                </div>
                @endif
                <div class="summary-row total">
                    <span>Total</span>
                    <span style="color: #F97316;">₦{{ number_format($total, 2) }}</span>
                </div>

                <a href="{{ route('user.checkout') }}" class="btn btn-brand btn-block btn-lg mt-4">
                    Proceed to Checkout<i class="fas fa-arrow-right ml-2"></i>
                </a>

                <div class="text-center mt-3">
                    <small class="text-muted"><i class="fas fa-lock mr-1"></i>Secure checkout</small>
                </div>
            </div>
        </div>
    </div>
    @else
    <!-- Empty Cart -->
    <div class="fetch-card">
        <div class="empty-cart">
            <div class="empty-cart-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <h3 class="font-weight-bold mb-2">Your cart is empty</h3>
            <p class="text-muted mb-4">Looks like you haven't added any items to your cart yet.</p>
            <a href="{{ route('user.shop.index') }}" class="btn btn-brand btn-lg">
                <i class="fas fa-store mr-2"></i>Start Shopping
            </a>
        </div>
    </div>
    @endif
</div>
@endsection

@section('extra-js')
<script>
function updateQuantity(cartId, change) {
    const input = document.querySelector(`.cart-item[data-cart-id="${cartId}"] .quantity-input`);
    const currentQty = parseInt(input.value);
    const newQty = currentQty + change;

    if (newQty < 1) return;

    fetch(`/api/cart/${cartId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ quantity: newQty })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            toastr.error(data.message || 'Failed to update quantity');
        }
    })
    .catch(error => {
        toastr.error('Failed to update quantity');
    });
}

function removeItem(cartId) {
    if (!confirm('Are you sure you want to remove this item?')) return;

    fetch(`/api/cart/${cartId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            toastr.success('Item removed from cart');
            location.reload();
        } else {
            toastr.error(data.message || 'Failed to remove item');
        }
    })
    .catch(error => {
        toastr.error('Failed to remove item');
    });
}
</script>
@endsection
