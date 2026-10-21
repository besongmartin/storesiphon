<?php

namespace App\Domain\Billing;

use Illuminate\Support\Carbon;

/**
 * Provider-neutral snapshot of an invoice as reported by the payment provider.
 */
final class ProviderInvoice
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,                 // provider vocabulary, e.g. New | Processing | Settled | Expired | Invalid
        public readonly ?string $additionalStatus,      // e.g. None | PaidPartial | PaidOver | PaidLate | Marked | Invalid
        public readonly ?string $orderId,
        public readonly ?string $checkoutUrl = null,
        public readonly ?Carbon $expiresAt = null,
        public readonly ?string $btcAmount = null,      // amount requested in BTC
        public readonly ?string $btcPaid = null,        // amount received in BTC
        public readonly ?string $rate = null,           // fiat per BTC
        public readonly ?string $fiatAmount = null,
        public readonly ?string $fiatCurrency = null,
    ) {}
}
