<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Package extends Model
{
    protected $table = 'company_packages';

    protected $fillable = [
        'goods_id',
        'user_id',
        'company_id',
        'driver_id',
        'origin',
        'destination',
        'origin_address',
        'destination_address',
        'status',
        'insurance_cost',
        'transportation_cost',
        'total_cost',
        'description',
        'weight_kg',
        'dimensions',
        'tracking_notes',
    ];

    protected $casts = [
        'insurance_cost' => 'decimal:2',
        'transportation_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'weight_kg' => 'decimal:2',
        'tracking_notes' => 'array',
    ];

    /**
     * Auto-generate goods_id on creation
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($package) {
            if (empty($package->goods_id)) {
                $package->goods_id = 'PKG-' . date('Ymd') . '-' . strtoupper(uniqid());
            }
            if (empty($package->status)) {
                $package->status = 'awaiting_pickup';
            }
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Interstate\TruckingCompany::class, 'company_id');
    }

    public function driver()
    {
        return $this->belongsTo(\App\Models\Admin\Driver::class, 'driver_id');
    }

    public function trackingLogs()
    {
        return $this->hasMany(PackageTracking::class, 'goods_id', 'goods_id')->orderBy('created_at', 'desc');
    }

    public function payments()
    {
        return $this->hasMany(PackagePayment::class, 'goods_id', 'goods_id')->orderBy('created_at', 'desc');
    }

    // Scopes
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByGoodsId($query, string $goodsId)
    {
        return $query->where('goods_id', $goodsId);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['awaiting_pickup', 'in_transit']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'delivered');
    }

    // Status helpers
    public function getStatusLabel()
    {
        $labels = [
            'awaiting_pickup' => 'Awaiting Pickup',
            'picked_up' => 'Picked Up',
            'in_transit' => 'In Transit',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusBadgeClass()
    {
        $classes = [
            'awaiting_pickup' => 'badge-warning',
            'picked_up' => 'badge-info',
            'in_transit' => 'badge-primary',
            'out_for_delivery' => 'badge-info',
            'delivered' => 'badge-success',
            'cancelled' => 'badge-danger',
        ];
        return $classes[$this->status] ?? 'badge-secondary';
    }

    // Add tracking note
    public function addNote(string $note, int $adminId): void
    {
        $notes = $this->tracking_notes ?? [];
        $notes[] = [
            'note' => $note,
            'added_by' => $adminId,
            'added_at' => now()->toIso8601String(),
        ];
        $this->update(['tracking_notes' => $notes]);
    }

    // Update costs and log
    public function updateCosts(?float $insurance = null, ?float $transport = null, int $adminId): void
    {
        $changes = [];

        if ($insurance !== null && $insurance != $this->insurance_cost) {
            $changes['insurance'] = [
                'old' => $this->insurance_cost,
                'new' => $insurance,
            ];
            $this->insurance_cost = $insurance;
        }

        if ($transport !== null && $transport != $this->transportation_cost) {
            $changes['transport'] = [
                'old' => $this->transportation_cost,
                'new' => $transport,
            ];
            $this->transportation_cost = $transport;
        }

        if (!empty($changes)) {
            $this->total_cost = ($this->insurance_cost ?? 0) + ($this->transportation_cost ?? 0);
            $this->save();

            // Log the change
            PackageTracking::create([
                'goods_id' => $this->goods_id,
                'company_id' => $this->company_id,
                'note' => 'Costs updated: ' . json_encode($changes),
                'cost_added' => ($this->insurance_cost ?? 0) + ($this->transportation_cost ?? 0),
                'created_by_admin_id' => $adminId,
            ]);
        }
    }

    // Check if payment is required
    public function requiresPayment(): bool
    {
        return ($this->total_cost ?? 0) > 0;
    }

    public function getPendingPayments()
    {
        return $this->payments()->where('status', 'pending')->get();
    }

    public function isFullyPaid(): bool
    {
        $paid = $this->payments()->where('status', 'paid')->sum('amount');
        return $paid >= ($this->total_cost ?? 0);
    }
}
