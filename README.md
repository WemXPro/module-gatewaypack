# GatewayPack Integration Guide

This document provides details about adding and managing payment gateways in the `GatewayPack` module. It also describes
available methods in the system and their usage.

---

## Overview

`GatewayPack` is designed for seamless integration and management of payment gateways. The system provides a trait (
`HelperGateway`) for shared functionality and a structured way to implement new gateways.

---

## How to Add a New Gateway

1. **Create a Gateway Class**

    - All gateways must implement the `PaymentGatewayInterface` and use the `HelperGateway` trait for shared
      functionality.

2. **Register the Gateway**

    - Add the gateway class to the `GatewayPack` registry in the `Modules\\GatewayPack\\Entities\\GatewayPack` class.

3. **Define Drivers and Configuration**

    - Implement methods like `getConfigMerge()` and `drivers()` to define specific behaviors for your gateway.

---

## Available Methods in `HelperGateway` Trait

### 1. `getGatewayByEndpoint()`

- Retrieves the `Gateway` model instance based on its endpoint.
- **Return Type:** `Gateway`
- **Usage:**
  ```php
  $gateway = self::getGatewayByEndpoint();
  ```

### 2. `errorRedirect(string $message)`

- Redirects the user to the dashboard with an error message.
- **Usage:**
  ```php
  return self::errorRedirect('Payment failed');
  ```

### 3. `getReturnUrl()`

- Generates the return URL for the payment gateway.
- **Return Type:** `string`
- **Usage:**
  ```php
  $url = self::getReturnUrl();
  ```

### 4. `getCancelUrl(Payment $payment)`

- Generates the cancel URL for the payment gateway.
- **Return Type:** `string`
- **Usage:**
  ```php
  $url = self::getCancelUrl($payment);
  ```

### 5. `getSucceedUrl(Payment $payment)`

- Generates the success URL for the payment gateway.
- **Return Type:** `string`
- **Usage:**
  ```php
  $url = self::getSucceedUrl($payment);
  ```

### 6. `sendHttpRequest(string $method, string $url, array $data = [], ?string $token = null)`

- Sends an HTTP request (GET or POST) to the specified URL.
- Parameters:
    - `$method`: HTTP method (GET or POST).
    - `$url`: URL to send the request.
    - `$data`: Request payload.
    - `$token`: Optional authorization token.
- **Return Type:** `HttpResponse`
- **Usage:**
  ```php
  $response = self::sendHttpRequest('POST', $url, $data, $token);
  ```

### 7. `log(string $message, string $level = 'info')`

- Logs a message with the specified level.
- Parameters:
    - `$message`: Message to log.
    - `$level`: Log level (info, warning, error).
- **Usage:**
  ```php
  self::log('Payment failed', 'error');
  ```

---

## Examples

### Adding a New Gateway

Here is a step-by-step example for adding a new payment gateway:

1. **Create a New Gateway Class**

    - Use the `HelperGateway` trait for common functionality and implement `PaymentGatewayInterface`.

   ```php
   namespace Modules\GatewayPack\Gateways\Once;

   use App\Models\Gateways\Gateway;
   use App\Models\Gateways\PaymentGatewayInterface;
   use App\Models\Payment;
   use Illuminate\Http\Request;
   use Modules\GatewayPack\Traits\HelperGateway;

   class ExampleGateway implements PaymentGatewayInterface
   {
       use HelperGateway;

       public static function endpoint(): string
       {
           return 'example-gateway';
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
               'ExampleGateway' => [
                   'driver' => 'ExampleGateway',
                   'type' => 'once',
                   'class' => self::class,
                   'endpoint' => self::endpoint(),
                   'refund_support' => false,
               ],
           ];
       }

       public static function processGateway(Gateway $gateway, Payment $payment)
       {
           // Implementation for payment processing
       }

       public static function returnGateway(Request $request)
       {
           // Implementation for handling gateway return
       }
   }
   ```

2. **Register the Gateway**

    - Add the new gateway to the `GatewayPack` registry.

   ```php
   namespace Modules\GatewayPack\Entities;

   use Modules\GatewayPack\Gateways\Once\ExampleGateway;

   class GatewayPack
   {
       protected static array $gateways = [
           ExampleGateway::class,
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
   ```

---

Happy coding! 😊

