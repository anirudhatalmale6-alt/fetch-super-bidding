<?php

namespace App\Models\Interstate;

use Illuminate\Database\Eloquent\Model;

class CompanyNotification extends Model
{
    protected $fillable = [
        'owner_id',
        'trucking_company_id',
        'title',
        'message',
        'type',
        'is_read',
        'link',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Get the owner that owns this notification
     */
    public function owner()
    {
        return $this->belongsTo(\App\Models\Admin\Owner::class, 'owner_id');
    }

    /**
     * Get the company this notification belongs to
     */
    public function company()
    {
        return $this->belongsTo(TruckingCompany::class, 'trucking_company_id');
    }

    /**
     * Scope to get unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope to get notifications for a specific owner
     */
    public function scopeForOwner($query, $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    /**
     * Scope to get notifications for a specific company
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('trucking_company_id', $companyId);
    }

    /**
     * Scope to get recent notifications
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Check if notification is read
     */
    public function isRead()
    {
        return $this->is_read || $this->read_at !== null;
    }
}
