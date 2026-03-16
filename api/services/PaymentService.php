<?php

final class PaymentService
{
    public static function createPayment(array $order, array $items, array $shippingAddress): array
    {
        $result = PaytrService::createPayment($order, $items, $shippingAddress);

        PaymentTransactionService::log(
            (string) ($order['id'] ?? ''),
            'paytr',
            'init',
            self::resolveTransactionStatus($result),
            [
                'merchant_oid' => $result['merchant_oid'] ?? ($order['id'] ?? null),
                'provider_token' => $result['iframe_token'] ?? null,
                'provider_reference' => $result['merchant_oid'] ?? null,
                'request_payload' => [
                    'order_id' => $order['id'] ?? null,
                    'total' => $order['total'] ?? null,
                    'item_count' => count($items),
                ],
                'response_payload' => $result,
                'error_message' => $result['status'] === 'failed' ? ($result['message'] ?? 'payment_init_failed') : null,
            ]
        );

        return $result;
    }

    public static function completeMock(string $orderId, string $status): array
    {
        $result = PaytrService::completeMock($orderId, $status);
        PaymentTransactionService::log(
            $orderId,
            'paytr',
            'callback',
            $status === 'success' ? 'success' : 'failed',
            [
                'merchant_oid' => $orderId,
                'response_payload' => $result,
            ]
        );

        return $result;
    }

    private static function resolveTransactionStatus(array $result): string
    {
        return match ($result['status'] ?? '') {
            'iframe_ready', 'mock_ready', 'success' => 'success',
            'failed' => 'failed',
            default => 'pending',
        };
    }
}
