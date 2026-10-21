<?php

namespace App\Console\Commands;

use App\Domain\Billing\PaymentService;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Throwable;

/**
 * Safety net for missed or delayed webhooks: re-check every open invoice with the provider.
 * Scheduled every five minutes (routes/console.php).
 */
class ReconcileInvoices extends Command
{
    protected $signature = 'billing:reconcile {--all : Re-check open invoices even if checked recently}';

    protected $description = 'Re-check open Bitcoin invoices with the payment provider';

    public function handle(PaymentService $payments): int
    {
        if (! $payments->provider()->isConfigured()) {
            $this->line('Payment provider not configured; nothing to reconcile.');

            return self::SUCCESS;
        }

        $query = Invoice::whereIn('status', Invoice::OPEN)
            ->whereNotNull('provider_invoice_id')
            ->where('created_at', '>', now()->subDays(7));
        if (! $this->option('all')) {
            $query->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subMinutes(2)));
        }

        $checked = 0;
        $changed = 0;
        foreach ($query->cursor() as $invoice) {
            try {
                $before = $invoice->status;
                $after = $payments->refresh($invoice, 'reconcile')->status;
                $checked++;
                if ($after !== $before) {
                    $changed++;
                    $this->line("{$invoice->id}: {$before} → {$after}");
                }
            } catch (Throwable $e) {
                report($e);
                $this->warn("{$invoice->id}: could not be checked");
            }
        }

        $this->info("Checked {$checked} open invoice(s); {$changed} changed.");

        return self::SUCCESS;
    }
}
