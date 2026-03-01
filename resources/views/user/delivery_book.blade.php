@extends('user.layouts.app')

@section('title', 'Book Delivery')

@section('extra-css')
<style>
    .booking-step {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 40px;
    }
    .booking-step-item {
        display: flex;
        align-items: center;
        color: #94A3B8;
    }
    .booking-step-item.active {
        color: #F97316;
    }
    .booking-step-item.completed {
        color: #22C55E;
    }
    .step-circle {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #F1F5F9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        margin-right: 10px;
    }
    .booking-step-item.active .step-circle {
        background: #F97316;
        color: #fff;
    }
    .booking-step-item.completed .step-circle {
        background: #22C55E;
        color: #fff;
    }
    .step-line {
        width: 80px;
        height: 2px;
        background: #E2E8F0;
        margin: 0 16px;
    }
    .booking-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        padding: 30px;
    }
    .location-input {
        position: relative;
    }
    .location-input i {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #F97316;
        font-size: 1.1rem;
    }
    .location-input input {
        padding-left: 45px;
        height: 56px;
        border: 2px solid #E5E7EB;
        border-radius: 12px;
        font-size: 1rem;
    }
    .location-input input:focus {
        border-color: #F97316;
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
    }
    .vehicle-option {
        border: 2px solid #E5E7EB;
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
    }
    .vehicle-option:hover {
        border-color: #F97316;
        transform: translateY(-4px);
    }
    .vehicle-option.selected {
        border-color: #F97316;
        background: #FFF7ED;
    }
    .vehicle-icon {
        width: 80px;
        height: 80px;
        background: #F1F5F9;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        font-size: 2rem;
        color: #64748B;
    }
    .vehicle-option.selected .vehicle-icon {
        background: #F97316;
        color: #fff;
    }
    .vehicle-name {
        font-weight: 700;
        font-size: 1.1rem;
        margin-bottom: 4px;
    }
    .vehicle-capacity {
        color: #64748B;
        font-size: 0.875rem;
    }
    .vehicle-price {
        color: #F97316;
        font-weight: 700;
        font-size: 1.25rem;
        margin-top: 8px;
    }
    .package-type-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    .package-type {
        border: 2px solid #E5E7EB;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    .package-type:hover, .package-type.selected {
        border-color: #F97316;
        background: #FFF7ED;
    }
    .package-type i {
        font-size: 2rem;
        color: #64748B;
        margin-bottom: 8px;
    }
    .package-type.selected i {
        color: #F97316;
    }
    .price-estimate {
        background: linear-gradient(135deg, #F97316 0%, #EA580C 100%);
        color: #fff;
        border-radius: 16px;
        padding: 24px;
        text-align: center;
    }
    .price-estimate-value {
        font-size: 2.5rem;
        font-weight: 800;
    }
    .price-breakdown {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        margin-top: 16px;
    }
    .price-breakdown-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px dashed #E2E8F0;
    }
    .price-breakdown-item:last-child {
        border-bottom: none;
        font-weight: 700;
        font-size: 1.1rem;
        color: #1E293B;
    }
</style>
@endsection

@section('content')
<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-plus-circle mr-3"></i>Book a Delivery</h1>
        <p class="mb-0 mt-2 opacity-75">Send your package anywhere in Nigeria</p>
    </div>
</div>

