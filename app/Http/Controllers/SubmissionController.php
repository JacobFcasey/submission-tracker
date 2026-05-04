<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Uploads; // Changed from Submission to Uploads
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view submissions');

        $query = Uploads::query() // Changed from Submission to Uploads
            ->with(['company:id,name', 'municipality:id,name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, function ($q, $s) {
                $q->where('reference', 'like', "%$s%")
                  ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%$s%"))
                  ->orWhereHas('municipality', fn ($m) => $m->where('name', 'like', "%$s%"));
            })
            ->latest('submitted_at');

        return Inertia::render('Submissions/Index', [
            'filters' => $request->only(['status', 'search']),
            'submissions' => $query->paginate(12)->withQueryString(),
        ]);
    }
}
