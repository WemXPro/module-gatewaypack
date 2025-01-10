<?php

namespace Modules\GatewayPack\Entities;

use Modules\GatewayPack\Gateways\Monobank;
use Modules\GatewayPack\Gateways\PayPalRest;

class GatewayPack
{
    protected static array $gateways = [
        Monobank::class,
        PayPalRest::class,
    ];
    public static function drivers(): array
    {
        $drivers = [];
        foreach (self::$gateways as $class) {
            if (method_exists($class, 'drivers')) {
                $drivers = array_merge($drivers, $class::drivers());
            }
        }
        return $drivers;
    }
}
