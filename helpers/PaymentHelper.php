<?php
/**
 * Payment signature and webhook payload helpers.
 */
if (!defined('APP_INIT')) die('Direct access not permitted');

class PaymentHelper
{
    public static function verifyPaymentSignature(
        string $orderId,
        string $paymentId,
        string $signature,
        string $secret
    ): bool {
        if ($orderId === '' || $paymentId === '' || $signature === '' || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
        return hash_equals($expected, $signature);
    }

    public static function verifyWebhookSignature(
        string $payload,
        string $signature,
        string $secret
    ): bool {
        if ($payload === '' || $signature === '' || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    public static function decodeWebhookPayload(string $payload): ?array
    {
        if ($payload === '') return null;

        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return null;
        }

        return is_array($event) ? $event : null;
    }

    public static function fetchPayment(
        string $paymentId,
        string $keyId,
        string $keySecret
    ): ?array {
        if ($paymentId === '' || $keyId === '' || $keySecret === '') return null;

        $ch = curl_init('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId));
        if ($ch === false) return null;

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $keyId . ':' . $keySecret,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError !== '' || $httpCode < 200 || $httpCode >= 300 || !is_string($response)) {
            return null;
        }

        $payment = json_decode($response, true);
        return is_array($payment) ? $payment : null;
    }

    public static function fetchOrder(
        string $orderId,
        string $keyId,
        string $keySecret
    ): ?array {
        if ($orderId === '' || $keyId === '' || $keySecret === '') return null;

        $ch = curl_init('https://api.razorpay.com/v1/orders/' . rawurlencode($orderId));
        if ($ch === false) return null;

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $keyId . ':' . $keySecret,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError !== '' || $httpCode < 200 || $httpCode >= 300 || !is_string($response)) {
            return null;
        }

        $order = json_decode($response, true);
        return is_array($order) ? $order : null;
    }
}
