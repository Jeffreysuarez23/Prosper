<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Membresia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class PayPalController extends Controller
{
    private $base_url;

    public function __construct()
    {
        $mode = env('PAYPAL_MODE', 'sandbox');
        $this->base_url = $mode === 'live' 
            ? 'https://api-m.paypal.com' 
            : 'https://api-m.sandbox.paypal.com';
    }

    public function getClientId()
    {
        return response()->json([
            'client_id' => env('PAYPAL_CLIENT_ID', 'test')
        ]);
    }

    private function getAccessToken()
    {
        $clientId = env('PAYPAL_CLIENT_ID');
        $secret = env('PAYPAL_SECRET');

        // If credentials are empty, return dummy token for 'test' mode to prevent fatal errors
        // Note: Real sandbox environment requires actual credentials
        if (empty($clientId) || empty($secret)) {
            throw new \Exception('Missing PayPal configuration in .env');
        }

        $response = Http::withBasicAuth($clientId, $secret)
            ->asForm()
            ->post("{$this->base_url}/v1/oauth2/token", [
                'grant_type' => 'client_credentials'
            ]);

        if ($response->successful()) {
            return $response->json('access_token');
        }

        throw new \Exception('Failed to obtain PayPal Access Token: ' . $response->body());
    }

    public function createOrder(Request $request)
    {
        $request->validate([
            'plan' => 'required|in:ultra',
            'billing_cycle' => 'required|in:monthly,annual',
        ]);

        $plan = $request->plan;
        $cycle = $request->billing_cycle;

        // Prices in USD (since COP is not officially supported for direct checkout)
        $prices = [
            'ultra' => [
                'monthly' => '6.99',
                'annual' => '69.99'
            ]
        ];

        $price = $prices[$plan][$cycle];

        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post("{$this->base_url}/v2/checkout/orders", [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'amount' => [
                                'currency_code' => 'USD',
                                'value' => $price
                            ],
                            'description' => "Prosper " . ucfirst($plan) . " - " . ucfirst($cycle)
                        ]
                    ]
                ]);

            if ($response->successful()) {
                return response()->json([
                    'id' => $response->json('id')
                ]);
            }

            return response()->json(['error' => 'Failed to create order on PayPal: ' . $response->body()], 500);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function captureOrder(Request $request)
    {
        $request->validate([
            'orderID' => 'required|string',
            'plan' => 'required|in:ultra',
            'billing_cycle' => 'required|in:monthly,annual',
        ]);

        try {
            $accessToken = $this->getAccessToken();
            $orderID = $request->orderID;

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json'
                ])
                ->post("{$this->base_url}/v2/checkout/orders/{$orderID}/capture", new \stdClass());

            if ($response->successful() && $response->json('status') === 'COMPLETED') {
                $user = $request->user();
                $plan = $request->plan;
                $cycle = $request->billing_cycle;

                // Prices in USD
                $prices = [
                    'ultra' => ['monthly' => '6.99', 'annual' => '69.99']
                ];
                $expectedPrice = $prices[$plan][$cycle];
                $capturedAmount = $response->json('purchase_units.0.payments.captures.0.amount.value');

                if ((float)$capturedAmount < (float)$expectedPrice) {
                    Log::error("PayPal Fraud Attempt: User {$user->id} captured \${$capturedAmount} but requested {$plan} {$cycle} (\${$expectedPrice}).");
                    return response()->json(['error' => 'El monto pagado no coincide con el plan seleccionado. Reembolso automático en proceso o contacte soporte.'], 400);
                }

                // Update database
                $startsAt = now();
                $endsAt = $cycle === 'monthly' ? now()->addMonth() : now()->addYear();

                Membresia::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'plan' => $plan,
                        'billing_cycle' => $cycle,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'status' => 'active',
                    ]
                );

                return response()->json(['success' => true]);
            }

            Log::error('PayPal Capture Failed: ' . $response->body());
            return response()->json(['error' => 'Payment not completed or verification failed'], 400);

        } catch (\Exception $e) {
            Log::error('PayPal Capture Exception: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
