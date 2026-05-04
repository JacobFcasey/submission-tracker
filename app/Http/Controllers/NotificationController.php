<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $this->isAdmin($user);
        $perPage = (int) $request->get('per_page', 20);
        if (!in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 20;
        }

        $selectedUser = null;
        if ($request->filled('user_id') && $isAdmin) {
            $selectedUser = User::find($request->user_id);
        }

        // Build base query: selected user's notifications for admin, otherwise current user
        $query = ($selectedUser ? $selectedUser->notifications() : $user->notifications())->latest();

        // Apply filters
        if ($request->filled('type')) {
            $query->where('data->type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('data->message', 'like', "%{$search}%")
                    ->orWhere('data->municipality_name', 'like', "%{$search}%")
                    ->orWhere('data->company_name', 'like', "%{$search}%")
                    ->orWhere('data->user_name', 'like', "%{$search}%")
                    ->orWhere('data->assigned_user_name', 'like', "%{$search}%");
            });
        }

        // Filter by read/unread status
        if ($request->filled('read_status')) {
            if ($request->read_status === 'unread') {
                $query->whereNull('read_at');
            } elseif ($request->read_status === 'read') {
                $query->whereNotNull('read_at');
            }
        }

        $notifications = $query->paginate($perPage)->withQueryString();

        // Enhance notification data
        $enhancedNotifications = $notifications->through(function ($notification) {
            $data = $notification->data;

            // Fix days calculation to use whole days
            if (isset($data['deadline_date'])) {
                $deadlineDate = Carbon::parse($data['deadline_date']);
                $data['days_until_deadline'] = now()->startOfDay()->diffInDays($deadlineDate, false);

                // Ensure negative days are displayed as absolute values
                if ($data['days_until_deadline'] < 0) {
                    $data['days_overdue'] = abs($data['days_until_deadline']);
                }
            }

            // Add formatted date
            if (isset($data['deadline_date'])) {
                $data['formatted_deadline'] = Carbon::parse($data['deadline_date'])
                    ->format('M d, Y');
            }

            // Add assignment information if available
            if (isset($data['assigned_user_id']) && isset($data['company_count'])) {
                if (isset($data['all_companies_assigned']) && $data['all_companies_assigned']) {
                    $data['assignment_summary'] = 'All ' . $data['company_count'] . ' companies assigned to ' .
                        ($data['assigned_user_name'] ?? 'user');
                } else {
                    $data['assignment_summary'] = 'Multiple users assigned';
                }
            }

            // Add read status
            $data['is_read'] = $notification->read_at !== null;
            $notification->setAttribute('data', $data);

            return $notification;
        });

        return Inertia::render('Notifications/Index', [
            'notifications' => $enhancedNotifications,
            'unreadCount' => $user->unreadNotifications()->count(),
            'users' => $isAdmin ? User::where('is_active', true)->orderBy('name')->get() : [],
            'selectedUserId' => $selectedUser?->id,
            'selectedUserName' => $selectedUser?->name,
            'isAdmin' => $isAdmin,
            'filters' => $request->only(['type', 'search', 'user_id', 'per_page', 'read_status']),
        ]);
    }

    public function markAsRead($id)
    {
        $user = Auth::user();
        $isAdmin = $this->isAdmin($user);

        if ($isAdmin) {
            // Admin can mark any notification as read
            $notification = DB::table('notifications')->where('id', $id)->first();

            if ($notification) {
                // Check if admin is allowed to manage this user's notifications
                $notificationUser = User::find($notification->notifiable_id);
                if (!$notificationUser) {
                    return redirect()->back()->with('error', 'Notification user could not be found.');
                }

                // Only allow if admin has permission to manage users or it's their own notification
                if ($user->can('manage users') || $notificationUser->id === $user->id) {
                    DB::table('notifications')
                        ->where('id', $id)
                        ->update(['read_at' => now()]);

                    return redirect()->back()->with('success', 'Notification marked as read.');
                }
            }

            return redirect()->back()->with('error', 'You are not authorized to mark this notification as read.');
        } else {
            // User can only mark their own notifications as read
            $notification = $user->notifications()->findOrFail($id);
            $notification->markAsRead();

            return redirect()->back()->with('success', 'Notification marked as read.');
        }
    }

    public function markAllAsRead()
    {
        $user = Auth::user();
        $isAdmin = $this->isAdmin($user);

        if ($isAdmin) {
            // Admin with permission can mark all notifications as read
            if ($user->can('manage users')) {
                DB::table('notifications')
                    ->where('notifiable_type', 'App\Models\User')
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);

                return redirect()->back()->with('success', 'All notifications marked as read.');
            } else {
                // Admin without manage users permission can only mark their own
                $user->unreadNotifications->markAsRead();
                return redirect()->back()->with('success', 'Your notifications marked as read.');
            }
        } else {
            // Regular user can only mark their own notifications as read
            $user->unreadNotifications->markAsRead();
            return redirect()->back()->with('success', 'All your notifications marked as read.');
        }
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $isAdmin = $this->isAdmin($user);

        if ($isAdmin) {
            // Admin can delete any notification (with permission check)
            $notification = DB::table('notifications')->where('id', $id)->first();

            if ($notification && $user->can('manage users')) {
                DB::table('notifications')->where('id', $id)->delete();
                return redirect()->back()->with('success', 'Notification deleted.');
            }

            return redirect()->back()->with('error', 'You are not authorized to delete this notification.');
        } else {
            // User can only delete their own notifications
            $notification = $user->notifications()->findOrFail($id);
            $notification->delete();

            return redirect()->back()->with('success', 'Notification deleted.');
        }
    }

    public function clearAll()
    {
        $user = Auth::user();
        $isAdmin = $this->isAdmin($user);

        if ($isAdmin) {
            // Admin with permission can clear all notifications
            if ($user->can('manage users')) {
                DB::table('notifications')->where('notifiable_type', 'App\Models\User')->delete();
                return redirect()->back()->with('success', 'All notifications cleared.');
            } else {
                // Admin without permission can only clear their own
                $user->notifications()->delete();
                return redirect()->back()->with('success', 'Your notifications cleared.');
            }
        } else {
            // Regular user can only clear their own notifications
            $user->notifications()->delete();
            return redirect()->back()->with('success', 'Your notifications cleared.');
        }
    }

    /**
     * Get notification summary for dashboard/header
     */
    public function summary(Request $request)
    {
        $user = $request->user();

        $unreadCount = $user->unreadNotifications()->count();

        // Get recent unread notifications
        $recentNotifications = $user->unreadNotifications()
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($notification) {
                $data = $notification->data;

                // Fix days calculation
                if (isset($data['deadline_date'])) {
                    $deadlineDate = Carbon::parse($data['deadline_date']);
                    $data['days_until'] = now()->startOfDay()->diffInDays($deadlineDate, false);

                    if ($data['days_until'] < 0) {
                        $data['is_overdue'] = true;
                        $data['days_overdue'] = abs($data['days_until']);
                    }
                }

                return [
                    'id' => $notification->id,
                    'type' => $data['type'] ?? 'general',
                    'message' => $data['message'] ?? 'Notification',
                    'created_at' => $notification->created_at,
                    'formatted_date' => Carbon::parse($notification->created_at)->diffForHumans(),
                    'data' => $data
                ];
            });

        return response()->json([
            'unread_count' => $unreadCount,
            'recent_notifications' => $recentNotifications
        ]);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole(['admin', 'super-admin', 'superadmin']) || (bool) $user->is_admin;
    }
}
