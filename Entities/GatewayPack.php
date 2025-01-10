<?php

namespace Modules\GatewayPack\Entities;

use Modules\GatewayPack\Gateways\Monobank;

class GatewayPack
{
    protected static array $gateways = [
        'monobank' => Monobank::class,
    ];
    public static function drivers(): array
    {
        $drivers = [];
        foreach (self::$gateways as $key => $class) {
            if (method_exists($class, 'drivers')) {
                $drivers = array_merge($drivers, $class::drivers());
            }
        }
        return $drivers;
    }
}
