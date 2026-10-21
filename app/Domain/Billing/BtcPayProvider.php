<?php

namespace App\Domain\Billing;

use App\Models\Invoice;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * BTCPay Server via its Greenfield API (works the same for a managed host or a self-hosted server).
 *
 * Non-custodial: payments go to the store owner's own wallet. On-chain only (D7) and the confirmation
 * policy (D8, 1 confirmation) are enforced with the invoice's speedPolicy; BTCPay reports "Settled"
 * only once the required confirmations are reached.
 */
final class BtcPayProvider implements PaymentProvider
{
    /** speedPolicy for each confirmation requirement. */
    private const SPEED = [0 => 'HighSpeed', 1 => 'MediumSpeed', 2 => 'LowMediumSpeed', 6 => 'LowSpeed'];

    /** @param array{url: ?string, api_key: ?string, store_id: ?string, webhook_secret: ?string, confirmations: int, expiration_minutes: int} $config */
    public function __construct(private readonly HttpFactory $http, private readonly array $config) {}

    public function name(): string
    {
        return 'btcpay';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['url'] ?? null) && filled($this->config['api_key'] ?? null)
            && filled($this->config['store_id'] ?? null) && filled($this->config['webhook_secret'] ?? null);
    }

    public function createInvoice(Invoice $invoice, string $redirectUrl): ProviderInvoice
    {
        $response = $this->client()->post($this->storePath('invoices'), [
            'amount' => number_format((float) $invoice->fiat_amount, 2, '.', ''),
            'currency' => $invoice->fiat_currency,
            // Only our opaque order reference; no personal data is sent to the provider.
            'metadata' => ['orderId' => $invoice->id, 'itemDesc' => 'StoreSiphon '.$invoice->plan->name.' – '.$invoice->plan->period_days.' days'],
            'checkout' => [
                'expirationMinutes' => (int) ($this->config['expiration_minutes'] ?? 30),
                'speedPolicy' => self::SPEED[(int) ($this->config['confirmations'] ?? 1)] ?? 'MediumSpeed',
                'redirectURL' => $redirectUrl,
                'redirectAutomatically' => false,
            ],
        ]);
        $this->ensureOk($response->status(), 'create invoice');

        return $this->getInvoice((string) $response->json('id'));
    }

    public function getInvoice(string $providerInvoiceId): ProviderInvoice
    {
        $invoice = $this->client()->get($this->storePath('invoices/'.rawurlencode($providerInvoiceId)));
        $this->ensureOk($invoice->status(), 'get invoice');
        $methods = $this->client()->get($this->storePath('invoices/'.rawurlencode($providerInvoiceId).'/payment-methods'));
        $btc = $methods->successful() ? $this->btcMethod((array) $methods->json()) : null;

        $data = (array) $invoice->json();

        return new ProviderInvoice(
            id: (string) $data['id'],
            status: (string) ($data['status'] ?? 'New'),
            additionalStatus: $data['additionalStatus'] ?? null,
            orderId: $data['metadata']['orderId'] ?? null,
            checkoutUrl: $data['checkoutLink'] ?? null,
            expiresAt: isset($data['expirationTime']) ? Carbon::createFromTimestamp((int) $data['expirationTime']) : null,
            btcAmount: $btc['amount'] ?? null,
            btcPaid: $btc['paid'] ?? null,
            rate: $btc['rate'] ?? null,
            fiatAmount: isset($data['amount']) ? (string) $data['amount'] : null,
            fiatCurrency: $data['currency'] ?? null,
        );
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');
        $header = (string) ($headers['btcpay-sig'] ?? '');
        if ($secret === '' || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), strtolower(substr($header, 7)));
    }

    public function parseWebhook(string $rawBody): array
    {
        $data = json_decode($rawBody, true);
        if (! is_array($data) || empty($data['deliveryId']) || empty($data['type'])) {
            throw new RuntimeException('Malformed BTCPay webhook payload');
        }

        return [
            'delivery_id' => (string) $data['deliveryId'],
            'type' => (string) $data['type'],
            'invoice_id' => isset($data['invoiceId']) ? (string) $data['invoiceId'] : null,
            'store_id' => isset($data['storeId']) ? (string) $data['storeId'] : null,
        ];
    }

    public function expectedStoreId(): ?string
    {
        return $this->config['store_id'] ?? null;
    }

    /**
     * The on-chain BTC entry from /payment-methods (BTCPay 1.x "BTC" / 2.x "BTC-CHAIN").
     *
     * @return array{amount: ?string, paid: ?string, rate: ?string}|null
     */
    private function btcMethod(array $methods): ?array
    {
        foreach ($methods as $m) {
            $id = strtoupper((string) ($m['paymentMethodId'] ?? $m['paymentMethod'] ?? ''));
            if (in_array($id, ['BTC', 'BTC-CHAIN', 'BTC_ONCHAIN'], true)) {
                return [
                    'amount' => isset($m['amount']) ? (string) $m['amount'] : null,
                    'paid' => isset($m['totalPaid']) ? (string) $m['totalPaid'] : (isset($m['paymentMethodPaid']) ? (string) $m['paymentMethodPaid'] : null),
                    'rate' => isset($m['rate']) ? (string) $m['rate'] : null,
                ];
            }
        }

        return null;
    }

    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('BTCPay is not configured');
        }

        return $this->http
            ->baseUrl(rtrim((string) $this->config['url'], '/'))
            ->withHeaders(['Authorization' => 'token '.$this->config['api_key']])
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(20);
    }

    private function storePath(string $suffix): string
    {
        return '/api/v1/stores/'.rawurlencode((string) $this->config['store_id']).'/'.$suffix;
    }

    private function ensureOk(int $status, string $action): void
    {
        if ($status < 200 || $status >= 300) {
            // Never include the request/response here: it could contain the API key in proxies' echoes.
            throw new RuntimeException("BTCPay {$action} failed with HTTP {$status}");
        }
    }
}
