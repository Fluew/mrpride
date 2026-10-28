<?php

namespace App\Services;

use App\Models\OrderFulfillment;
use App\Models\Quotation;
use App\Models\Sale;
use Illuminate\Support\Facades\Log;

/**
 * Customer-facing emails sent automatically when a record reaches a milestone:
 *  - quotation approved (the quotation is final)  → the quotation, to the client
 *  - sale completed                                → the sales invoice, to the customer
 *  - delivery set to "Out for Delivery"            → an in-transit notice, to the customer
 *
 * Everything goes through ResendService, so in local development it is redirected to the
 * Resend account address (RESEND_DEV_REDIRECT_TO). A missing customer email is skipped and
 * logged; a failed send is logged, audited and shown to the user who made the change. Sending
 * never blocks or undoes the business action itself.
 */
class CustomerEmails
{
    private const COMPANY = 'MR-Pride Electrical Supplies';

    public function __construct(private ResendService $mailer) {}

    public function quotationApproved(Quotation $quotation): void
    {
        $quotation->loadMissing(['items', 'client']);
        $to = $quotation->email ?: $quotation->client?->email;
        $label = "quotation {$quotation->quotation_number}";

        $rows = $quotation->items->map(fn ($item) => [
            $item->sku, $item->product_name, (int) $item->quantity, $this->money($item->unit_price), $this->money($item->subtotal),
        ])->all();

        $html = $this->layout(
            "Quotation {$quotation->quotation_number}",
            'Dear '.e($quotation->contact_person ?: $quotation->client_name).',',
            'Thank you for your interest. Your quotation is ready. Please review the details below and reply to this email to confirm your order.',
            [
                'Quotation No.' => $quotation->quotation_number,
                'Date' => $quotation->quotation_date?->format('M d, Y'),
                'Valid Until' => $quotation->valid_until?->format('M d, Y'),
                'Client' => $quotation->client_name,
            ],
            $rows,
            [
                'Subtotal' => $this->money($quotation->subtotal),
                'Discount' => $this->money($quotation->discount),
                'Tax' => $this->money($quotation->tax_amount),
                'Total' => $this->money($quotation->grand_total),
            ],
            $quotation->terms_and_conditions ? 'Terms: '.$quotation->terms_and_conditions : null,
        );

        $this->send($to, "Your Quotation {$quotation->quotation_number} from ".self::COMPANY, $html, $label, 'email.quotation_sent');
    }

    public function saleInvoice(Sale $sale): void
    {
        $sale->loadMissing(['items', 'quotation']);
        $to = $sale->email ?: $sale->quotation?->email;
        $label = "invoice {$sale->invoice_number}";

        $rows = $sale->items->map(fn ($item) => [
            $item->sku, $item->product_name, (int) $item->quantity, $this->money($item->unit_price), $this->money($item->subtotal),
        ])->all();

        $html = $this->layout(
            "Sales Invoice {$sale->invoice_number}",
            'Dear '.e($sale->contact_person ?: $sale->client_name).',',
            'Thank you for your purchase. Here is your sales invoice.',
            [
                'Invoice No.' => $sale->invoice_number,
                'Date' => $sale->sale_date?->format('M d, Y'),
                'Customer' => $sale->client_name,
                'Payment Method' => $sale->payment_method,
                'Payment Reference' => $sale->payment_reference,
            ],
            $rows,
            [
                'Subtotal' => $this->money($sale->subtotal),
                'Discount' => $this->money($sale->discount),
                'Tax' => $this->money($sale->tax_amount),
                'Total' => $this->money($sale->grand_total),
                'Amount Paid' => $this->money($sale->amount_paid),
                'Balance' => $this->money($sale->outstanding_balance),
            ],
        );

        $this->send($to, "Your Invoice {$sale->invoice_number} from ".self::COMPANY, $html, $label, 'email.invoice_sent');
    }

    public function outForDelivery(OrderFulfillment $fulfillment): void
    {
        $fulfillment->loadMissing(['sale.quotation', 'handledBy']);
        $sale = $fulfillment->sale;
        $to = $sale?->email ?: $sale?->quotation?->email;
        $label = "delivery {$fulfillment->fulfillment_number}";

        $html = $this->layout(
            'Your order is on the way',
            'Dear '.e($fulfillment->recipient_name ?: $sale?->client_name ?: 'Customer').',',
            'Good news: your order has left our warehouse and is now out for delivery.',
            [
                'Delivery No.' => $fulfillment->fulfillment_number,
                'Invoice No.' => $sale?->invoice_number,
                'Recipient' => $fulfillment->recipient_name,
                'Delivery Address' => $fulfillment->delivery_address,
                'Scheduled' => $fulfillment->delivery_schedule?->format('M d, Y h:i A'),
                'Dispatched' => ($fulfillment->dispatched_at ?? now())->format('M d, Y h:i A'),
            ],
            [],
            [],
            'Please make sure someone is available to receive the items. Reply to this email if you need to change the delivery details.',
        );

        $this->send($to, "Your order {$sale?->invoice_number} is out for delivery", $html, $label, 'email.delivery_in_transit');
    }

