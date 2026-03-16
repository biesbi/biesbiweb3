SET NAMES utf8mb4;

START TRANSACTION;

CREATE TABLE IF NOT EXISTS payment_transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id VARCHAR(36) NOT NULL,
  provider VARCHAR(40) NOT NULL,
  transaction_type ENUM('init','callback','status_check','refund') NOT NULL DEFAULT 'init',
  status ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
  merchant_oid VARCHAR(120) NULL,
  provider_token VARCHAR(500) NULL,
  provider_reference VARCHAR(255) NULL,
  request_payload JSON NULL,
  response_payload JSON NULL,
  hash_payload JSON NULL,
  error_message TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_payment_transactions_order (order_id),
  KEY idx_payment_transactions_provider (provider, status),
  KEY idx_payment_transactions_merchant_oid (merchant_oid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

COMMIT;
