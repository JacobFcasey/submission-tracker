<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Municipality;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\Uploads;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isStaff = $user->hasRole(['admin', 'super-admin', 'superadmin', 'manager']);

        $query = SupportTicket::with([
            'creator:id,name,email,employee_number',
            'assignee:id,name',
            'company:id,name',
            'municipality:id,name',
            'upload:id,reference,status',
            'latestMessage',
        ])->forUser($user);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('reference', 'like', "%{$s}%")
                  ->orWhere('subject', 'like', "%{$s}%")
                  ->orWhereHas('company', fn($c) => $c->where('name', 'like', "%{$s}%"))
                  ->orWhereHas('creator', fn($c) => $c->where('name', 'like', "%{$s}%"));
            });
        }

        $tickets = $query->orderByRaw("CASE WHEN status IN ('open','in_progress','waiting_on_casey') THEN 0 ELSE 1 END")
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'open' => SupportTicket::forUser($user)->where('status', 'open')->count(),
            'in_progress' => SupportTicket::forUser($user)->where('status', 'in_progress')->count(),
            'waiting' => SupportTicket::forUser($user)->whereIn('status', ['waiting_on_company', 'waiting_on_casey'])->count(),
            'resolved' => SupportTicket::forUser($user)->whereIn('status', ['resolved', 'closed'])->count(),
        ];

        return Inertia::render('Support/Index', [
            'tickets' => $tickets,
            'stats' => $stats,
            'filters' => $request->only(['status', 'priority', 'category', 'search']),
            'isStaff' => $isStaff,
            'companies' => $isStaff ? Company::orderBy('name')->get(['id', 'name']) : [],
            'municipalities' => $isStaff ? Municipality::orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $isStaff = $user->hasRole(['admin', 'super-admin', 'superadmin', 'manager']);

        // Pre-fill from linked upload if provided
        $linkedUpload = null;
        if ($request->filled('upload_id')) {
            $linkedUpload = Uploads::with(['company:id,name', 'municipality:id,name'])->find($request->upload_id);
        }

        $companies = $isStaff
            ? Company::orderBy('name')->get(['id', 'name'])
            : Company::whereIn('id', $user->assignments()->pluck('company_id'))->orderBy('name')->get(['id', 'name']);

        $municipalities = $isStaff
            ? Municipality::orderBy('name')->get(['id', 'name'])
            : Municipality::whereIn('id', $user->assignments()->pluck('municipality_id'))->orderBy('name')->get(['id', 'name']);

        $uploads = Uploads::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'reference', 'status', 'company_id', 'municipality_id']);

        $staffUsers = $isStaff
            ? User::whereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'super-admin', 'manager']))
                ->orderBy('name')->get(['id', 'name', 'email'])
            : [];

        return Inertia::render('Support/Create', [
            'companies' => $companies,
            'municipalities' => $municipalities,
            'uploads' => $uploads,
            'staffUsers' => $staffUsers,
            'linkedUpload' => $linkedUpload,
            'isStaff' => $isStaff,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'priority' => 'required|in:low,medium,high,urgent',
            'category' => 'required|in:upload_query,verification_issue,deadline_query,account_issue,data_correction,general',
            'company_id' => 'nullable|exists:companies,id',
            'municipality_id' => 'nullable|exists:municipalities,id',
            'upload_id' => 'nullable|exists:uploads,id',
            'assigned_to' => 'nullable|exists:users,id',
            'attachments.*' => 'nullable|file|max:5120',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($request, $user) {
            $ticket = SupportTicket::create([
                'reference' => 'TKT-' . strtoupper(Str::random(8)),
                'subject' => $request->subject,
                'status' => 'open',
                'priority' => $request->priority,
                'category' => $request->category,
                'company_id' => $request->company_id,
                'municipality_id' => $request->municipality_id,
                'upload_id' => $request->upload_id,
                'created_by' => $user->id,
                'assigned_to' => $request->assigned_to,
                'last_message_at' => now(),
                'message_count' => 1,
                'unread_casey' => $this->isStaff($user) ? 0 : 1,
                'unread_company' => $this->isStaff($user) ? 1 : 0,
            ]);

            $attachments = $this->storeAttachments($request, $ticket);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'body' => $request->body,
                'attachments' => $attachments,
                'is_internal' => false,
            ]);
        });

        return redirect()->route('support.index')
            ->with('success', 'Ticket created successfully.');
    }

    public function show(Request $request, SupportTicket $ticket)
    {
        $user = $request->user();
        $isStaff = $this->isStaff($user);

        // Access check: staff can see all, users only their own
        if (!$isStaff && $ticket->created_by !== $user->id) {
            abort(403);
        }

        // Mark messages as read
        if ($isStaff) {
            $ticket->update(['unread_casey' => 0]);
        } else {
            $ticket->update(['unread_company' => 0]);
        }

        $ticket->load([
            'creator:id,name,email,employee_number',
            'assignee:id,name,email',
            'company:id,name',
            'municipality:id,name',
            'upload:id,reference,status,company_id,municipality_id',
        ]);

        $messagesQuery = $ticket->messages()->with('user:id,name,email')->orderBy('created_at');

        // Non-staff users can't see internal notes
        if (!$isStaff) {
            $messagesQuery->where('is_internal', false);
        }

        $messages = $messagesQuery->get();

        $staffUsers = $isStaff
            ? User::whereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'super-admin', 'manager']))
                ->orderBy('name')->get(['id', 'name', 'email'])
            : [];

        return Inertia::render('Support/Show', [
            'ticket' => $ticket,
            'messages' => $messages,
            'isStaff' => $isStaff,
            'staffUsers' => $staffUsers,
            'currentUserId' => $user->id,
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $request->validate([
            'body' => 'required|string|max:5000',
            'is_internal' => 'nullable|boolean',
            'attachments.*' => 'nullable|file|max:5120',
        ]);

        $user = $request->user();
        $isStaff = $this->isStaff($user);

        if (!$isStaff && $ticket->created_by !== $user->id) {
            abort(403);
        }

        $attachments = $this->storeAttachments($request, $ticket);
        $isInternal = $isStaff && $request->boolean('is_internal');

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => $request->body,
            'attachments' => $attachments,
            'is_internal' => $isInternal,
        ]);

        $updates = [
            'last_message_at' => now(),
            'message_count' => $ticket->messages()->count(),
        ];

        // Update status based on who replied
        if (!$isInternal) {
            if ($isStaff) {
                $updates['status'] = 'waiting_on_company';
                $updates['unread_company'] = $ticket->unread_company + 1;
                $updates['unread_casey'] = 0;
            } else {
                if ($ticket->status === 'waiting_on_company' || $ticket->status === 'open') {
                    $updates['status'] = 'waiting_on_casey';
                }
                $updates['unread_casey'] = $ticket->unread_casey + 1;
                $updates['unread_company'] = 0;
            }
        }

        $ticket->update($updates);

        return back()->with('success', 'Reply sent.');
    }

    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $request->validate([
            'status' => 'required|in:open,in_progress,waiting_on_company,waiting_on_casey,resolved,closed',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $updates = ['status' => $request->status];

        if ($request->filled('assigned_to')) {
            $updates['assigned_to'] = $request->assigned_to;
        }

        if (in_array($request->status, ['resolved'])) {
            $updates['resolved_at'] = now();
        }
        if ($request->status === 'closed') {
            $updates['closed_at'] = now();
        }

        $ticket->update($updates);

        return back()->with('success', 'Ticket updated.');
    }

    public function downloadAttachment(SupportTicket $ticket, TicketMessage $message, int $index)
    {
        $attachments = $message->attachments ?? [];
        if (!isset($attachments[$index])) {
            abort(404);
        }

        $att = $attachments[$index];
        $path = $att['path'] ?? '';
        if (!Storage::disk('private')->exists($path)) {
            abort(404);
        }

        return Storage::disk('private')->download($path, $att['name'] ?? 'attachment');
    }

    private function isStaff(User $user): bool
    {
        return $user->hasRole(['admin', 'super-admin', 'superadmin', 'manager']);
    }

    private function storeAttachments(Request $request, SupportTicket $ticket): array
    {
        $attachments = [];
        if ($request->hasFile('attachments')) {
            $dir = "tickets/{$ticket->reference}";
            foreach ($request->file('attachments') as $file) {
                $path = $file->store($dir, 'private');
                $attachments[] = [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                ];
            }
        }
        return $attachments;
    }
}
