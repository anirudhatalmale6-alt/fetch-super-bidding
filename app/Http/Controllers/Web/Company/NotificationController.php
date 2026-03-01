<?php

namespace App\Http\Controllers\Web\Company;

use App\Http\Controllers\Web\BaseController;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends BaseController
{
    /**
     * Display all notifications
     */
    public function index()
    {
        $owner = auth()->user()->owner;
        
        $notifications = Notification::where('owner_id', $owner->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Mark all as read
        Notification::where('owner_id', $owner->id)
            ->where('read', false)
            ->update(['read' => true]);

        return view('company.notifications.index', compact('notifications'));
    }

    /**
     * Get unread notifications count (AJAX)
     */
    public function unreadCount()
    {
        $owner = auth()->user()->owner;
        
        $count = Notification::where('owner_id', $owner->id)
            ->where('read', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Get recent notifications for dropdown (AJAX)
     */
    public function recent()
    {
        $owner = auth()->user()->owner;
        
        $notifications = Notification::where('owner_id', $owner->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json($notifications);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($id)
    {
        $owner = auth()->user()->owner;
        
        Notification::where('id', $id)
            ->where('owner_id', $owner->id)
            ->update(['read' => true]);

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        $owner = auth()->user()->owner;
        
        Notification::where('owner_id', $owner->id)
            ->where('read', false)
            ->update(['read' => true]);

        return response()->json(['success' => true]);
    }

    /**
     * Delete notification
     */
    public function destroy($id)
    {
        $owner = auth()->user()->owner;
        
        Notification::where('id', $id)
            ->where('owner_id', $owner->id)
            ->delete();

        return redirect()->route('company.notifications.index')
            ->with('success', 'Notification deleted.');
    }

    /**
     * Show notification preferences
     */
    public function preferences()
    {
        $owner = auth()->user()->owner;
        $settings = json_decode($owner->settings ?? '{}', true);
        
        return view('company.notifications.preferences', compact('settings'));
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(Request $request)
    {
        $owner = auth()->user()->owner;
        
        $validated = $request->validate([
            'email_bid_updates' => 'boolean',
            'email_shipment_updates' => 'boolean',
            'email_order_updates' => 'boolean',
            'sms_bid_updates' => 'boolean',
            'sms_shipment_updates' => 'boolean',
            'push_bid_updates' => 'boolean',
            'push_shipment_updates' => 'boolean',
            'push_order_updates' => 'boolean',
        ]);

        $settings = json_decode($owner->settings ?? '{}', true);
        $settings = array_merge($settings, $validated);
        
        $owner->update(['settings' => json_encode($settings)]);

        return redirect()->route('company.notifications.preferences')
            ->with('success', 'Preferences updated successfully.');
    }
}
