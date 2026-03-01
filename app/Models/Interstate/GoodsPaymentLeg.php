<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;

class GoodsPaymentLeg extends Model
{
    protected $table = 'goods_payment_legs';

    protected $fillable = [
        'goods_id',
        'goods_item_id',
        'request_id',
        'payer_user_id',
        'handler_type',
        'handler_id',
        'handler_name',
        'leg_type',
        'amount',
        'amount_paid',
        'payment_status',
        'payment_reference',
        'payment_channel',
        'payment_metadata',
        'payment_confirmed_at',
        'confirmed_by',
        'is_unlocked',
        'notified_user',
        'user_notified_at',
    ];

    protected $casts = [
        'amount'               => 'decimal:2',
        'amount_paid'          => 'decimal:2',
        'payment_metadata'     => 'array',
        'payment_confirmed_at' => 'datetime',
        'user_notified_at'     => 'datetime',
        'is_unlocked'          => 'boolean',
        'notified_user'        => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function goodsItem()
    {
        return $this->belongsTo(GoodsItem::class, 'goods_item_id');
    }

    public function payer()
    {
        return $this->belongsTo(\App\Models\User::class, 'payer_user_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeForGoods($query, string $goodsId)
    {
        return $query->where('goods_id', $goodsId);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function getBalanceDueAttribute(): float
    {
        return max(0, $this->amount - $this->amount_paid);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function confirmPayment(string $reference, string $channel = 'paystack', ?int $confirmedBy = null): void
    {
        $this->update([
            'payment_status'       => 'paid',
            'amount_paid'          => $this->amount,
            'payment_reference'    => $reference,
            'payment_channel'      => $channel,
            'payment_confirmed_at' => now(),
            'confirmed_by'         => $confirmedBy,
        ]);
    }

    /**
     * Leg type labels for display.
     */
    public function getLegTypeLabel(): string
    {
        return match ($this->leg_type) {
            'pickup_fee'          => 'Pickup Fee',
            'interstate_transport'=> 'Interstate Transport',
            'insurance'           => 'Insurance',
            'handling'            => 'Handling Fee',
            'final_delivery'      => 'Final Delivery',
            default               => ucwords(str_replace('_', ' ', $this->leg_type)),
        };
    }
}
