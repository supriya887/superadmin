-- Upgrade cart: separate coupon discount from pro-rata credit (run manually)

ALTER TABLE `carts`
    ADD COLUMN `pro_rata_adjustment` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `discount_amount`;