    private function send(?string $to, string $subject, string $html, string $label, string $event): void
    {
        if (! $to || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Log::info("Customer email for {$label} skipped: no customer email address on record.");

            return;
        }

        $result = $this->mailer->sendCustomerEmail($to, $subject, $html, $label);

        AuditLogger::log(
            $event,
            $result['success']
                ? ucfirst($label)." emailed to {$to}."
                : ucfirst($label)." email to {$to} FAILED: {$result['error']}",
            auth()->user(),
            ['to' => $to, 'resend_id' => $result['id'] ?? null, 'success' => $result['success']],
            null,
            'audit',
            $result['success'] ? 'info' : 'warning',
            null,
            $result['success'] ? 'success' : 'failure',
        );

        if (! $result['success'] && app()->runningInConsole() === false && request()->hasSession()) {
            request()->session()->flash('error', "The {$label} email to {$to} could not be sent: {$result['error']}");
        }
    }

    private function money(mixed $amount): string
    {
        return '₱'.number_format((float) $amount, 2);
    }

    /**
     * Simple, email-client-safe HTML (inline styles, tables). Every value is escaped.
     *
     * @param  array<string, ?string>  $details
     * @param  array<int, array<int, mixed>>  $rows  [sku, name, qty, unit price, line total]
     * @param  array<string, string>  $totals
     */
    private function layout(string $title, string $greeting, string $intro, array $details, array $rows, array $totals, ?string $footnote = null): string
    {
        $detailHtml = collect($details)->filter(fn ($v) => filled($v))->map(fn ($v, $k) => '<tr><td style="padding:4px 12px 4px 0;color:#6b7280;">'.e($k).'</td><td style="padding:4px 0;font-weight:600;color:#111827;">'.e((string) $v).'</td></tr>'
        )->implode('');

        $itemsHtml = '';
        if ($rows !== []) {
            $body = collect($rows)->map(fn ($r) => '<tr>'
                .'<td style="padding:8px;border-bottom:1px solid #e5e7eb;color:#6b7280;">'.e((string) $r[0]).'</td>'
                .'<td style="padding:8px;border-bottom:1px solid #e5e7eb;">'.e((string) $r[1]).'</td>'
                .'<td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">'.e((string) $r[2]).'</td>'
                .'<td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">'.e((string) $r[3]).'</td>'
                .'<td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:600;">'.e((string) $r[4]).'</td>'
                .'</tr>')->implode('');
            $itemsHtml = '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:20px;font-size:13px;">'
                .'<tr style="background:#f3f4f6;"><th align="left" style="padding:8px;">SKU</th><th align="left" style="padding:8px;">Item</th><th align="right" style="padding:8px;">Qty</th><th align="right" style="padding:8px;">Unit Price</th><th align="right" style="padding:8px;">Amount</th></tr>'
                .$body.'</table>';
        }

        $totalsHtml = collect($totals)->map(fn ($v, $k) => '<tr><td style="padding:4px 12px;color:#6b7280;text-align:right;">'.e($k).'</td><td style="padding:4px 0;text-align:right;font-weight:'.($k === 'Total' ? '800' : '600').';">'.e($v).'</td></tr>'
        )->implode('');
        $totalsHtml = $totalsHtml ? '<table cellpadding="0" cellspacing="0" style="margin:16px 0 0 auto;font-size:13px;">'.$totalsHtml.'</table>' : '';

        $foot = $footnote ? '<p style="margin-top:20px;font-size:12px;color:#6b7280;">'.nl2br(e($footnote)).'</p>' : '';

        return '<!DOCTYPE html><html><body style="margin:0;background:#f9fafb;font-family:Arial,Helvetica,sans-serif;color:#111827;">'
            .'<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px;">'
            .'<table width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">'
            .'<tr><td style="background:#4b5320;padding:20px 24px;color:#ffffff;"><div style="font-size:12px;letter-spacing:1px;color:#F8B803;font-weight:700;">'.e(strtoupper(self::COMPANY)).'</div>'
            .'<div style="font-size:20px;font-weight:800;margin-top:4px;">'.e($title).'</div></td></tr>'
            .'<tr><td style="padding:24px;font-size:14px;line-height:1.5;">'
            .'<p style="margin:0 0 8px;">'.$greeting.'</p><p style="margin:0 0 16px;">'.e($intro).'</p>'
            .'<table cellpadding="0" cellspacing="0" style="font-size:13px;">'.$detailHtml.'</table>'
            .$itemsHtml.$totalsHtml.$foot
            .'</td></tr>'
            .'<tr><td style="padding:16px 24px;background:#f3f4f6;font-size:11px;color:#6b7280;">This is an automated message from '.e(self::COMPANY).'.</td></tr>'
            .'</table></td></tr></table></body></html>';
    }
}
