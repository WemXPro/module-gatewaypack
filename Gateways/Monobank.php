<?php

namespace Modules\GatewayPack\Gateways;

use App\Models\Gateways\Gateway;
use App\Models\Gateways\PaymentGatewayInterface;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class Monobank implements PaymentGatewayInterface
{
    public static function processGateway(Gateway $gateway, Payment $payment)
    {
        $amount = $payment->amount;
        if (self::setWebhook($gateway)) {
            $params = '?a=' . $amount . '&t=' . $payment->id;
            $redirectUrl = $gateway->config['banka_url'] . $params;
             return redirect()->away($redirectUrl);
        }
        self::log('Error create webhook', 'error');
        return redirect()->route('payment.cancel', ['payment' => $payment->id]);
    }

    public static function returnGateway(Request $request): void
    {
        // Checking the availability of the necessary data in the request
        $data = $request->all();
        if (!isset($data['type'])) {
//            self::log('Payment processing error: missing type', 'error');
            return;
        }
        if (strtolower($data['type']) == 'statementitem') {
            if (!isset($data['data']['statementItem'])) {
//                self::log('Payment processing error: missing statementItem', 'error');
                return;
            }

            $statementItem = $data['data']['statementItem'];
            if (!isset($statementItem['comment']) || !isset($statementItem['amount'])) {
//                self::log('Payment processing error: missing comment or amount', 'error');
                return;
            }

            // Skip processing if the amount is negative
            if ($statementItem['amount'] < 0) {
//                self::log('Negative amount, no processing needed');
                return;
            }

            $paymentId = $statementItem['comment'];
            $amount = $statementItem['amount'];
            try {
                $payment = Payment::findOrFail($paymentId);
            } catch (\Exception $e) {
                self::log('Payment processing error: payment not found', 'error');
                return;
            }

            if ($amount == ($payment->amount * 100)) {
                $payment->completed($payment->id, $data);
            } else {
                self::log('Payment processing error: amount mismatch', 'error');
            }
        }
    }

    public static function setWebhook(Gateway $gateway): bool
    {
        $url = 'https://api.monobank.ua/personal/webhook';
        $data = [
            'webHookUrl' => route('payment.return', ['gateway' => $gateway->endpoint]),
        ];
        $response = Http::withHeaders(['X-Token' => $gateway->config['token'],])->post($url, $data);
        return $response->status() === 200;
    }

    public static function checkSubscription(Gateway $gateway, $subscriptionId): bool
    {
        return false;
    }
    public static function processRefund(Payment $payment, array $data)
    {

    }
    public static function endpoint(): string
    {
        return 'monobank';
    }
    public static function getConfigMerge(): array
    {
        return [
            'token' => '',
            'banka_url' => '',
        ];
    }
    public static function drivers(): array
    {
        return [
            'MonoBank' => [
                'driver' => 'MonoBank',
                'type' => 'once',
                'class' => self::class,
                'endpoint' => self::endpoint(),
                'refund_support' => false,
                'blade_edit_path' => 'gatewaypack::monobank',
            ],
        ];
    }
    protected static function log(string $message, string $level = 'info'): void
    {
        ErrorLog("(Monobank) {$message}", $level);
    }
}
