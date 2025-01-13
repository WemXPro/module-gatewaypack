<?php

namespace Modules\GatewayPack\Traits;

use App\Models\Gateways\Gateway;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;

trait HelperGateway
{
    protected static function getGatewayByEndpoint(): Gateway
    {
        return Gateway::where('endpoint', self::endpoint())->first();
    }

    protected static function errorRedirect(string $message): string
    {
       return redirect()->route('dashboard')->with('error', $message);
    }

    protected static function getReturnUrl(): string
    {
        return route('payment.return', ['gateway' => self::endpoint()]);
    }

    protected static function getSucceedUrl(Payment $payment): string
    {
        return route('payment.success', ['payment' => $payment->id]);
    }

    protected static function getCancelUrl(Payment $payment): string
    {
        return route('payment.cancel', ['payment' => $payment->id]);
    }

    protected static function sendHttpRequest(string $method, string $url, array $data = [], ?string $token = null)
    {
        $request = Http::withToken($token);
        return $method === 'POST' ? $request->post($url, $data) : $request->get($url);
    }

    protected static function log(string $message, string $level = 'info'): void
    {
        ErrorLog(static::class, $message, $level);
    }

    // Abstract methods to be implemented in the child classes. Overwrite them in the child classes if needed.
    public static function drivers(): array
    {
        $driver = basename(str_replace('\\', '/', static::class)) . '_' . 'GatewayPack';
        return [
            $driver => [
                'driver' => $driver,
                'type' => self::$type ?? 'once',
                'class' => self::class,
                'endpoint' => self::endpoint(),
                'refund_support' => self::$refund_support ?? false,
                'blade_edit_path' =>  self::$blade_helper_file ?? null,
            ],
        ];
    }
    public static function endpoint(): string
    {
        return self::$endpoint;
    }
    public static function checkSubscription(Gateway $gateway, $subscriptionId): bool
    {
        // Not supported
        return false;
    }
    public static function processRefund(Payment $payment, array $data)
    {
        // Not supported
    }
}
