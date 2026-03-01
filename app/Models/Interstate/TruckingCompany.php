<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class TruckingCompany extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_name',
        'slug',
        'registration_number',
        'email',
        'phone',
        'user_id',
        'logo',
        'status',
        'company_type',
        'banner_media',
        'banner_title',
        'banner_description',
        'show_shop_section',
        'commission_rate',
        'fleet_size',
        'service_types',
        'operating_states',
        'rating',
        'default_volumetric_divisor',
        'default_minimum_charge',
        'max_weight_per_package',
        'max_dimensions_cm',
    ];

    protected $casts = [
        'service_types' => 'array',
        'operating_states' => 'array',
        'max_dimensions_cm' => 'array',
        'commission_rate' => 'decimal:2',
        'rating' => 'decimal:1',
        'default_minimum_charge' => 'decimal:2',
        'max_weight_per_package' => 'decimal:2',
    ];

    /**
     * Boot method to set default values
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($company) {
            if (empty($company->slug)) {
                $company->slug = \Str::slug($company->company_name . '-' . uniqid());
            }
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hubs()
    {
        return $this->hasMany(TruckingHub::class);
    }

    public function routes()
    {
        return $this->hasMany(SupportedRoute::class);
    }

    public function banners()
    {
        return $this->hasMany(CompanyBanner::class)->ordered();
    }

    public function activeBanners()
    {
        return $this->hasMany(CompanyBanner::class)->active()->ordered();
    }

    public function goodsItems()
    {
        return $this->hasMany(GoodsItem::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVerified($query)
    {
        return $query->where('status', 'active')->where('rating', '>=', 4.0);
    }

    public function scopeInterstateTrucking($query)
    {
        return $query->whereIn('company_type', ['interstate_trucking', 'both']);
    }

    public function scopeLastMileDispatch($query)
    {
        return $query->whereIn('company_type', ['last_mile_dispatch', 'both']);
    }

    // Helper methods
    public function getActiveHubs()
    {
        return $this->hubs()->where('is_active', true)->get();
    }

    public function getActiveRoutes()
    {
        return $this->routes()->where('is_active', true)->get();
    }

    public function getMaxDimensionsArray(): array
    {
        return $this->max_dimensions_cm ?? [
            'length' => 200,
            'width' => 150,
            'height' => 150
        ];
    }
}

// TruckingHub Model
class TruckingHub extends Model
{
    protected $fillable = [
        'trucking_company_id',
        'hub_name',
        'hub_code',
        'hub_type',
        'address',
        'city',
        'state',
        'latitude',
        'longitude',
        'phone',
        'operating_hours',
        'daily_capacity',
        'is_active',
    ];

    protected $casts = [
        'operating_hours' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    public function truckingCompany()
    {
        return $this->belongsTo(TruckingCompany::class);
    }

    public function originRoutes()
    {
        return $this->hasMany(SupportedRoute::class, 'origin_hub_id');
    }

    public function destinationRoutes()
    {
        return $this->hasMany(SupportedRoute::class, 'destination_hub_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInCity($query, string $city)
    {
        return $query->where('city', 'LIKE', "%{$city}%");
    }

    public function getFullAddressAttribute(): string
    {
        return "{$this->address}, {$this->city}, {$this->state}";
    }
}
