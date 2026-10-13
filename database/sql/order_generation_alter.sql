-- Order generation schema changes (run manually — do not use php artisan migrate)
-- Uses EXISTING table: partner_aadhaar_verifications
--   (id, customer_id, partner_id, cart_id, order_id, aadhaar_number, aadhaar_masked,
--    otp, aadhaar_ref_no, verification_mode, status, verified_at, created_at, updated_at)
--   status values: init | verified | order_linked

-- ---------------------------------------------------------------------------
-- 1) partners: Aadhaar mandatory flag
-- ---------------------------------------------------------------------------
ALTER TABLE `partners`
    ADD COLUMN IF NOT EXISTS `aadhaar_mandatory` ENUM('yes','no') NOT NULL DEFAULT 'no'
    AFTER `status`;

-- MySQL < 8.0.12 does not support IF NOT EXISTS on ADD COLUMN; use this instead if needed:
-- ALTER TABLE `partners` ADD COLUMN `aadhaar_mandatory` ENUM('yes','no') NOT NULL DEFAULT 'no';

-- ---------------------------------------------------------------------------
-- 2) order_po_details: PO links + status + signature
-- ---------------------------------------------------------------------------
ALTER TABLE `order_po_details`
    ADD COLUMN IF NOT EXISTS `sign_required` ENUM('yes','no') NOT NULL DEFAULT 'no'
    AFTER `journey_status`;

ALTER TABLE `order_po_details`
    ADD COLUMN IF NOT EXISTS `draft_po_link` VARCHAR(500) NULL DEFAULT NULL
    AFTER `po_document_url`;

ALTER TABLE `order_po_details`
    ADD COLUMN IF NOT EXISTS `final_po_link` VARCHAR(500) NULL DEFAULT NULL
    AFTER `draft_po_link`;

ALTER TABLE `order_po_details`
    ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) NOT NULL DEFAULT 'pending'
    COMMENT 'pending | final'
    AFTER `journey_status`;

-- MySQL < 8.0.12 (no IF NOT EXISTS):
-- ALTER TABLE `order_po_details` ADD COLUMN `draft_po_link` VARCHAR(500) NULL AFTER `po_document_url`;
-- ALTER TABLE `order_po_details` ADD COLUMN `final_po_link` VARCHAR(500) NULL AFTER `draft_po_link`;
-- ALTER TABLE `order_po_details` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER `journey_status`;
-- ALTER TABLE `order_po_details` ADD COLUMN `sign_required` ENUM('yes','no') NOT NULL DEFAULT 'no' AFTER `journey_status`;
