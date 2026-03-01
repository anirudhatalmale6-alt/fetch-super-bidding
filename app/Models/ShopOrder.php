<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Interstate\TruckingCompany;
use App\Models\Request\Request;

class ShopOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'company_id',
        'user_id',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'delivery_fee',
        'total_amount',
        'payment_method',
        'payment_status',
        'transaction_reference',
        'paid_at',
        'bank_transfer_proof',
        'bank_transfer_submitted_at',
        'status',
        'delivery_type',
        'delivery_status',
        'delivery_contact_name',
        'delivery_contact_phone',
        'delivery_address',
        'delivery_lat',
        'delivery_lng',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'origin_hub_id',
        'destination_hub_id',
        'delivery_notes',
        'admin_notes',
        'logistics_request_id',
        'estimated_delivery_at',
        'actual_delivered_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'delivery_lat' => 'decimal:8',
        'delivery_lng' => 'decimal:8',
        'pickup_lat' => 'decimal:8',
        'pickup_lng' => 'decimal:8',
        'paid_at' => 'datetime',
        'bank_transfer_submitted_at' => 'datetime',
        'estimated_delivery_at' => 'datetime',
        'actual_delivered_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    // Payment status constants
    const PAYMENT_PENDING = 'pending';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_FAILED = 'failed';
    const PAYMENT_REFUNDED = 'refunded';

    // Relationships
    public function company()
    {
        return $this->belongsTo(TruckingCompany::class, 'company_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(ShopOrderItem::class);
    }

    public function logisticsRequest()
    {
        return $this->belongsTo(Request::class, 'logistics_request_id');
    }

    public function originHub()
    {
        return $this->belongsTo(\App\Models\Interstate\TruckingHub::class, 'origin_hub_id');
    }

    public function destinationHub()
    {
        return $this->belongsTo(\App\Models\Interstate\TruckingHub::class, 'destination_hub_id');
    }

    // Scopes
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentStatus($query, string $status)
    {
        return $query->where('payment_status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_PAID);
    }

    // Helpers
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function markAsPaid(string $transactionReference = null): void
    {
        $this->update([
            'payment_status' => self::PAYMENT_PAID,
            'transaction_reference' => $transactionReference,
            'paid_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'payment_status' => self::PAYMENT_FAILED,
        ]);
    }

    public function updateStatus(string $status): void
    {
        $this->update(['status' => $status]);
    }

    // Generate unique order number
    public static function generateOrderNumber(): string
    {
        $prefix = 'SHOP';
        $year = date('Y');
        $random = strtoupper(\Str::random(6));
        return "{$prefix}-{$year}-{$random}";
    }
}
