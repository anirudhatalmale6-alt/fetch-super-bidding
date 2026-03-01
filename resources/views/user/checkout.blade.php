@extends('user.layouts.app')

@section('title', 'Checkout')

@section('extra-css')
<style>
    .checkout-step {
        display: flex;
        align-items: center;
        margin-bottom: 30px;
    }
    .step-number {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #F1F5F9;
        color: #64748B;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        margin-right: 12px;
        transition: all 0.3s;
    }
    .step-number.active {
        background: #F97316;
        color: #fff;
    }
    .step-number.completed {
        background: #22C55E;
        color: #fff;
    }
    .step-title {
        font-weight: 600;
        color: #1E293B;
    }
    .step-title.active {
        color: #F97316;
    }
    .checkout-section {
        background: #fff;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 20px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    }
    .form-group label {
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
        font-size: 0.9rem;
    }
    .form-control {
        border: 2px solid #E5E7EB;
        border-radius: 10px;
        padding: 12px 16px;
        transition: all 0.2s;
    }
    .form-control:focus {
        border-color: #F97316;
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
    }
    .delivery-option, .payment-option {
        border: 2px solid #E5E7EB;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 12px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
    }
    .delivery-option:hover, .payment-option:hover {
        border-color: #F97316;
    }
    .delivery-option.selected, .payment-option.selected {
        border-color: #F97316;
        background: #FFF7ED;
    }
    .option-icon {
        width: 48px;
        height: 48px;
        background: #F1F5F9;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 16px;
        font-size: 1.25rem;
        color: #64748B;
    }
    .delivery-option.selected .option-icon,
    .payment-option.selected .option-icon {
        background: #F97316;
        color: #fff;
    }
    .option-details h6 {
        font-weight: 600;
        margin-bottom: 4px;
    }
    .option-details p {
        color: #64748B;
        font-size: 0.875rem;
        margin: 0;
    }
    .order-item {
        display: flex;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #F1F5F9;
    }
    .order-item:last-child {
        border-bottom: none;
    }
    .order-item-image {
        width: 60px;
        height: 60px;
        border-radius: 8px;
        object-fit: cover;
        background: #F1F5F9;
    }
    .order-item-details {
        flex: 1;
        margin-left: 12px;
    }
    .order-item-name {
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 2px;
    }
    .order-item-qty {
        color: #64748B;
        font-size: 0.8rem;
    }
    .order-item-price {
        font-weight: 600;
        color: #F97316;
    }
</style>
@endsection

@section('content')
<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-credit-card mr-3"></i>Checkout</h1>
        <p class="mb-0 mt-2 opacity-75">Complete your order by providing your details</p>
    </div>
</div>

<div class="container">
    <form action="{{ route('user.checkout.process') }}" method="POST" id="checkout-form">
        @csrf

        <div class="row">
            <!-- Main Checkout Form -->
            <div class="col-lg-8">
                <!-- Delivery Information -->
                <div class="checkout-section">
                    <div class="d-flex align-items-center mb-4">
                        <div class="step-number active">1</div>
                        <div class="step-title active">Delivery Information</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" class="form-control" required
                                       value="{{ auth()->user()->first_name }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" class="form-control" required
                                       value="{{ auth()->user()->last_name }}">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email Address *</label>
                                <input type="email" name="email" class="form-control" required
                                       value="{{ auth()->user()->email }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone Number *</label>
                                <input type="tel" name="phone" class="form-control" required
                                       value="{{ auth()->user()->phone }}">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Delivery Address *</label>
                        <textarea name="address" class="form-control" rows="3" required
                                  placeholder="Enter your complete delivery address">{{ auth()->user()->address }}</textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>City *</label>
                                <input type="text" name="city" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>State *</label>
                                <input type="text" name="state" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Delivery Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Any special instructions for delivery"></textarea>
                    </div>
                </div>

                <!-- Delivery Method -->
                <div class="checkout-section">
                    <div class="d-flex align-items-center mb-4">
                        <div class="step-number active">2</div>
                        <div class="step-title active">Delivery Method</div>
                    </div>

                    <div class="delivery-option selected" onclick="selectDelivery('standard')">
                        <input type="radio" name="delivery_method" value="standard" checked class="mr-3" style="width: 20px; height: 20px;">
                        <div class="option-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="option-details">
                            <h6>Standard Delivery</h6>
                            <p>3-5 business days • Free for orders over ₦50,000</p>
                        </div>
                        <div class="ml-auto font-weight-bold">₦2,500</div>
                    </div>

                    <div class="delivery-option" onclick="selectDelivery('express')">
                        <input type="radio" name="delivery_method" value="express" class="mr-3" style="width: 20px; height: 20px;">
                        <div class="option-icon">
                            <i class="fas fa-shipping-fast"></i>
                        </div>
                        <div class="option-details">
                            <h6>Express Delivery</h6>
                            <p>1-2 business days • Priority handling</p>
                        </div>
                        <div class="ml-auto font-weight-bold">₦5,000</div>
                    </div>

                    <div class="delivery-option" onclick="selectDelivery('interstate')">
                        <input type="radio" name="delivery_method" value="interstate" class="mr-3" style="width: 20px; height: 20px;">
                        <div class="option-icon">
                            <i class="fas fa-route"></i>
                        </div>
                        <div class="option-details">
                            <h6>Interstate Delivery</h6>
                            <p>5-7 business days • For locations outside Lagos</p>
                        </div>
                        <div class="ml-auto font-weight-bold">₦8,000</div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="checkout-section">
                    <div class="d-flex align-items-center mb-4">
                        <div class="step-number active">3</div>
                        <div class="step-title active">Payment Method</div>
                    </div>

                    <div class="payment-option selected" onclick="selectPayment('flutterwave')">
                        <input type="radio" name="payment_method" value="flutterwave" checked class="mr-3" style="width: 20px; height: 20px;">
                        <div class="option-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="option-details">
                            <h6>Pay with Card</h6>
                            <p>Secure payment via Flutterwave</p>
                        </div>
                    </div>

                    <div class="payment-option" onclick="selectPayment('bank_transfer')">
                        <input type="radio" name="payment_method" value="bank_transfer" class="mr-3" style="width: 20px; height: 20px;">
                        <div class="option-icon">
                            <i class="fas fa-university"></i>
                        </div>
                        <div class="option-details">
                            <h6>Bank Transfer</h6>
                            <p>Pay via bank transfer and upload proof</p>
                        </div>
                    </div>

                    <div class="payment-option" onclick="selectPayment('wallet')">
                        <input type="radio" name="payment_method" value="wallet" class="mr-3" style="width: 20px; height: 20px;">
                        <div class="option-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="option-details">
                            <h6>Wallet Payment</h6>
                            <p>Pay from your wallet balance (₦{{ number_format(auth()->user()->wallet_balance ?? 0, 2) }})</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="checkout-section" style="position: sticky; top: 100px;">
                    <h5 class="font-weight-bold mb-4">Order Summary</h5>

                    <!-- Order Items -->
                    <div class="mb-4">
                        @foreach($cartItems as $item)
                        <div class="order-item">
                            <img src="{{ $item->product->first_image ?? asset('images/default-product.png') }}" class="order-item-image" alt="">
                            <div class="order-item-details">
                                <div class="order-item-name">{{ Str::limit($item->product->name, 30) }}</div>
                                <div class="order-item-qty">Qty: {{ $item->quantity }}</div>
                            </div>
                            <div class="order-item-price">₦{{ number_format($item->total_price, 2) }}</div>
                        </div>
                        @endforeach
                    </div>

                    <hr>

                    <!-- Summary -->
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span class="font-weight-600">₦{{ number_format($subtotal, 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Shipping</span>
                        <span class="font-weight-600" id="shipping-cost">₦2,500</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Tax (7.5%)</span>
                        <span class="font-weight-600">₦{{ number_format($tax, 2) }}</span>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mb-4">
                        <span class="font-weight-bold">Total</span>
                        <span class="font-weight-bold" style="font-size: 1.25rem; color: #F97316;" id="order-total">
                            ₦{{ number_format($total, 2) }}
                        </span>
                    </div>

                    <button type="submit" class="btn btn-brand btn-block btn-lg" id="place-order-btn">
                        <i class="fas fa-lock mr-2"></i>Place Order
                    </button>

                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt mr-1"></i>Your payment is secured
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('extra-js')
<script>
function selectDelivery(method) {
    document.querySelectorAll('.delivery-option').forEach(el => {
        el.classList.remove('selected');
        el.querySelector('input[type="radio"]').checked = false;
    });
    event.currentTarget.classList.add('selected');
    event.currentTarget.querySelector('input[type="radio"]').checked = true;
    updateTotals();
}

function selectPayment(method) {
    document.querySelectorAll('.payment-option').forEach(el => {
        el.classList.remove('selected');
        el.querySelector('input[type="radio"]').checked = false;
    });
    event.currentTarget.classList.add('selected');
    event.currentTarget.querySelector('input[type="radio"]').checked = true;
}

function updateTotals() {
    const subtotal = {{ $subtotal }};
    const tax = {{ $tax }};
    const deliveryMethod = document.querySelector('input[name="delivery_method"]:checked').value;

    let shipping = 2500;
    if (deliveryMethod === 'express') shipping = 5000;
    if (deliveryMethod === 'interstate') shipping = 8000;

    const total = subtotal + tax + shipping;

    document.getElementById('shipping-cost').textContent = '₦' + shipping.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('order-total').textContent = '₦' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
}

document.getElementById('checkout-form').addEventListener('submit', function(e) {
    const btn = document.getElementById('place-order-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
});
</script>
@endsection
