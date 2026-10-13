-- Upgrade proration: required columns on orders + order_items (run manually in MySQL/MariaDB)
-- Skip any line that errors if the column already exists.

-- ---------------------------------------------------------------------------
-- orders: subscription cycle + GST (used by RenewalService::calculateUpgradeProration)
-- ---------------------------------------------------------------------------
ALTER TABLE `orders`
    ADD COLUMN `subscription_start_date` DATE NULL DEFAULT NULL AFTER `duration`;

ALTER TABLE `orders`
    ADD COLUMN `subscription_end_date` DATE NULL DEFAULT NULL AFTER `subscription_start_date`;

ALTER TABLE `orders`
    ADD COLUMN `apply_gst` TINYINT(1) NOT NULL DEFAULT 1 AFTER `gst_id`;

ALTER TABLE `orders`
    ADD COLUMN `gst_rate` DECIMAL(5,2) NOT NULL DEFAULT 18.00 AFTER `apply_gst`;

ALTER TABLE `orders`
    ADD COLUMN `order_category` ENUM('new','renewal','upgrade') NOT NULL DEFAULT 'new' AFTER `order_type`;

-- ---------------------------------------------------------------------------
-- order_items: proration audit fields (stored on upgrade order line)
-- ---------------------------------------------------------------------------
ALTER TABLE `order_items`
    ADD COLUMN `remaining_days` INT UNSIGNED NULL DEFAULT NULL AFTER `subscription_end_date`;

ALTER TABLE `order_items`
    ADD COLUMN `total_plan_days` INT UNSIGNED NULL DEFAULT 365 AFTER `remaining_days`;

ALTER TABLE `order_items`
    ADD COLUMN `prorated_credit` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `total_plan_days`;

ALTER TABLE `order_items` MODIFY `plan_id` BIGINT UNSIGNED NULL;
ALTER TABLE `order_items` MODIFY `variant_id` BIGINT UNSIGNED NULL;
