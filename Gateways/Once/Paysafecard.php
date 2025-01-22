<?php

namespace Modules\GatewayPack\Gateways\Once;

use App\Models\Gateways\Gateway;
use App\Models\Gateways\PaymentGatewayInterface;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        $paymentHandleToken = self::generatePaymentHandleToken($gateway, $payment);

        $url = self::getApiUrl($gateway) . '/v1/payments';
        $token = self::generateAuthorizationToken($gateway);

        $payload = [
            "merchantRefNum" => (string)$payment->id,
            "amount" => (int)($payment->amount * 100),
            "currencyCode" => $payment->currency,
            "paymentHandleToken" => $paymentHandleToken,
            "description" => "Payment for order #" . $payment->id,
        ];

        $response = self::sendHttpRequest('POST', $url, $payload, headers: [
            'Content-Type' => 'application/json',
            'Authorization' => $token,
        ]);

        if ($response->successful() && isset($response['links'])) {
            $checkoutLink = collect($response->json()['links'])->firstWhere('rel', 'approval_url')['href'];
            return redirect()->away($checkoutLink);
        }

        $errorBody = $response->json();
        self::log('Error creating Paysafecard payment: ' . json_encode($errorBody), 'error');
        return redirect(self::getCancelUrl($payment));
    }

    private static function generatePaymentHandleToken(Gateway $gateway, Payment $payment): string
    {
        $url = self::getApiUrl($gateway) . '/v1/paymenthandles';
        $token = self::generateAuthorizationToken($gateway);

        $payload = [
            "merchantRefNum" => (string)$payment->id,
            "transactionType" => "PAYMENT",
            "amount" => (int)($payment->amount * 100),
            "currencyCode" => $payment->currency,
            "paymentType" => "PAYSAFECARD",
            "redirect" => [
                "success" => self::getReturnUrl(),
                "failure" => self::getCancelUrl($payment),
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => $token,
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

//        dd($response->json());
        if ($response->successful() && isset($response['paymentHandleToken'])) {
            return $response->json()['paymentHandleToken'];
        }

        self::log('Error creating payment handle: ' . $response->body(), 'error');
        throw new \Exception('Failed to create payment handle: ' . $response->body());
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
        return 'Basic ' . base64_encode("$username:$apiKey");
    }

    protected static function sendHttpRequest(string $method, string $url, array $data = [], ?string $token = null, array $headers = [])
    {
        $request = Http::withHeaders($headers);
        return $method === 'POST' ? $request->post($url, $data) : $request->get($url);
    }
}
