<?php

namespace Modules\GatewayPack\Gateways\Once;

use App\Models\Gateways\Gateway;
use App\Models\Gateways\PaymentGatewayInterface;
use App\Models\Payment;
use Illuminate\Http\Request;
use Modules\GatewayPack\Traits\HelperGateway;

class PayPalRest implements PaymentGatewayInterface
{
    use HelperGateway;

    public static string $apiUrl = 'https://api.paypal.com';
    public static string $sandboxUrl = 'https://api.sandbox.paypal.com';

    public static string $endpoint = 'paypal-rest';

    public static string $type = 'once';

    public static bool $refund_support = false;

    public static function getConfigMerge(): array
    {
        return [
            'client_id' => '',
            'client_secret' => '',
            'test_mode' => true,
        ];
    }

    public static function processGateway(Gateway $gateway, Payment $payment)
    {
        $amount = $payment->amount;
        $currency = $payment->currency;

        $apiUrl = self::getApiUrl($gateway) . '/v1/payments/payment';
        $token = self::getAccessToken($gateway);

        if (!$token) {
            self::log('Error obtaining access token', 'error');
            return redirect(self::getCancelUrl($payment));
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
                'return_url' => self::getReturnUrl(),
                'cancel_url' => self::getCancelUrl($payment),
            ],
        ];

        $response = self::sendHttpRequest('POST', $apiUrl, $payload, $token);

        if ($response->successful() && isset($response['links'])) {
            $approvalLink = collect($response['links'])->firstWhere('rel', 'approval_url')['href'] ?? null;
            if ($approvalLink) {
                return redirect()->away($approvalLink);
            }
        }

        self::log('Error creating PayPal payment: ' . $response->body(), 'error');
        return redirect(self::getCancelUrl($payment));
    }

    public static function returnGateway(Request $request)
    {
        $paymentId = $request->input('paymentId');
        $payerId = $request->input('PayerID');
        $gateway = self::getGatewayByEndpoint();

        if (!$paymentId || !$payerId) {
            self::log('Missing parameters', 'error');
            return self::errorRedirect('Missing parameters');
        }

        $apiUrl = self::getApiUrl($gateway) . '/v1/payments/payment/' . $paymentId . '/execute';
        $token = self::getAccessToken($gateway);

        if (!$token) {
            self::log('Error obtaining access token', 'error');
            return self::errorRedirect('Error processing payment');
        }

        $response = self::sendHttpRequest('POST', $apiUrl, ['payer_id' => $payerId], $token);

        if ($response->successful() && isset($response['transactions'])) {
            $amount = $response['transactions'][0]['amount']['total'] ?? null;
            $currency = $response['transactions'][0]['amount']['currency'] ?? null;

            $payment = Payment::find($response['transactions'][0]['invoice_number']);

            if (!$payment) {
                self::log('Payment not found', 'error');
                return redirect(self::getCancelUrl($payment));
            }

            if ($payment->status === 'paid') {
                self::log('Payment already completed', 'info');
                return redirect(self::getSucceedUrl($payment));
            }

            if ($payment->currency !== $currency) {
                self::log('Currency mismatch', 'error');
                return redirect(self::getCancelUrl($payment));
            }

            if ((float) $payment->amount === (float) $amount) {
                $payment->completed($payment->id, $response->json());
                return redirect(self::getSucceedUrl($payment));
            } else {
                self::log('Amount mismatch', 'error');
                return redirect(self::getCancelUrl($payment));
            }
        }

        self::log('Payment verification failed: ' . $response->body(), 'error');
        return self::errorRedirect('Payment verification failed');
    }

    private static function getApiUrl(Gateway $gateway): string
    {
        return $gateway->config['test_mode'] ? self::$sandboxUrl : self::$apiUrl;
    }

    private static function getAccessToken(Gateway $gateway): ?string
    {
        $apiUrl = self::getApiUrl($gateway) . '/v1/oauth2/token';
        $response = self::sendHttpRequest('POST', $apiUrl, ['grant_type' => 'client_credentials'], $gateway->config['client_id'] . ':' . $gateway->config['client_secret']);
        return $response->successful() ? $response['access_token'] ?? null : null;
    }
}
