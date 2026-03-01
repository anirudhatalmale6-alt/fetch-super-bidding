<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;
use App\Models\Request\Request;

class TrackingUpdate extends Model
{
    protected $fillable = [
        'request_id',
        'request_package_id',
        'message',
        'update_type',
        'location_name',
        'latitude',
        'longitude',
        'image_url',
        'attachments',
        'created_by_type',
        'created_by_id',
        'created_by_name',
        'previous_status',
        'new_status',
        'user_notified_at',
        'is_customer_visible',
        'metadata',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'attachments' => 'array',
        'metadata' => 'array',
        'user_notified_at' => 'datetime',
        'is_customer_visible' => 'boolean',
    ];

    // Relationships
    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function package()
    {
        return $this->belongsTo(RequestPackage::class, 'request_package_id');
    }

    // Scopes
    public function scopeVisibleToCustomer($query)
    {
        return $query->where('is_customer_visible', true);
    }

    public function scopeForRequest($query, string $requestId)
    {
        return $query->where('request_id', $requestId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('update_type', $type);
    }

    public function scopeRecent($query, int $limit = 20)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    // Helper methods
    public function markAsNotified(): void
    {
        $this->update(['user_notified_at' => now()]);
    }

    public function isStatusChange(): bool
    {
        return $this->update_type === 'status_change';
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function hasAttachments(): bool
    {
        return !empty($this->attachments) || !empty($this->image_url);
    }

    public function getFormattedTimeAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    // Static factory methods
    public static function createStatusChange(
        string $requestId,
        string $previousStatus,
        string $newStatus,
        string $message,
        ?string $packageId = null,
        ?int $createdById = null,
        string $createdByType = 'system'
    ): self {
        return self::create([
            'request_id' => $requestId,
            'request_package_id' => $packageId,
            'update_type' => 'status_change',
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'message' => $message,
            'created_by_type' => $createdByType,
            'created_by_id' => $createdById,
        ]);
    }

    public static function createLocationUpdate(
        string $requestId,
        string $locationName,
        float $latitude,
        float $longitude,
        string $message,
        ?string $packageId = null
    ): self {
        return self::create([
            'request_id' => $requestId,
            'request_package_id' => $packageId,
            'update_type' => 'location_update',
            'location_name' => $locationName,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'message' => $message,
        ]);
    }

    public static function createInspectionNote(
        string $requestId,
        string $message,
        ?string $imageUrl = null,
        ?string $packageId = null,
        ?int $createdById = null
    ): self {
        return self::create([
            'request_id' => $requestId,
            'request_package_id' => $packageId,
            'update_type' => 'inspection_note',
            'message' => $message,
            'image_url' => $imageUrl,
            'created_by_type' => 'trucking_company',
            'created_by_id' => $createdById,
        ]);
    }
}
