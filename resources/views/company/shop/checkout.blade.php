@extends('company.layouts.app')

@section('title', 'Checkout')

@php
use App\Models\Cart;

$cartItems = Cart::with('product')
    ->forCompany($company->id)
    ->get();

$subtotal = $cartItems->sum('total_price');
$itemCount = $cartItems->sum('quantity');

// Redirect if cart is empty
if ($cartItems->count() === 0) {
    header('Location: ' . route('company.shop.cart'));
    exit;
}
@endphp

@section('extra-css')
<style>
    .checkout-section {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .section-title {
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #f1f5f9;
    }
    .form-group label {
        font-weight: 500;
        color: #374151;
        margin-bottom: 8px;
    }
    .form-control {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px 15px;
    }
    .form-control:focus {
        border-color: #F97316;
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
    }
    .delivery-option {
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .delivery-option:hover {
        border-color: #F97316;
    }
    .delivery-option.selected {
        border-color: #F97316;
        background: #fff7ed;
    }
    .delivery-option input[type="radio"] {
        margin-right: 12px;
    }
    .payment-method {
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .payment-method:hover {
        border-color: #F97316;
    }
    .payment-method.selected {
        border-color: #F97316;
        background: #fff7ed;
    }
    .payment-method i {
        font-size: 1.5rem;
        color: #F97316;
    }
    .order-summary {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        position: sticky;
        top: 20px;
    }
    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .summary-item:last-child {
        border-bottom: none;
    }
    .summary-total {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        padding-top: 15px;
        border-top: 2px solid #e5e7eb;
        margin-top: 15px;
    }
    .place-order-btn {
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
    .place-order-btn:hover {
        background: #EA580C;
    }
    .place-order-btn:disabled {
        background: #cbd5e1;
        cursor: not-allowed;
    }
    .cart-mini-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .cart-mini-item img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 6px;
    }
    .cart-mini-item-info {
        flex: 1;
    }
    .cart-mini-item-name {
        font-weight: 500;
        font-size: 0.9rem;
    }
    .cart-mini-item-price {
        color: #F97316;
        font-weight: 600;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="page-top-bar mb-4">
        <h4><i class="fas fa-credit-card mr-2"></i>Checkout</h4>
        <p>Complete your order</p>
    </div>

    <form id="checkout-form" action="{{ route('company.shop.process-checkout') }}" method="POST">
        @csrf
        
        <div class="row">
            <!-- Main Checkout Form -->
            <div class="col-lg-8">
                <!-- Delivery Information -->
                <div class="checkout-section">
                    <h5 class="section-title">
                        <i class="fas fa-truck mr-2 text-warning"></i>Delivery Information
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="contact_name">Contact Name *</label>
                                <input type="text" class="form-control" id="contact_name" name="delivery_contact_name" 
                                       value="{{ auth()->user()->name }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="contact_phone">Contact Phone *</label>
                                <input type="tel" class="form-control" id="contact_phone" name="delivery_contact_phone" 
                                       value="{{ auth()->user()->phone }}" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="delivery_address">Delivery Address *</label>
                        <textarea class="form-control" id="delivery_address" name="delivery_address" rows="3" required
                                  placeholder="Enter your complete delivery address"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="delivery_notes">Delivery Notes (Optional)</label>
                        <textarea class="form-control" id="delivery_notes" name="delivery_notes" rows="2"
                                  placeholder="Any special instructions for delivery"></textarea>
                    </div>
                </div>

                <!-- Delivery Method -->
                <div class="checkout-section">
                    <h5 class="section-title">
                        <i class="fas fa-shipping-fast mr-2 text-warning"></i>Delivery Method
                    </h5>
                    
                    <div class="delivery-option selected" onclick="selectDelivery('metro')">
                        <div class="d-flex align-items-start">
                            <input type="radio" name="delivery_type" value="metro" checked id="delivery_metro">
                            <div class="ml-3">
                                <label for="delivery_metro" class="font-weight-bold mb-1" style="cursor: pointer;">
                                    Metro Delivery
                                </label>
                                <p class="text-muted mb-0 small">
                                    Standard local delivery within the city. 1-2 business days.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="delivery-option" onclick="selectDelivery('interstate')">
                        <div class="d-flex align-items-start">
                            <input type="radio" name="delivery_type" value="interstate" id="delivery_interstate">
                            <div class="ml-3">
                                <label for="delivery_interstate" class="font-weight-bold mb-1" style="cursor: pointer;">
                                    Interstate Delivery
                                </label>
                                <p class="text-muted mb-0 small">
                                    Long-distance delivery between states. 3-5 business days.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="checkout-section">
                    <h5 class="section-title">
                        <i class="fas fa-wallet mr-2 text-warning"></i>Payment Method
                    </h5>
                    
                    <div class="payment-method selected" onclick="selectPayment('flutterwave')">
                        <input type="radio" name="payment_method" value="flutterwave" checked id="payment_flutterwave">
                        <i class="fas fa-credit-card"></i>
                        <div>
                            <div class="font-weight-bold">Pay with Card</div>
                            <div class="text-muted small">Secure payment via Flutterwave</div>
                        </div>
                    </div>
                    
                    <div class="payment-method" onclick="selectPayment('bank_transfer')">
                        <input type="radio" name="payment_method" value="bank_transfer" id="payment_bank_transfer">
                        <i class="fas fa-university"></i>
                        <div>
                            <div class="font-weight-bold">Bank Transfer</div>
                            <div class="text-muted small">Pay via bank transfer and upload proof</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="order-summary">
                    <h5 class="mb-4" style="font-weight: 600;">Order Summary</h5>
                    
                    <!-- Mini Cart Items -->
                    <div class="cart-mini-items mb-3">
                        @foreach($cartItems as $item)
                        <div class="cart-mini-item">
                            @if($item->product && !empty($item->product->images) && is_array($item->product->images))
                                <img src="{{ $item->product->images[0] }}" alt="{{ $item->product->name }}">
                            @else
                                <div style="width: 50px; height: 50px; background: #f1f5f9; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-image text-muted"></i>
                                </div>
                            @endif
                            <div class="cart-mini-item-info">
                                <div class="cart-mini-item-name">{{ $item->product ? $item->product->name : 'Unknown' }}</div>
                                <div class="text-muted small">Qty: {{ $item->quantity }}</div>
                            </div>
                            <div class="cart-mini-item-price">
                                ₦{{ number_format($item->total_price, 2) }}
                            </div>
                        </div>
                        @endforeach
                    </div>
                    
                    <!-- Summary Totals -->
                    <div class="summary-item">
                        <span>Subtotal ({{ $itemCount }} items)</span>
                        <span>₦{{ number_format($subtotal, 2) }}</span>
                    </div>
                    
                    <div class="summary-item">
                        <span>Delivery Fee</span>
                        <span id="delivery-fee">Calculated</span>
                    </div>
                    
                    <div class="summary-item">
                        <span>Tax</span>
                        <span id="tax-amount">Calculated</span>
                    </div>
                    
                    <div class="summary-total d-flex justify-content-between">
                        <span>Total</span>
                        <span id="order-total">₦{{ number_format($subtotal, 2) }}</span>
                    </div>

                    <button type="submit" class="place-order-btn" id="place-order-btn">
                        <i class="fas fa-lock mr-2"></i>Place Order
                    </button>
                    
                    <div class="text-center mt-3">
                        <a href="{{ route('company.shop.cart') }}" class="text-muted small">
                            <i class="fas fa-arrow-left mr-1"></i>Back to Cart
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('extra-js')
<script>
function selectDelivery(type) {
    // Update radio button
    document.getElementById('delivery_' + type).checked = true;
    
    // Update visual selection
    document.querySelectorAll('.delivery-option').forEach(el => {
        el.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
    
    // Recalculate totals (would need API call for actual delivery fee)
    updateTotals();
}

function selectPayment(method) {
    // Update radio button
    document.getElementById('payment_' + method).checked = true;
    
    // Update visual selection
    document.querySelectorAll('.payment-method').forEach(el => {
        el.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
}

function updateTotals() {
    // This would typically make an API call to calculate delivery fees
    // For now, we'll just show placeholder calculation
    const subtotal = {{ $subtotal }};
    const deliveryType = document.querySelector('input[name="delivery_type"]:checked').value;
    
    let deliveryFee = 0;
    if (deliveryType === 'metro') {
        deliveryFee = subtotal > 50000 ? 0 : 2500;
    } else {
        deliveryFee = subtotal > 100000 ? 0 : 5000;
    }
    
    const tax = subtotal * 0.075; // 7.5% VAT
    const total = subtotal + deliveryFee + tax;
    
    document.getElementById('delivery-fee').textContent = deliveryFee === 0 ? 'FREE' : '₦' + deliveryFee.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('tax-amount').textContent = '₦' + tax.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('order-total').textContent = '₦' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
}

// Update totals on page load
document.addEventListener('DOMContentLoaded', function() {
    updateTotals();
});

// Form submission
document.getElementById('checkout-form').addEventListener('submit', function(e) {
    const btn = document.getElementById('place-order-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
});
</script>
@endsection
