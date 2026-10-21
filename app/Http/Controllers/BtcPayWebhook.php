<?php

namespace App\Http\Controllers;

use App\Domain\Billing\InvalidWebhookSignature;
use App\Domain\Billing\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * BTCPay → StoreSiphon notifications. Authenticated by HMAC over the raw body; the body is only used
 * to know WHICH invoice to re-check — its status is always re-fetched from the BTCPay API.
 */
class BtcPayWebhook
{
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        $headers = array_change_key_case(array_map(fn ($v) => $v[0] ?? '', $request->headers->all()), CASE_LOWER);

        try {
            $result = $payments->receiveWebhook($request->getContent(), $headers);
        } catch (InvalidWebhookSignature) {
            Log::warning('Rejected BTCPay webhook with an invalid signature', ['ip' => $request->ip()]);

            return response()->json(['error' => 'invalid signature'], 401);
        } catch (RuntimeException) {
            return response()->json(['error' => 'malformed payload'], 400);
        }

        return response()->json(['status' => $result]);
    }
}
