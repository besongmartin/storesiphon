<?php

namespace App\Domain\Billing;

use App\Models\Invoice;

interface PaymentProvider
{
    public function name(): string;

    public function isConfigured(): bool;

    /** Create the provider-side invoice for a local Invoice (amount, currency and order id come from it). */
    public function createInvoice(Invoice $invoice, string $redirectUrl): ProviderInvoice;

    /** The provider's current view of an invoice: the only source of truth for payment status. */
    public function getInvoice(string $providerInvoiceId): ProviderInvoice;

    /** Authenticate a webhook from its raw body and headers (header names lower-cased). */
    public function verifyWebhook(string $rawBody, array $headers): bool;

    /** @return array{delivery_id: string, type: string, invoice_id: ?string, store_id: ?string} */
    public function parseWebhook(string $rawBody): array;
}
