<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;
use App\Models\Request\Request;

class GoodsItem extends Model
{
    protected $table = 'trucking_goods_items';

    protected $fillable = [
        'goods_id',
        'request_id',
        'request_leg_id',
        'trucking_company_id',
        'current_handler_type',
        'current_handler_id',
        'current_handler_name',
        'handover_chain',
        'origin_address',
        'destination_address',
        'item_number',
        'item_index',
        'description',
        'category',
        'weight_kg',
        'length_cm',
        'width_cm',
        'height_cm',
        'quantity',
        'volumetric_weight_kg',
        'chargeable_weight_kg',
        'volumetric_divisor_used',
        'declared_value',
        'is_fragile',
        'requires_insurance',
        'company_price_per_kg',
        'company_base_price',
        'company_insurance_rate',
        'company_insurance_fee',
        'company_total_price',
        'transportation_service_fee',
        'insurance_fee',
        'total_service_fee',
        'fee_breakdown',
        'fee_added_at',
        'pricing_breakdown',
        'special_instructions',
        'status',
        'priced_at',
        'priced_by',
        'picked_up_at',
        'received_by_company_at',
        'dispatched_at',
        'delivered_at',
        'tracking_notes',
        'payment_status',
        'amount_paid',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:2',
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'volumetric_weight_kg' => 'decimal:2',
        'chargeable_weight_kg' => 'decimal:2',
        'declared_value' => 'decimal:2',
        'company_price_per_kg' => 'decimal:2',
        'company_base_price' => 'decimal:2',
        'company_insurance_rate' => 'decimal:2',
        'company_insurance_fee' => 'decimal:2',
        'company_total_price' => 'decimal:2',
        'transportation_service_fee' => 'decimal:2',
        'insurance_fee' => 'decimal:2',
        'total_service_fee' => 'decimal:2',
        'fee_breakdown' => 'array',
        'fee_added_at' => 'datetime',
        'pricing_breakdown' => 'array',
        'special_instructions' => 'array',
        'is_fragile' => 'boolean',
        'requires_insurance' => 'boolean',
        'priced_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'received_by_company_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'handover_chain' => 'array',
    ];

