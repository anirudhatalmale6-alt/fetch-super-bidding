<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;

class GoodsFeeNotification extends Model
{
    protected $fillable = [
        'goods_item_id',
        'user_id',
        'transportation_fee',
        'insurance_fee',
        'notified_at',
        'is_read',
        'read_at'
    ];

    protected $casts = [
        'transportation_fee' => 'decimal:2',
        'insurance_fee' => 'decimal:2',
        'notified_at' => 'datetime',
        'read_at' => 'datetime',
        'is_read' => 'boolean'
    ];

    // Relationships
    public function goodsItem()
    {
        return $this->belongsTo(GoodsItem::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Helpers
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    public function getTotalFee()
    {
        return $this->transportation_fee + $this->insurance_fee;
    }
}
