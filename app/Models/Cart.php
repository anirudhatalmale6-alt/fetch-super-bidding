<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Interstate\TruckingCompany;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'product_id',
        'quantity',
        'unit_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(TruckingCompany::class, 'company_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Scopes
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // Helpers
    public function getTotalPriceAttribute(): float
    {
        return (float) $this->unit_price * $this->quantity;
    }

    // Static methods
    public static function getCartTotal(int $companyId): float
    {
        return self::forCompany($companyId)
            ->with('product')
            ->get()
            ->sum(function ($item) {
                return $item->total_price;
            });
    }

    public static function getCartCount(int $companyId): int
    {
        return self::forCompany($companyId)->sum('quantity');
    }

    public static function clearCart(int $companyId): void
    {
        self::forCompany($companyId)->delete();
    }
}
