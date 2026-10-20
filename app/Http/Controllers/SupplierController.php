<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers with AHP scores.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team, 403, 'No active team selected.');

        $search = $request->input('search');
        $status = $request->input('status', 'All');
        $sort = $request->input('sort', 'ahp_desc');

        $query = Supplier::where('team_id', $team->id);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status && $status !== 'All') {
            $query->where('status', $status);
        }

        if ($sort === 'name_asc') {
            $query->orderBy('name', 'asc');
        } elseif ($sort === 'name_desc') {
            $query->orderBy('name', 'desc');
        } else {
            $query->orderByDesc('ahp_score')->orderBy('name', 'asc');
        }

        $suppliers = $query->paginate(15)->withQueryString();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
            ],
        ]);
    }

    /**
     * Store a newly created supplier.
     */
    public function store(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team, 403, 'No active team selected.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $validated['team_id'] = $team->id;

        Supplier::create($validated);

        return back()->with('success', 'Supplier created successfully.');
    }

    /**
     * Update the specified supplier.
     */
    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team || $supplier->team_id !== $team->id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $supplier->update($validated);

        return back()->with('success', 'Supplier updated successfully.');
    }

    /**
     * Remove the specified supplier.
     */
    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team || $supplier->team_id !== $team->id, 403);

        // Prevent deletion if supplier has products
        if ($supplier->products()->exists()) {
            return back()->withErrors(['supplier' => 'Cannot delete supplier with linked products. Reassign products first.']);
        }

        $supplier->delete();

        return back()->with('success', 'Supplier deleted successfully.');
    }

    /**
     * Run AHP evaluation for all active suppliers in the team.
     *
     * Uses the Analytic Hierarchy Process with pairwise comparisons
     * across four criteria: Price, Quality, Delivery, Service.
     */
    public function runAhp(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team, 403, 'No active team selected.');

        $validated = $request->validate([
            'criteria_weights' => ['required', 'array', 'size:4'],
            'criteria_weights.price' => ['required', 'numeric', 'min:0'],
            'criteria_weights.quality' => ['required', 'numeric', 'min:0'],
            'criteria_weights.delivery' => ['required', 'numeric', 'min:0'],
            'criteria_weights.service' => ['required', 'numeric', 'min:0'],
            'supplier_scores' => ['required', 'array', 'min:1'],
            'supplier_scores.*.id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('team_id', $team->id)],
            'supplier_scores.*.price' => ['required', 'integer', 'min:1', 'max:10'],
            'supplier_scores.*.quality' => ['required', 'integer', 'min:1', 'max:10'],
            'supplier_scores.*.delivery' => ['required', 'integer', 'min:1', 'max:10'],
            'supplier_scores.*.service' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        // Normalize criteria weights so they sum to 1
        $weights = $validated['criteria_weights'];
        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0) {
            return back()->withErrors(['criteria_weights' => 'Criteria weights must sum to a positive number.']);
        }
        $normalizedWeights = [
            'price' => $weights['price'] / $totalWeight,
            'quality' => $weights['quality'] / $totalWeight,
            'delivery' => $weights['delivery'] / $totalWeight,
            'service' => $weights['service'] / $totalWeight,
        ];

        // Calculate AHP score for each supplier
        foreach ($validated['supplier_scores'] as $scores) {
            $ahpScore = ($scores['price'] / 10) * $normalizedWeights['price']
                + ($scores['quality'] / 10) * $normalizedWeights['quality']
                + ($scores['delivery'] / 10) * $normalizedWeights['delivery']
                + ($scores['service'] / 10) * $normalizedWeights['service'];

            Supplier::where('id', $scores['id'])
                ->where('team_id', $team->id)
                ->update([
                    'ahp_score' => round($ahpScore, 3),
                    'metrics' => [
                        'price' => $scores['price'],
                        'quality' => $scores['quality'],
                        'delivery' => $scores['delivery'],
                        'service' => $scores['service'],
                    ],
                ]);
        }

        return back()->with('success', 'AHP evaluation completed successfully.');
    }
}
