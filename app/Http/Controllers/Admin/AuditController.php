<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['user_id', 'event', 'date_from', 'date_to', 'search', 'per_page']);
        $perPage = (int) ($filters['per_page'] ?? 20);
        if (!in_array($perPage, [20, 50, 100], true)) {
            $perPage = 20;
        }

        $audits = Audit::query()
            ->with('user:id,name,email')
            ->when($filters['user_id'] ?? null, function ($query, $userId) {
                $query->where('user_id', $userId);
            })
            ->when($filters['event'] ?? null, function ($query, $event) {
                $query->where('event', $event);
            })
            ->when($filters['date_from'] ?? null, function ($query, $date) {
                $query->where('created_at', '>=', $date);
            })
            ->when($filters['date_to'] ?? null, function ($query, $date) {
                $query->where('created_at', '<=', $date);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('auditable_type', 'like', '%' . $search . '%')
                        ->orWhere('url', 'like', '%' . $search . '%')
                        ->orWhere('event', 'like', '%' . $search . '%')
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%');
                        });
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Audits/Index', [
            'audits' => $audits,
            'filters' => $filters,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'eventTypes' => Audit::query()->select('event')->distinct()->orderBy('event')->pluck('event')->values(),
            'perPageOptions' => [20, 50, 100],
        ]);
    }

    public function show($id)
    {
        $audit = Audit::query()
            ->with('user:id,name,email')
            ->findOrFail($id);

        return Inertia::render('Admin/Audits/Show', [
            'audit' => [
                'id' => $audit->id,
                'event' => $audit->event,
                'auditable_type' => $audit->auditable_type,
                'auditable_id' => $audit->auditable_id,
                'auditable_label' => $audit->auditable_label,
                'changes_count' => $audit->changes_count,
                'old_values' => $audit->old_values ?? [],
                'new_values' => $audit->new_values ?? [],
                'url' => $audit->url,
                'ip_address' => $audit->ip_address,
                'user_agent' => $audit->user_agent,
                'tags' => $audit->tags,
                'created_at' => optional($audit->created_at)?->toISOString(),
                'user' => $audit->user ? [
                    'id' => $audit->user->id,
                    'name' => $audit->user->name,
                    'email' => $audit->user->email,
                ] : null,
            ],
        ]);
    }
}
