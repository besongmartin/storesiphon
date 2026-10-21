<?php

namespace App\Livewire\Billing;

use App\Domain\Billing\PaymentService;
use App\Models\Invoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * Invoice status page. This is also where BTCPay sends the customer back after paying: arriving here
 * grants nothing by itself — the page only re-checks the invoice with the provider's API.
 */
#[Layout('components.layouts.app')]
#[Title('Invoice')]
class InvoiceShow extends Component
{
    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        abort_unless($invoice->user_id === auth()->id(), 403);
        $this->invoice = $invoice;
    }

    /** Polled while the invoice is open; asks the provider at most every 15 seconds. */
    public function check(PaymentService $payments): void
    {
        $this->invoice->refresh();
        if (! $this->invoice->isOpen() || ($this->invoice->last_checked_at && $this->invoice->last_checked_at->gt(now()->subSeconds(15)))) {
            return;
        }
        try {
            $this->invoice = $payments->refresh($this->invoice, 'invoice page');
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function render()
    {
        $this->invoice->refresh()->load('plan');

        return view('livewire.billing.invoice-show');
    }
}
