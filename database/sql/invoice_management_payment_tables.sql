-- Invoice management: partner bulk invoice payments (Razorpay stub / gateway hooks).
-- Run against your app database (MySQL 5.7+ / 8; JSON column requires 5.7.8+).
-- Skip any ALTER that errors with "Duplicate column name".

-- ---------------------------------------------------------------------------
-- invoices: optional columns (ignore duplicate-column errors if already present)
-- ---------------------------------------------------------------------------
ALTER TABLE `invoices`
    ADD COLUMN `status` VARCHAR(64) NULL DEFAULT 'pending' AFTER `due_date`;

ALTER TABLE `invoices`
    ADD COLUMN `tzg_invoice_no` VARCHAR(100) NULL AFTER `invoice_no`;

-- ---------------------------------------------------------------------------
-- invoice_payment_transactions: parent payment attempt per partner
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoice_payment_transactions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `partner_id` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
    `total_amount` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(8) NOT NULL DEFAULT 'INR',
    `gateway` VARCHAR(32) NOT NULL DEFAULT 'razorpay',
    `gateway_order_id` VARCHAR(255) NULL DEFAULT NULL,
    `gateway_payment_id` VARCHAR(255) NULL DEFAULT NULL,
    `meta` JSON NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `invoice_payment_transactions_partner_id_index` (`partner_id`),
    KEY `invoice_payment_transactions_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- invoice_payment_transaction_items: invoices included in a parent transaction
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoice_payment_transaction_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_payment_transaction_id` BIGINT UNSIGNED NOT NULL,
    `invoice_id` BIGINT UNSIGNED NOT NULL,
    `amount` DECIMAL(14, 2) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `invoice_payment_transaction_items_invoice_payment_transaction_id_index` (`invoice_payment_transaction_id`),
    KEY `invoice_payment_transaction_items_invoice_id_index` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
