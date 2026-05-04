<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Municipality;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view companies');

        $companies = Company::query()
            ->with('municipality:id,name,code')
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate($request->per_page ?? 10)
            ->withQueryString();

        return Inertia::render('Admin/Companies/Index', [
            'companies' => $companies, // This is a paginator object
            'filters' => $request->only(['search', 'per_page']),
            'municipalities' => Municipality::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage companies');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:companies,name',
            'municipality_id' => 'required|exists:municipalities,id',
            'status' => 'required|in:active,inactive',
            'registration_number' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
        ]);

        Company::create($validated);

        return redirect()->back()->with('success', 'Company created successfully.');
    }

    public function update(Request $request, Company $company)
    {
        $this->authorize('manage companies');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:companies,name,' . $company->id,
            'municipality_id' => 'required|exists:municipalities,id',
            'status' => 'required|in:active,inactive',
            'registration_number' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
        ]);

        $company->update($validated);

        return redirect()->back()->with('success', 'Company updated successfully.');
    }

    public function destroy(Company $company)
    {
        $this->authorize('manage companies');

        $company->delete();

        return redirect()->back()->with('success', 'Company deleted successfully.');
    }
}
