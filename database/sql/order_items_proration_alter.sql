-- Proration audit columns for mid-cycle upgrade order_items (run manually if not using artisan migrate)

ALTER TABLE `order_items`
    ADD COLUMN IF NOT EXISTS `remaining_days` INT UNSIGNED NULL DEFAULT NULL AFTER `subscription_end_date`,
    ADD COLUMN IF NOT EXISTS `total_plan_days` INT UNSIGNED NULL DEFAULT 365 AFTER `remaining_days`,
    ADD COLUMN IF NOT EXISTS `prorated_credit` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `total_plan_days`;

ALTER TABLE `order_items` MODIFY `plan_id` BIGINT UNSIGNED NULL;
ALTER TABLE `order_items` MODIFY `variant_id` BIGINT UNSIGNED NULL;
