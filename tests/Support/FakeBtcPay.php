<?php

namespace Tests\Support;

use App\Domain\Billing\PaymentProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Simulated BTCPay Server (Greenfield API) for tests. Invoices live in memory; tests move them
 * through BTCPay statuses with set(). Also signs webhook payloads like BTCPay does.
 */
final class FakeBtcPay
{
    public const URL = 'https://btcpay.payments.example';

    public const STORE = 'store_TEST123';

    public const API_KEY = 'apikey_0123456789abcdef_SECRET';

    public const SECRET = 'whsec_super_secret_value_42';

    /** @var array<string, array<string, mixed>> */
    public array $invoices = [];

    /** @var list<array<string, mixed>> */
    public array $created = [];

    private int $seq = 0;

    public function install(): self
    {
        config(['services.btcpay' => [
            'url' => self::URL, 'api_key' => self::API_KEY, 'store_id' => self::STORE,
            'webhook_secret' => self::SECRET, 'confirmations' => 1, 'expiration_minutes' => 30,
        ]]);
        app()->forgetInstance(PaymentProvider::class);

        Http::fake([self::URL.'/*' => fn (Request $r) => $this->respond($r)]);

        return $this;
    }

    /** Move a provider invoice to a new state. */
    public function set(string $id, string $status, string $additional = 'None', string $paid = '0'): void
    {
        $this->invoices[$id]['status'] = $status;
        $this->invoices[$id]['additionalStatus'] = $additional;
        $this->invoices[$id]['paid'] = $paid;
    }

    public function lastId(): string
    {
        return array_key_last($this->invoices);
    }

    /** A webhook body + headers signed the way BTCPay signs them. */
    public static function webhook(string $invoiceId, string $type = 'InvoiceSettled', ?string $deliveryId = null, string $store = self::STORE, array $extra = []): array
    {
        $body = json_encode(array_merge([
            'deliveryId' => $deliveryId ?? 'dlv_'.bin2hex(random_bytes(6)),
            'webhookId' => 'wh_1', 'originalDeliveryId' => 'dlv_orig', 'isRedelivery' => false,
            'type' => $type, 'timestamp' => time(), 'storeId' => $store, 'invoiceId' => $invoiceId,
        ], $extra));

        return [$body, ['BTCPay-Sig' => 'sha256='.hash_hmac('sha256', $body, self::SECRET), 'Content-Type' => 'application/json']];
    }

    private function respond(Request $request)
    {
        if (($request->header('Authorization')[0] ?? null) !== 'token '.self::API_KEY) {
            return Http::response(['code' => 'unauthenticated'], 401);
        }
        $path = parse_url($request->url(), PHP_URL_PATH);
        $prefix = '/api/v1/stores/'.self::STORE.'/invoices';

        if ($request->method() === 'POST' && $path === $prefix) {
            $data = $request->data();
            $this->created[] = $data;
            $id = 'BTCPAY'.(++$this->seq);
            $this->invoices[$id] = [
                'id' => $id, 'status' => 'New', 'additionalStatus' => 'None', 'paid' => '0',
                'amount' => $data['amount'], 'currency' => $data['currency'], 'orderId' => $data['metadata']['orderId'],
            ];

            return Http::response(['id' => $id, 'checkoutLink' => self::URL.'/i/'.$id, 'status' => 'New'], 200);
        }

        if (preg_match('#^'.preg_quote($prefix, '#').'/([^/]+)(/payment-methods)?$#', $path, $m) && isset($this->invoices[$m[1]])) {
            $inv = $this->invoices[$m[1]];
            if (! empty($m[2])) {
                $btc = bcdiv((string) $inv['amount'], '60000', 8);

                return Http::response([[
                    'paymentMethodId' => 'BTC-CHAIN', 'currency' => 'BTC', 'rate' => '60000.00',
                    'amount' => $btc, 'paymentMethodPaid' => $inv['paid'], 'totalPaid' => $inv['paid'], 'due' => bcsub($btc, $inv['paid'], 8),
                ]], 200);
            }

            return Http::response([
                'id' => $inv['id'], 'status' => $inv['status'], 'additionalStatus' => $inv['additionalStatus'],
                'amount' => $inv['amount'], 'currency' => $inv['currency'], 'checkoutLink' => self::URL.'/i/'.$inv['id'],
                'expirationTime' => now()->addMinutes(30)->getTimestamp(), 'metadata' => ['orderId' => $inv['orderId']],
            ], 200);
        }

        return Http::response(['code' => 'not-found'], 404);
    }
}