<div class="container">
    <!-- Steps -->
    <div class="booking-step">
        <div class="booking-step-item active" id="step-1-indicator">
            <div class="step-circle">1</div>
            <span>Location</span>
        </div>
        <div class="step-line"></div>
        <div class="booking-step-item" id="step-2-indicator">
            <div class="step-circle">2</div>
            <span>Vehicle</span>
        </div>
        <div class="step-line"></div>
        <div class="booking-step-item" id="step-3-indicator">
            <div class="step-circle">3</div>
            <span>Package</span>
        </div>
        <div class="step-line"></div>
        <div class="booking-step-item" id="step-4-indicator">
            <div class="step-circle">4</div>
            <span>Confirm</span>
        </div>
    </div>

    <form action="{{ route('user.delivery.store') }}" method="POST" id="booking-form">
        @csrf

        <!-- Step 1: Locations -->
        <div class="booking-card mb-4" id="step-1">
            <h4 class="font-weight-bold mb-4"><i class="fas fa-map-marker-alt mr-2 text-warning"></i>Pickup & Delivery</h4>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <label class="font-weight-600 mb-2">Pickup Location *</label>
                    <div class="location-input">
                        <i class="fas fa-circle" style="color: #22C55E;"></i>
                        <input type="text" name="pickup_address" class="form-control" placeholder="Enter pickup address" required>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <label class="font-weight-600 mb-2">Delivery Location *</label>
                    <div class="location-input">
                        <i class="fas fa-map-marker-alt" style="color: #EF4444;"></i>
                        <input type="text" name="delivery_address" class="form-control" placeholder="Enter delivery address" required>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <label class="font-weight-600 mb-2">Sender Name *</label>
                    <input type="text" name="sender_name" class="form-control" value="{{ auth()->user()->name }}" required>
                </div>
                <div class="col-md-6 mb-4">
                    <label class="font-weight-600 mb-2">Sender Phone *</label>
                    <input type="tel" name="sender_phone" class="form-control" value="{{ auth()->user()->phone }}" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <label class="font-weight-600 mb-2">Receiver Name *</label>
                    <input type="text" name="receiver_name" class="form-control" placeholder="Enter receiver's name" required>
                </div>
                <div class="col-md-6 mb-4">
                    <label class="font-weight-600 mb-2">Receiver Phone *</label>
                    <input type="tel" name="receiver_phone" class="form-control" placeholder="Enter receiver's phone" required>
                </div>
            </div>

            <div class="text-right">
                <button type="button" class="btn btn-brand btn-lg" onclick="nextStep(2)">
                    Continue<i class="fas fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- Step 2: Vehicle Selection -->
        <div class="booking-card mb-4" id="step-2" style="display: none;">
            <h4 class="font-weight-bold mb-4"><i class="fas fa-truck mr-2 text-warning"></i>Select Vehicle Type</h4>

            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="vehicle-option selected" onclick="selectVehicle('bike', 1500)">
                        <div class="vehicle-icon">
                            <i class="fas fa-motorcycle"></i>
                        </div>
                        <div class="vehicle-name">Bike</div>
                        <div class="vehicle-capacity">Max 20kg • Documents & Small Packages</div>
                        <div class="vehicle-price">₦1,500</div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="vehicle-option" onclick="selectVehicle('car', 3500)">
                        <div class="vehicle-icon">
                            <i class="fas fa-car"></i>
                        </div>
                        <div class="vehicle-name">Car</div>
                        <div class="vehicle-capacity">Max 100kg • Medium Packages</div>
                        <div class="vehicle-price">₦3,500</div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="vehicle-option" onclick="selectVehicle('van', 8000)">
                        <div class="vehicle-icon">
                            <i class="fas fa-shuttle-van"></i>
                        </div>
                        <div class="vehicle-name">Van</div>
                        <div class="vehicle-capacity">Max 500kg • Large Packages</div>
                        <div class="vehicle-price">₦8,000</div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="vehicle-option" onclick="selectVehicle('truck', 15000)">
                        <div class="vehicle-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="vehicle-name">Truck</div>
                        <div class="vehicle-capacity">Max 2000kg • Heavy Cargo</div>
                        <div class="vehicle-price">₦15,000</div>
                    </div>
                </div>
            </div>

            <input type="hidden" name="vehicle_type" id="vehicle_type" value="bike">

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-brand" onclick="prevStep(1)">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </button>
                <button type="button" class="btn btn-brand btn-lg" onclick="nextStep(3)">
                    Continue<i class="fas fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- Step 3: Package Details -->
        <div class="booking-card mb-4" id="step-3" style="display: none;">
            <h4 class="font-weight-bold mb-4"><i class="fas fa-box mr-2 text-warning"></i>Package Details</h4>

            <div class="mb-4">
                <label class="font-weight-600 mb-2">Package Type</label>
                <div class="package-type-grid">
                    <div class="package-type selected" onclick="selectPackageType('document')">
                        <i class="fas fa-file-alt"></i>
                        <div>Document</div>
                    </div>
                    <div class="package-type" onclick="selectPackageType('food')">
                        <i class="fas fa-utensils"></i>
                        <div>Food</div>
                    </div>
                    <div class="package-type" onclick="selectPackageType('electronics')">
                        <i class="fas fa-laptop"></i>
                        <div>Electronics</div>
                    </div>
                    <div class="package-type" onclick="selectPackageType('clothing')">
                        <i class="fas fa-tshirt"></i>
                        <div>Clothing</div>
                    </div>
                    <div class="package-type" onclick="selectPackageType('furniture')">
                        <i class="fas fa-couch"></i>
                        <div>Furniture</div>
                    </div>
                    <div class="package-type" onclick="selectPackageType('other')">
                        <i class="fas fa-box"></i>
                        <div>Other</div>
                    </div>
                </div>
                <input type="hidden" name="package_type" id="package_type" value="document">
            </div>

            <div class="row">
                <div class="col-md-4 mb-4">
                    <label class="font-weight-600 mb-2">Weight (kg) *</label>
                    <input type="number" name="weight" class="form-control" placeholder="Enter weight" step="0.1" required>
                </div>
                <div class="col-md-4 mb-4">
                    <label class="font-weight-600 mb-2">Dimensions (Optional)</label>
                    <input type="text" name="dimensions" class="form-control" placeholder="L x W x H (cm)">
                </div>
                <div class="col-md-4 mb-4">
                    <label class="font-weight-600 mb-2">Delivery Priority</label>
                    <select name="priority" class="form-control">
                        <option value="standard">Standard</option>
                        <option value="express">Express (+50%)</option>
                        <option value="same_day">Same Day (+100%)</option>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label class="font-weight-600 mb-2">Package Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Describe your package"></textarea>
            </div>

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-brand" onclick="prevStep(2)">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </button>
                <button type="button" class="btn btn-brand btn-lg" onclick="nextStep(4)">
                    Continue<i class="fas fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- Step 4: Price & Confirm -->
        <div class="booking-card mb-4" id="step-4" style="display: none;">
            <h4 class="font-weight-bold mb-4"><i class="fas fa-check-circle mr-2 text-warning"></i>Confirm Booking</h4>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="price-estimate">
                        <div class="text-white-50 mb-2">Estimated Price</div>
                        <div class="price-estimate-value" id="estimated-price">₦1,500</div>
                        <div class="mt-2">Final price may vary based on actual distance</div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="price-breakdown">
                        <h6 class="font-weight-bold mb-3">Price Breakdown</h6>
                        <div class="price-breakdown-item">
                            <span>Base Fare</span>
                            <span id="base-fare">₦1,500</span>
                        </div>
                        <div class="price-breakdown-item">
                            <span>Distance</span>
                            <span>Calculated on booking</span>
                        </div>
                        <div class="price-breakdown-item">
                            <span>Service Fee</span>
                            <span>₦200</span>
                        </div>
                        <div class="price-breakdown-item">
                            <span>Total Estimate</span>
                            <span id="total-estimate">₦1,700</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                By confirming this booking, you agree to our <a href="#">Terms of Service</a> and <a href="#">Delivery Policy</a>.
            </div>

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-brand" onclick="prevStep(3)">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </button>
                <button type="submit" class="btn btn-brand btn-lg" id="confirm-btn">
                    <i class="fas fa-check mr-2"></i>Confirm Booking
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('extra-js')
<script>
let currentStep = 1;
let vehiclePrice = 1500;

