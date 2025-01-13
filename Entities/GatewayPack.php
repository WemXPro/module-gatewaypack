<?php

namespace Modules\GatewayPack\Entities;

use Modules\GatewayPack\Gateways\Once\Monobank;
use Modules\GatewayPack\Gateways\Once\PayPalRest;
use Modules\GatewayPack\Gateways\Once\Paysafecard;

class GatewayPack
{
    protected static array $gateways = [
        Monobank::class,
        PayPalRest::class,
//        Paysafecard::class,
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
