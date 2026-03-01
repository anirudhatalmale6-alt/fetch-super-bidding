<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;

class CompanyBanner extends Model
{
    protected $fillable = [
        'trucking_company_id',
        'title',
        'description',
        'media_type',
        'media_url',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    // Relationships
    public function truckingCompany()
    {
        return $this->belongsTo(TruckingCompany::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    // Helpers
    public function isImage()
    {
        return $this->media_type === 'image';
    }

    public function isVideo()
    {
        return $this->media_type === 'video';
    }
}
