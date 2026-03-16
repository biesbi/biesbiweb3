<?php

final class PaymentTransactionService
{
    public static function log(
        string $orderId,
        string $provider,
        string $transactionType,
        string $status,
        array $payload = []
    ): void {
        if (!tableExists('payment_transactions')) {
            return;
        }

        db()->prepare(
            'INSERT INTO payment_transactions
             (order_id, provider, transaction_type, status, merchant_oid, provider_token, provider_reference, request_payload, response_payload, hash_payload, error_message)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $orderId,
            $provider,
            $transactionType,
            $status,
            $payload['merchant_oid'] ?? null,
            $payload['provider_token'] ?? null,
            $payload['provider_reference'] ?? null,
            isset($payload['request_payload']) ? json_encode($payload['request_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            isset($payload['response_payload']) ? json_encode($payload['response_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            isset($payload['hash_payload']) ? json_encode($payload['hash_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $payload['error_message'] ?? null,
        ]);
    }
}

