<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;
use App\Models\Request\Request;
use App\Models\User;
use App\Models\Interstate\TruckingHub;

class InspectionPhoto extends Model
{
    protected $fillable = [
        'request_id',
        'request_package_id',
        'photo_url',
        'photo_type',
        'description',
        'recorded_weight',
        'recorded_length',
        'recorded_width',
        'recorded_height',
        'taken_by_id',
        'taken_by_name',
        'hub_id',
        'latitude',
        'longitude',
        'taken_at',
    ];

    protected $casts = [
        'recorded_weight' => 'decimal:2',
        'recorded_length' => 'decimal:2',
        'recorded_width' => 'decimal:2',
        'recorded_height' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'taken_at' => 'datetime',
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

    public function takenBy()
    {
        return $this->belongsTo(User::class, 'taken_by_id');
    }

    public function hub()
    {
        return $this->belongsTo(TruckingHub::class, 'hub_id');
    }

    // Scopes
    public function scopeForRequest($query, string $requestId)
    {
        return $query->where('request_id', $requestId);
    }

    public function scopeForPackage($query, int $packageId)
    {
        return $query->where('request_package_id', $packageId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('photo_type', $type);
    }

    public function scopeWeightMeasurements($query)
    {
        return $query->where('photo_type', 'weight_measurement');
    }

    public function scopeDimensionChecks($query)
    {
        return $query->where('photo_type', 'dimension_check');
    }

    // Helper methods
    public function hasMeasurements(): bool
    {
        return $this->recorded_weight !== null ||
               $this->recorded_length !== null ||
               $this->recorded_width !== null ||
               $this->recorded_height !== null;
    }

    public function getDimensionsAttribute(): ?array
    {
        if (!$this->hasMeasurements()) {
            return null;
        }

        return [
            'weight' => $this->recorded_weight,
            'length' => $this->recorded_length,
            'width' => $this->recorded_width,
            'height' => $this->recorded_height,
        ];
    }

    public static function recordMeasurement(
        string $requestId,
        int $packageId,
        string $photoUrl,
        string $photoType,
        array $measurements,
        int $takenById,
        string $takenByName,
        ?int $hubId = null,
        ?string $description = null
    ): self {
        return self::create([
            'request_id' => $requestId,
            'request_package_id' => $packageId,
            'photo_url' => $photoUrl,
            'photo_type' => $photoType,
            'description' => $description,
            'recorded_weight' => $measurements['weight'] ?? null,
            'recorded_length' => $measurements['length'] ?? null,
            'recorded_width' => $measurements['width'] ?? null,
            'recorded_height' => $measurements['height'] ?? null,
            'taken_by_id' => $takenById,
            'taken_by_name' => $takenByName,
            'hub_id' => $hubId,
            'taken_at' => now(),
        ]);
    }
}
