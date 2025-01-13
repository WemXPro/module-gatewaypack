<?php

namespace Modules\GatewayPack\Gateways\Once;

use App\Models\Gateways\Gateway;
use App\Models\Gateways\PaymentGatewayInterface;
use App\Models\Payment;
use Illuminate\Http\Request;
use Modules\GatewayPack\Traits\CommonPaymentGateway;

class Paysafecard implements PaymentGatewayInterface
{
    use CommonPaymentGateway;

    public static string $apiUrl = 'https://api.paysafecard.com';
    public static string $sandboxUrl = 'https://apitest.paysafecard.com';

    public static function getApiUrl(Gateway $gateway): string
    {
        return $gateway->config['test_mode'] ? self::$sandboxUrl : self::$apiUrl;
    }

    public static function processGateway(Gateway $gateway, Payment $payment)
    {
        $amount = $payment->amount;
        $currency = $payment->currency;

        $url = self::getApiUrl($gateway) . '/v1/payments';
        $token = $gateway->config['api_key'];

        $payload = [
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency' => $currency,
            ],
            'redirect' => [
                'success_url' => self::getSucceedUrl($payment),
                'failure_url' => self::getCancelUrl($payment),
            ],
            'customer' => [
                'id' => $payment->user_id,
                'email' => $payment->user->email ?? 'unknown@example.com',
            ],
            'correlation_id' => $payment->id,
        ];

        $response = self::sendHttpRequest('POST', $url, $payload, $token);

        if ($response->successful() && isset($response['redirect']['auth_url'])) {
            return redirect()->away($response['redirect']['auth_url']);
        }

        self::log('Error creating Paysafecard payment: ' . $response->body(), 'error');
        return self::getCancelUrl($payment);
    }

    public static function returnGateway(Request $request)
    {
        $paymentId = $request->input('correlation_id');
        $payment = Payment::find($paymentId);
        $gateway = self::getGatewayByEndpoint();

        if (!$payment) {
            self::log('Missing parameters', 'error');
            return self::getCancelUrl($payment);
        }

        $apiUrl = self::getApiUrl($gateway) . '/v1/payments/' . $paymentId;
        $token = $gateway->config['api_key'];

        $response = self::sendHttpRequest('GET', $apiUrl, [], $token);

        if ($response->successful() && isset($response['status'])) {
            $status = $response['status'];

            if ($status === 'SUCCESS') {
                if ($payment->status === 'paid') {
                    self::log('Payment already paid', 'info');
                    return self::getSucceedUrl($payment);
                }
                $payment->completed($payment->id, $response->json());
                return self::getSucceedUrl($payment);
            } else {
                self::log('Payment failed with status ' . $status, 'error');
                return self::getCancelUrl($payment);
            }
        }

        self::log('Payment verification failed: ' . $response->body(), 'error');
        return self::errorRedirect('Payment verification failed');
    }

    public static function endpoint(): string
    {
        return 'paysafecard';
    }

    public static function getConfigMerge(): array
    {
        return [
            'api_key' => '',
            'test_mode' => true,
        ];
    }

    public static function drivers(): array
    {
        return [
            'Paysafecard' => [
                'driver' => 'Paysafecard',
                'type' => 'once',
                'class' => self::class,
                'endpoint' => self::endpoint(),
                'refund_support' => false,
            ],
        ];
    }
}