    /**
     * Boot method to auto-generate item number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            // Auto-generate the immutable goods_id
            if (empty($item->goods_id)) {
                $item->goods_id = 'GDS-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(8));
            }
            // Auto-generate item_number
            if (empty($item->item_number)) {
                $item->item_number = 'ITM-' . date('Ymd') . '-' . strtoupper(uniqid());
            }
        });

        // Prevent goods_id from being changed after creation
        static::updating(function ($item) {
            if ($item->isDirty('goods_id') && !empty($item->getOriginal('goods_id'))) {
                $item->goods_id = $item->getOriginal('goods_id'); // silently revert
            }
        });
    }

    // Relationships
    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    public function requestLeg()
    {
        return $this->belongsTo(RequestLeg::class);
    }

    public function truckingCompany()
    {
        return $this->belongsTo(TruckingCompany::class);
    }

    public function pricedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'priced_by');
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function trackingUpdates()
    {
        return $this->hasMany(TrackingUpdate::class)->orderBy('created_at', 'desc');
    }

    public function paymentLegs()
    {
        return $this->hasMany(\App\Models\Interstate\GoodsPaymentLeg::class, 'goods_item_id');
    }

    public function statusUpdates()
    {
        return $this->hasMany(GoodsStatusUpdate::class)->orderBy('update_timestamp', 'desc');
    }

    public function feeNotifications()
    {
        return $this->hasMany(GoodsFeeNotification::class)->orderBy('notified_at', 'desc');
    }

    // Scopes
    public function scopePendingPricing($query)
    {
        return $query->where('status', 'pending_pricing');
    }

    public function scopePriced($query)
    {
        return $query->where('status', 'priced');
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('trucking_company_id', $companyId);
    }

    public function scopeForRequest($query, $requestId)
    {
        return $query->where('request_id', $requestId);
    }

    public function scopeByGoodsId($query, string $goodsId)
    {
        return $query->where('goods_id', $goodsId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->whereHas('request', fn($q) => $q->where('user_id', $userId));
    }

    public function scopeWithActiveHandlers($query)
    {
        return $query->whereNotIn('status', ['delivered', 'cancelled']);
    }

    // Helper Methods
    public function calculateVolumetricWeight($divisor = 5000)
    {
        return ($this->length_cm * $this->width_cm * $this->height_cm) / $divisor;
    }

    public function getChargeableWeight()
    {
        return max($this->weight_kg, $this->volumetric_weight_kg);
    }

    public function calculateBasePrice($pricePerKg)
    {
        return $this->chargeable_weight_kg * $pricePerKg * $this->quantity;
    }

    public function calculateInsuranceFee($ratePercent)
    {
        $fee = $this->declared_value * ($ratePercent / 100);
        return max($fee, 0); // Apply minimum if needed at company level
    }

    public function calculateTotalPrice($pricePerKg, $insuranceRate)
    {
        $basePrice = $this->calculateBasePrice($pricePerKg);
        $insuranceFee = $this->requires_insurance ? $this->calculateInsuranceFee($insuranceRate) : 0;
        return $basePrice + $insuranceFee;
    }

    public function applyCompanyPricing($pricePerKg, $insuranceRate, $userId)
    {
        $basePrice = $this->calculateBasePrice($pricePerKg);
        $insuranceFee = $this->requires_insurance ? $this->calculateInsuranceFee($insuranceRate) : 0;
        $totalPrice = $basePrice + $insuranceFee;

        $this->update([
            'company_price_per_kg' => $pricePerKg,
            'company_base_price' => $basePrice,
            'company_insurance_rate' => $insuranceRate,
            'company_insurance_fee' => $insuranceFee,
            'company_total_price' => $totalPrice,
            'status' => 'priced',
            'priced_at' => now(),
            'priced_by' => $userId,
            'pricing_breakdown' => [
                'price_per_kg' => $pricePerKg,
                'chargeable_weight' => $this->chargeable_weight_kg,
                'quantity' => $this->quantity,
                'base_price_calculation' => "{$this->chargeable_weight_kg} kg × ₦{$pricePerKg} × {$this->quantity} qty = ₦{$basePrice}",
                'declared_value' => $this->declared_value,
                'insurance_rate' => $insuranceRate,
                'insurance_calculation' => $this->requires_insurance ? "₦{$this->declared_value} × {$insuranceRate}% = ₦{$insuranceFee}" : 'Not required',
                'total_price' => $totalPrice
            ]
        ]);

        return $this;
    }

    public function getCategoryLabel()
    {
        $labels = [
            'electronics' => 'Electronics',
            'fashion' => 'Fashion & Clothing',
            'food' => 'Food & Beverages',
            'documents' => 'Documents',
            'fragile' => 'Fragile Items',
            'general' => 'General Cargo',
            'perishable' => 'Perishable Goods',
            'hazardous' => 'Hazardous Materials'
        ];

        return $labels[$this->category] ?? 'Unknown';
    }

    /**
     * Record a handover to a new handler — appends to the immutable chain.
     */
    public function recordHandover(
        string $toType,
        int $toId,
        string $toName,
        int $recordedByUserId,
        string $note = ''
    ): void {
        $chain = $this->handover_chain ?? [];
        $chain[] = [
            'from_type'      => $this->current_handler_type,
            'from_id'        => $this->current_handler_id,
            'from_name'      => $this->current_handler_name,
            'to_type'        => $toType,
            'to_id'          => $toId,
            'to_name'        => $toName,
            'recorded_by'    => $recordedByUserId,
            'note'           => $note,
            'handover_at'    => now()->toIso8601String(),
        ];

        $this->update([
            'current_handler_type' => $toType,
            'current_handler_id'   => $toId,
            'current_handler_name' => $toName,
            'handover_chain'       => $chain,
            'status'               => 'in_transit',
        ]);
    }

    public function getStatusLabel()
    {
        $labels = [
            'pending_pricing' => 'Pending Pricing',
            'priced' => 'Priced',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'damaged' => 'Damaged',
            'lost' => 'Lost'
        ];

        return $labels[$this->status] ?? 'Unknown';
    }

    public function getStatusBadgeClass()
    {
        $classes = [
            'pending_pricing' => 'badge-warning',
            'priced' => 'badge-info',
            'in_transit' => 'badge-primary',
            'delivered' => 'badge-success',
            'damaged' => 'badge-danger',
            'lost' => 'badge-dark'
        ];

        return $classes[$this->status] ?? 'badge-secondary';
    }
}
