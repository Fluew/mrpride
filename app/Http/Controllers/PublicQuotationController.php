<?php

namespace App\Http\Controllers;

use App\Enums\QuotationStatus;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicQuotationController extends Controller
{
    /**
     * Show public quotation request form.
     */
    public function create(): Response
    {
        return Inertia::render('Quotations/Create');
    }

    /**
     * Store public quotation request.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company' => ['required', 'string', 'max:255'],
            'contactName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'projectType' => ['nullable', 'string', 'max:100'],
            'timeline' => ['nullable', 'string', 'max:100'],
            'requirements' => ['required', 'string', 'max:3000'],
        ]);

        $team = Team::first();
        if ($team) {
            $client = Client::firstOrCreate(
                [
                    'team_id' => $team->id,
                    'company_name' => $validated['company'],
                ],
                [
                    'contact_person' => $validated['contactName'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                ]
            );

            $quotationNumber = Quotation::generateNumber($team->id);

            $quotation = Quotation::create([
                'team_id' => $team->id,
                'quotation_number' => $quotationNumber,
                'client_id' => $client->id,
                'client_name' => $validated['company'],
                'contact_person' => $validated['contactName'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'quotation_date' => now()->toDateString(),
                'valid_until' => now()->addDays(30)->toDateString(),
                'subtotal' => 0.00,
                'discount' => 0.00,
                'tax_rate' => 12.00,
                'tax_amount' => 0.00,
                'grand_total' => 0.00,
                'status' => QuotationStatus::Pending,
                'notes' => "Project Type: {$validated['projectType']}\nTimeline: {$validated['timeline']}\n\nClient Requirements:\n{$validated['requirements']}",
            ]);

            // Add placeholder item for review
            $quotation->items()->create([
                'sku' => 'REQ-EST-001',
                'product_name' => "Custom Quotation Request: {$validated['projectType']}",
                'quantity' => 1,
                'unit_price' => 0.00,
                'discount' => 0.00,
                'subtotal' => 0.00,
            ]);
        }

        return back()->with('success', 'Thank you! Your quotation request has been received. Our team will review your specifications and get in touch shortly.');
    }
}
