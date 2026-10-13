-- =============================================================================
-- Sample data for B2B subscription / upgrade-downgrade modal (plans + orders)
-- Run in phpMyAdmin or MySQL client. Adjust IDs (user_id, plan_id, partner_id)
-- to match your database before executing the INSERT into `orders`.
-- =============================================================================

-- Assumes `orders.order_type` already exists (e.g. value 'b2b' for partner orders).

-- -----------------------------------------------------------------------------
-- 1) Sample plans for provider_id = 1 (Tizzy Mail style)
--    Slugs must stay UNIQUE. If inserts fail, change slugs or delete old rows.
-- -----------------------------------------------------------------------------
INSERT INTO `plans` (
    `variant_id`, `provider_id`, `category_id`, `name`, `slug`, `storage`,
    `price`, `actual_price`, `billing_cycle`, `limited_offer`, `features`,
    `customer_limit`, `description`, `status`, `created_at`, `updated_at`
) VALUES
    (NULL, 1, NULL, 'Tizzy® Mail Enterprise 100 GB', 'tizzy-mail-ent-100gb-sample', '100 GB',
     1000.00, NULL, 'yearly', 'no', NULL, 1, NULL, 1, NOW(), NOW()),
    (NULL, 1, NULL, 'Tizzy® Mail Enterprise 150 GB', 'tizzy-mail-ent-150gb-sample', '150 GB',
     1500.00, NULL, 'yearly', 'no', NULL, 1, NULL, 1, NOW(), NOW()),
    (NULL, 1, NULL, 'Tizzy® Mail Enterprise 200 GB', 'tizzy-mail-ent-200gb-sample', '200 GB',
     1800.00, NULL, 'yearly', 'no', NULL, 1, NULL, 1, NOW(), NOW()),
    (NULL, 1, NULL, 'Tizzy® Mail Enterprise 300 GB', 'tizzy-mail-ent-300gb-sample', '300 GB',
     2200.00, NULL, 'yearly', 'no', NULL, 1, NULL, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `price` = VALUES(`price`),
    `storage` = VALUES(`storage`),
    `updated_at` = NOW();

-- -----------------------------------------------------------------------------
-- 2) Example B2B order linked to a customer (replace placeholders)
--
--    @customer_id   = customers.id (must belong to a partner)
--    @plan_100_id   = id of the 100 GB plan row (from step 1 or existing data)
--
--    Example: SELECT id FROM plans WHERE slug = 'tizzy-mail-ent-100gb-sample' LIMIT 1;
-- -----------------------------------------------------------------------------
/*
INSERT INTO `orders` (
    `user_id`,
    `plan_id`,
    `domain_name`,
    `plan_name`,
    `price`,
    `quantity`,
    `duration`,
    `promo_code`,
    `discount_amount`,
    `tax_amount`,
    `total_amount`,
    `subtotal`,
    `gst_id`,
    `status`,
    `order_type`,
    `created_at`,
    `updated_at`
) VALUES (
    1,                                  -- REPLACE: customers.id
    (SELECT id FROM plans WHERE slug = 'tizzy-mail-ent-100gb-sample' LIMIT 1),
    'goyalinfotech.com',
    'Tizzy® Mail Enterprise 100 GB',
    1000.00,
    5,
    '1',                                -- term length in years (used for remaining days)
    NULL,
    0,
    0,
    5000.00,
    5000.00,
    NULL,
    'completed',
    'b2b',
    DATE_SUB(NOW(), INTERVAL 109 DAY),  -- started ~109 days ago so remaining days show in modal
    NOW()
);
*/
