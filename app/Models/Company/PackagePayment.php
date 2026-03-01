<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class PackagePayment extends Model
{
    protected $table = 'company_package_payments';

    protected $fillable = [
        'goods_id',
        'user_id',
        'company_id',
        'cost_type',
        'amount',
        'status',
        'payment_method',
        'transaction_id',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Interstate\TruckingCompany::class, 'company_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    // Mark as paid
    public function markAsPaid(string $method = null, string $transactionId = null): void
    {
        $this->update([
            'status' => 'paid',
            'payment_method' => $method,
            'transaction_id' => $transactionId,
            'paid_at' => now(),
        ]);
    }
}
