<?php

namespace Modules\GatewayPack\Gateways\Once;

use App\Models\Gateways\Gateway;
use App\Models\Gateways\PaymentGatewayInterface;
use App\Models\Payment;
use Illuminate\Http\Request;
use Modules\GatewayPack\Traits\CommonPaymentGateway;

class Monobank implements PaymentGatewayInterface
{
    use CommonPaymentGateway;

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
        $data = $request->all();
        if (!isset($data['type'])) {
            return;
        }

        if (strtolower($data['type']) === 'statementitem') {
            if (!isset($data['data']['statementItem'])) {
                return;
            }

            $statementItem = $data['data']['statementItem'];
            if (!isset($statementItem['comment']) || !isset($statementItem['amount'])) {
                return;
            }

            if ($statementItem['amount'] < 0) {
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
            'webHookUrl' => self::getReturnUrl(),
        ];

        $response = self::sendHttpRequest('POST', $url, $data, $gateway->config['token']);

        return $response->status() === 200;
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
}
