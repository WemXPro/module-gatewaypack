<?php

namespace Modules\GatewayPack\Gateways\Once;

use App\Models\Gateways\Gateway;
use App\Models\Gateways\PaymentGatewayInterface;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PayPalRest implements PaymentGatewayInterface
{
    public static string $apiUrl = 'https://api.paypal.com';
    public static string $sandboxUrl = 'https://api.sandbox.paypal.com';

    public static function getApiUrl(Gateway $gateway): string
    {
        return $gateway->config['test_mode'] ? self::$sandboxUrl : self::$apiUrl;
    }

    public static function processGateway(Gateway $gateway, Payment $payment)
    {
        $amount = $payment->amount;
        $currency = $payment->currency;

        $apiUrl = self::getApiUrl($gateway) . '/v1/payments/payment';
        $token = self::getAccessToken($gateway);

        if (!$token) {
            self::log('Error obtaining access token', 'error');
            return redirect()->route('payment.cancel', ['payment' => $payment->id]);
        }

        $payload = [
            'intent' => 'sale',
            'payer' => ['payment_method' => 'paypal'],
            'transactions' => [[
                'amount' => [
                    'total' => number_format($amount, 2, '.', ''),
                    'currency' => $currency,
                ],
                'description' => 'Payment for Order #' . $payment->id,
                'invoice_number' => $payment->id,
            ]],
            'redirect_urls' => [
                'return_url' => route('payment.return', ['gateway' => $gateway->endpoint]),
                'cancel_url' => route('payment.cancel', ['payment' => $payment->id]),
            ],
        ];

        $response = Http::withToken($token)->post($apiUrl, $payload);

        if ($response->successful() && isset($response['links'])) {
            $approvalLink = collect($response['links'])->firstWhere('rel', 'approval_url')['href'] ?? null;
            if ($approvalLink) {
                return redirect()->away($approvalLink);
            }
        }

        self::log('Error creating PayPal payment: ' . $response->body(), 'error');
        return redirect()->route('payment.cancel', ['payment' => $payment->id]);
    }

    public static function returnGateway(Request $request)
    {
        $paymentId = $request->input('paymentId');
        $payerId = $request->input('PayerID');
        $gateway = Gateway::where('endpoint', self::endpoint())->first();

        if (!$paymentId || !$payerId || !$gateway) {
            self::log('PayPal return: Missing parameters', 'error');
            return redirect()->route('dashboard')->with('error', 'Error processing payment');
        }

        $apiUrl = self::getApiUrl($gateway) . '/v1/payments/payment/' . $paymentId . '/execute';
        $token = self::getAccessToken($gateway);

        if (!$token) {
            self::log('Error obtaining access token', 'error');
            return redirect()->route('dashboard')->with('error', 'Error processing payment');
        }

        $response = Http::withToken($token)->post($apiUrl, ['payer_id' => $payerId]);

        if ($response->successful() && isset($response['transactions'])) {
            $amount = $response['transactions'][0]['amount']['total'] ?? null;
            $currency = $response['transactions'][0]['amount']['currency'] ?? null;

            $payment = Payment::find($response['transactions'][0]['invoice_number']);

            if (!$payment) {
                self::log('PayPal return: Payment not found', 'error');
                return redirect()->route('payment.cancel', ['payment' => $payment->id]);
            }

            if ($payment->status === 'paid') {
                self::log('PayPal return: Payment already completed', 'info');
                return redirect()->route('payment.success', ['payment' => $payment->id]);
            }

            if ($payment->currency !== $currency) {
                self::log('PayPal return: Currency mismatch', 'error');
                return redirect()->route('payment.cancel', ['payment' => $payment->id]);
            }

            if ((float) $payment->amount === (float) $amount) {
                $payment->completed($payment->id, $response->json());
                return redirect()->route('payment.success', ['payment' => $payment->id]);
            } else {
                self::log('PayPal return: Amount mismatch', 'error');
                return redirect()->route('payment.cancel', ['payment' => $payment->id]);
            }
        }

        self::log('PayPal return: Payment verification failed: ' . $response->body(), 'error');
        return redirect()->route('dashboard')->with('error', 'Payment verification failed');
    }

    private static function getAccessToken(Gateway $gateway): ?string
    {
        $apiUrl = self::getApiUrl($gateway) . '/v1/oauth2/token';
        $response = Http::withBasicAuth($gateway->config['client_id'], $gateway->config['client_secret'])
            ->asForm()->post($apiUrl, ['grant_type' => 'client_credentials']);

        if ($response->successful()) {
            return $response['access_token'] ?? null;
        }

        self::log('Error obtaining access token: ' . $response->body(), 'error');
        return null;
    }

    public static function checkSubscription(Gateway $gateway, $subscriptionId): bool
    {
        // Not supported
        return false;
    }

    public static function processRefund(Payment $payment, array $data): void
    {
        // Implement refund logic if required
        self::log('Refund processing is not yet implemented.', 'info');
    }

    public static function endpoint(): string
    {
        return 'paypal-rest';
    }

    public static function getConfigMerge(): array
    {
        return [
            'client_id' => '',
            'client_secret' => '',
            'test_mode' => true,
        ];
    }

    public static function drivers(): array
    {
        return [
            'PayPalRest' => [
                'driver' => 'PayPalRest',
                'type' => 'once',
                'class' => self::class,
                'endpoint' => self::endpoint(),
                'refund_support' => false,
            ],
        ];
    }

    protected static function log(string $message, string $level = 'info'): void
    {
        ErrorLog("PayPalRest", $message, $level);
    }
}
