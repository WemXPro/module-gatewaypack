<?php

namespace Modules\GatewayPack\Gateways\Once;

use App\Models\Gateways\Gateway;
use App\Models\Gateways\PaymentGatewayInterface;
use App\Models\Payment;
use Illuminate\Http\Request;
use Modules\GatewayPack\Traits\HelperGateway;

class Paysafecard implements PaymentGatewayInterface
{
    use HelperGateway;

    public static string $apiUrl = 'https://api.paysafe.com/paymenthub';
    public static string $sandboxUrl = 'https://api.test.paysafe.com/paymenthub';

    public static string $endpoint = 'paysafecard';

    public static string $type = 'once';

    public static bool $refund_support = false;

    public static function getConfigMerge(): array
    {
        return [
            'api_username' => '',
            'api_key' => '',
            'test_mode' => true,
        ];
    }

    public static function processGateway(Gateway $gateway, Payment $payment)
    {
        $amount = $payment->amount;
        $currency = $payment->currency;

        $url = self::getApiUrl($gateway) . '/v1/payments';
        $token = self::generateAuthorizationToken($gateway);

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

//        dd($response->json());
        if ($response->successful() && isset($response['redirect']['auth_url'])) {
            return redirect()->away($response['redirect']['auth_url']);
        }

        $errorBody = $response->json();
        self::log('Error creating Paysafecard payment: ' . json_encode($errorBody), 'error');
        return redirect(self::getCancelUrl($payment));
    }

    public static function returnGateway(Request $request)
    {
        $paymentId = $request->input('correlation_id');
        $payment = Payment::find($paymentId);
        $gateway = self::getGatewayByEndpoint();

        if (!$payment) {
            self::log('Missing parameters', 'error');
            return redirect(self::getCancelUrl($payment));
        }

        $apiUrl = self::getApiUrl($gateway) . '/v1/payments/' . $paymentId;
        $token = self::generateAuthorizationToken($gateway);

        $response = self::sendHttpRequest('GET', $apiUrl, [], $token);

        if ($response->successful() && isset($response['status'])) {
            $status = $response['status'];

            if ($status === 'SUCCESS') {
                if ($payment->status === 'paid') {
                    self::log('Payment already paid', 'info');
                    return redirect(self::getSucceedUrl($payment));
                }
                $payment->completed($payment->id, $response->json());
                return redirect(self::getSucceedUrl($payment));
            } else {
                self::log('Payment failed with status ' . $status, 'error');
                return redirect(self::getCancelUrl($payment));
            }
        }

        self::log('Payment verification failed: ' . $response->body(), 'error');
        return self::errorRedirect('Payment verification failed');
    }

    private static function getApiUrl(Gateway $gateway): string
    {
        return filter_var($gateway->config['test_mode'], FILTER_VALIDATE_BOOLEAN) ? self::$sandboxUrl : self::$apiUrl;
    }

    private static function generateAuthorizationToken(Gateway $gateway): string
    {
        $username = $gateway->config['api_username'];
        $apiKey = $gateway->config['api_key'];

        if (empty($username) || empty($apiKey)) {
            throw new \Exception('API username or API key is missing.');
        }

        return 'Basic ' . base64_encode("$username:$apiKey");
    }
}
