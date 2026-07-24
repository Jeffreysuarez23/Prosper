<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\HistorialPago;
use App\Models\Membresia;

class PayPalWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Verify Webhook Signature (in a real production app, verify PayPal's signature header)
        // For now, we process the event directly.
        $event = $request->input('event_type');
        $resource = $request->input('resource');

        if (!$event) {
            return response()->json(['error' => 'No event type'], 400);
        }

        Log::info("PayPal Webhook Event Received: {$event}");

        switch ($event) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                $this->handleCaptureCompleted($resource);
                break;
            case 'PAYMENT.CAPTURE.DENIED':
            case 'PAYMENT.CAPTURE.REFUNDED':
            case 'PAYMENT.CAPTURE.REVERSED':
                $this->handlePaymentReversed($resource);
                break;
            default:
                Log::info("Unhandled PayPal Event: {$event}");
                break;
        }

        return response()->json(['status' => 'success']);
    }

    private function handleCaptureCompleted($resource)
    {
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
        if (!$orderId) return;

        $pago = HistorialPago::where('order_id', $orderId)->first();
        if ($pago && $pago->status !== 'completed') {
            $pago->status = 'completed';
            $pago->save();
        }
    }

    private function handlePaymentReversed($resource)
    {
        // Look up by order ID (which corresponds to checkout order)
        // Note: For refunds, the parent payment ID is usually provided.
        // We do a best-effort match depending on PayPal webhook structure.
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
        
        if (!$orderId) {
            // Fallback: try to find by the capture ID if it matches
            $orderId = $resource['id'] ?? null;
        }

        $pago = HistorialPago::where('order_id', $orderId)->orWhere('id', $orderId)->first();
        
        if ($pago) {
            Log::info("Reversing payment for Order ID: {$orderId}, User ID: {$pago->user_id}");
            $pago->status = 'refunded';
            $pago->save();

            // Downgrade membership immediately
            Membresia::where('user_id', $pago->user_id)
                ->where('status', 'active')
                ->update([
                    'status' => 'expired',
                    'ends_at' => now(), // Force expiration today
                    'plan' => 'gratis'
                ]);
            
            Log::info("User ID {$pago->user_id} membership downgraded due to refund/reversal.");
        } else {
            Log::warning("PayPal Webhook: Could not find payment record for reversal. Resource ID: " . ($resource['id'] ?? 'unknown'));
        }
    }
}
