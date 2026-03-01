<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;

class GoodsStatusUpdate extends Model
{
    protected $fillable = [
        'goods_item_id',
        'trucking_company_id',
        'status_type',
        'message',
        'location_data',
        'update_timestamp',
        'created_by'
    ];

    protected $casts = [
        'location_data' => 'array',
        'update_timestamp' => 'datetime'
    ];

    // Relationships
    public function goodsItem()
    {
        return $this->belongsTo(GoodsItem::class);
    }

    public function truckingCompany()
    {
        return $this->belongsTo(TruckingCompany::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // Scopes
    public function scopeForGoods($query, $goodsItemId)
    {
        return $query->where('goods_item_id', $goodsItemId);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('update_timestamp', 'desc');
    }

    // Helpers
    public function getLocationString()
    {
        if (!$this->location_data) return null;
        return $this->location_data['address'] ?? null;
    }
}
