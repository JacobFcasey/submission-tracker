<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Municipality;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MunicipalityController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view municipalities');

        $municipalities = Municipality::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('region', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate($request->per_page ?? 10)
            ->withQueryString();

        return Inertia::render('Admin/Municipalities/Index', [
            'municipalities' => $municipalities,
            'filters' => $request->only(['search', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage municipalities');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:municipalities,name',
            'code' => 'nullable|string|max:50|unique:municipalities,code',
            'region' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
        ]);

        Municipality::create($validated);

        return redirect()->back()->with('success', 'Municipality created successfully.');
    }

    public function update(Request $request, Municipality $municipality)
    {
        $this->authorize('manage municipalities');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:municipalities,name,' . $municipality->id,
            'code' => 'nullable|string|max:50|unique:municipalities,code,' . $municipality->id,
            'region' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
        ]);

        $municipality->update($validated);

        return redirect()->back()->with('success', 'Municipality updated successfully.');
    }

    public function destroy(Municipality $municipality)
    {
        $this->authorize('manage municipalities');

        $municipality->delete();

        return redirect()->back()->with('success', 'Municipality deleted successfully.');
    }
}
