<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $search = $request->input('search');

        $query = Supplier::where('team_id', $team->id);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->orderBy('name')->get()->map(function (Supplier $s) {
            $metrics = $s->metrics ?? ['price' => 7, 'quality' => 7, 'delivery' => 7, 'service' => 7];
            return [
                'id' => $s->id,
                'name' => $s->name,
                'contactPerson' => $s->contact_person ?? '',
                'phone' => $s->phone ?? '',
                'email' => $s->email ?? '',
                'address' => $s->address ?? '',
                'status' => $s->status ?? 'Active',
                'ahpScore' => (float) ($s->ahp_score ?? 0.500),
                'metrics' => [
                    'price' => (int) ($metrics['price'] ?? 7),
                    'quality' => (int) ($metrics['quality'] ?? 7),
                    'delivery' => (int) ($metrics['delivery'] ?? 7),
                    'service' => (int) ($metrics['service'] ?? 7),
                ],
            ];
        });

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $search ?? '',
            ],
        ]);
    }

    /**
     * Store a newly created supplier in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contactPerson' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'string', 'in:Active,Inactive'],
            'metrics.price' => ['nullable', 'integer', 'between:1,10'],
            'metrics.quality' => ['nullable', 'integer', 'between:1,10'],
            'metrics.delivery' => ['nullable', 'integer', 'between:1,10'],
            'metrics.service' => ['nullable', 'integer', 'between:1,10'],
        ]);

        $metrics = $validated['metrics'] ?? ['price' => 7, 'quality' => 7, 'delivery' => 7, 'service' => 7];
        $ahpScore = $this->calculateAhpScore($metrics);

        Supplier::create([
            'team_id' => $team->id,
            'name' => $validated['name'],
            'contact_person' => $validated['contactPerson'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'status' => $validated['status'],
            'ahp_score' => $ahpScore,
            'metrics' => $metrics,
        ]);

        return back()->with('success', 'Supplier created successfully.');
    }

    /**
     * Update the specified supplier in storage.
     */
    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        if (! $team || $supplier->team_id !== $team->id) {
            abort(403, 'Supplier not found in current workspace.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contactPerson' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'string', 'in:Active,Inactive'],
            'metrics.price' => ['nullable', 'integer', 'between:1,10'],
            'metrics.quality' => ['nullable', 'integer', 'between:1,10'],
            'metrics.delivery' => ['nullable', 'integer', 'between:1,10'],
            'metrics.service' => ['nullable', 'integer', 'between:1,10'],
        ]);

        $metrics = $validated['metrics'] ?? $supplier->metrics ?? ['price' => 7, 'quality' => 7, 'delivery' => 7, 'service' => 7];
        $ahpScore = $this->calculateAhpScore($metrics);

        $supplier->update([
            'name' => $validated['name'],
            'contact_person' => $validated['contactPerson'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'status' => $validated['status'],
            'ahp_score' => $ahpScore,
            'metrics' => $metrics,
        ]);

        return back()->with('success', 'Supplier updated successfully.');
    }

    /**
     * Remove the specified supplier from storage.
     */
    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        if (! $team || $supplier->team_id !== $team->id) {
            abort(403, 'Supplier not found in current workspace.');
        }

        $supplier->delete();

        return back()->with('success', 'Supplier removed successfully.');
    }

    /**
     * Recalculate AHP ranking across suppliers.
     */
    public function evaluate(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $suppliers = Supplier::where('team_id', $team->id)->get();
        foreach ($suppliers as $s) {
            $metrics = $s->metrics ?? ['price' => 7, 'quality' => 7, 'delivery' => 7, 'service' => 7];
            $score = $this->calculateAhpScore($metrics);
            $s->update(['ahp_score' => $score]);
        }

        return back()->with('success', 'AHP evaluation completed across all suppliers.');
    }

    /**
     * Calculate Analytical Hierarchy Process (AHP) composite weight.
     * Weights: Price (35%), Quality (30%), Delivery (20%), Service (15%)
     */
    private function calculateAhpScore(array $metrics): float
    {
        $p = (float) ($metrics['price'] ?? 5);
        $q = (float) ($metrics['quality'] ?? 5);
        $d = (float) ($metrics['delivery'] ?? 5);
        $s = (float) ($metrics['service'] ?? 5);

        // Normalize 1-10 scale to 0.0 - 1.0 composite
        $score = ($p * 0.35 + $q * 0.30 + $d * 0.20 + $s * 0.15) / 10.0;

        return round(max(0.1, min(0.999, $score)), 3);
    }
}