function nextStep(step) {
    document.getElementById(`step-${currentStep}`).style.display = 'none';
    document.getElementById(`step-${step}`).style.display = 'block';

    document.getElementById(`step-${currentStep}-indicator`).classList.remove('active');
    document.getElementById(`step-${currentStep}-indicator`).classList.add('completed');
    document.getElementById(`step-${step}-indicator`).classList.add('active');

    currentStep = step;
}

function prevStep(step) {
    document.getElementById(`step-${currentStep}`).style.display = 'none';
    document.getElementById(`step-${step}`).style.display = 'block';

    document.getElementById(`step-${currentStep}-indicator`).classList.remove('active');
    document.getElementById(`step-${step}-indicator`).classList.remove('completed');
    document.getElementById(`step-${step}-indicator`).classList.add('active');

    currentStep = step;
}

function selectVehicle(type, price) {
    document.querySelectorAll('.vehicle-option').forEach(el => el.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    document.getElementById('vehicle_type').value = type;
    vehiclePrice = price;
    updatePrice();
}

function selectPackageType(type) {
    document.querySelectorAll('.package-type').forEach(el => el.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    document.getElementById('package_type').value = type;
}

function updatePrice() {
    const basePrice = vehiclePrice;
    const serviceFee = 200;
    const total = basePrice + serviceFee;

    document.getElementById('base-fare').textContent = '₦' + basePrice.toLocaleString();
    document.getElementById('estimated-price').textContent = '₦' + basePrice.toLocaleString();
    document.getElementById('total-estimate').textContent = '₦' + total.toLocaleString();
}

document.getElementById('booking-form').addEventListener('submit', function(e) {
    const btn = document.getElementById('confirm-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
});
</script>
@endsection
