<?php

namespace App\Saas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Saas\Services\BillingService;
use Illuminate\Http\Request;

/** Public endpoints Stripe and PayPal call. Every request is verified before it is trusted. */
class BillingWebhookController extends Controller
{
    public function __construct(private BillingService $billing) {}

    public function stripe(Request $request)
    {
        $payload = $request->getContent();

        if (! $this->billing->stripeVerify($payload, $request->header('Stripe-Signature'))) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $this->billing->stripeEvent(json_decode($payload, true) ?: []);

        return response()->json(['received' => true]);
    }

    public function paypal(Request $request)
    {
        $payload = $request->getContent();

        if (! $this->billing->paypalVerify($request->headers->all(), $payload)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $this->billing->paypalEvent(json_decode($payload, true) ?: []);

        return response()->json(['received' => true]);
    }
}