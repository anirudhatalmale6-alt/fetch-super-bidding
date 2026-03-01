<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Banner extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'image',
        'video',
        'video_url',
        'media_type',
        'button_text',
        'button_link',
        'position',
        'target_type',
        'sort_order',
        'is_active',
        'start_date',
        'end_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * Scope for active banners
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', Carbon::now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', Carbon::now());
            });
    }

    /**
     * Scope for position
     */
    public function scopePosition($query, $position)
    {
        return $query->where(function ($q) use ($position) {
            $q->where('position', $position)
              ->orWhere('position', 'both');
        });
    }

    /**
     * Scope for shop banners
     */
    public function scopeForShop($query)
    {
        return $query->position('shop');
    }

    /**
     * Scope for company store banners
     */
    public function scopeForCompanyStore($query)
    {
        return $query->position('company_store');
    }

    /**
     * Scope for company dashboard banners
     */
    public function scopeForCompanyDashboard($query)
    {
        return $query->position('company_dashboard');
    }

    /**
     * Scope for target type (homepage / company_dashboard)
     */
    public function scopeTargetType($query, $targetType)
    {
        return $query->where(function ($q) use ($targetType) {
            $q->where('target_type', $targetType)
              ->orWhere('target_type', 'both');
        });
    }

    /**
     * Scope for homepage banners
     */
    public function scopeForHomepage($query)
    {
        return $query->targetType('homepage');
    }

    /**
     * Get target type label
     */
    public function getTargetTypeLabelAttribute()
    {
        $labels = [
            'homepage' => 'Homepage',
            'company_dashboard' => 'Company Dashboard',
            'both' => 'Homepage & Dashboard',
        ];
        
        return $labels[$this->target_type] ?? $this->target_type;
    }

    /**
     * Get banner image URL or default
     */
    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : asset('images/default-banner.jpg');
    }

    /**
     * Check if banner is currently active based on dates
     */
    public function getIsCurrentlyActiveAttribute()
    {
        $now = Carbon::now();
        
        if (!$this->is_active) {
            return false;
        }
        
        if ($this->start_date && $now < $this->start_date) {
            return false;
        }
        
        if ($this->end_date && $now > $this->end_date) {
            return false;
        }
        
        return true;
    }

    /**
     * Get position label
     */
    public function getPositionLabelAttribute()
    {
        $labels = [
            'shop' => 'Shop Page',
            'company_store' => 'Company Store',
            'company_dashboard' => 'Company Dashboard',
            'both' => 'Shop & Store',
            'all' => 'All Pages',
        ];
        
        return $labels[$this->position] ?? $this->position;
    }

    /**
     * Get video URL (either uploaded or external)
     */
    public function getVideoUrlAttribute($value)
    {
        if ($this->video) {
            return asset('storage/' . $this->video);
        }
        return $value;
    }

    /**
     * Check if banner has video
     */
    public function getHasVideoAttribute()
    {
        return $this->media_type === 'video' || $this->video || ($this->video_url && !$this->image);
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        return $this->isCurrentlyActive ? 'Active' : 'Inactive';
    }

    /**
     * Get status badge class
     */
    public function getStatusClassAttribute()
    {
        return $this->isCurrentlyActive ? 'success' : 'danger';
    }
}
