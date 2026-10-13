-- ---------------------------------------------------------------------------
-- orders: drop legacy Aadhaar duplicate columns
--
-- Reason:
--   * Aadhaar journey / OTP / masked / ref-no / verified flags now live on
--     `order_po_details` (PO journey) and `partner_aadhaar_verifications`
--     (raw Aadhaar with OTP audit).
--   * The orders table still uses `aadhaar_verification_id` (FK → partner_aadhaar_verifications.id)
--     to link the canonical record — keep that one.
--   * Every row in the production dump has NULL in all 9 columns below.
--   * The PHP code never writes to orders.aadhaar_* — all writes go to the
--     PO-details / verification tables.
--
-- Run once. Skip any line that errors with "Unknown column" (already dropped).
-- ---------------------------------------------------------------------------

ALTER TABLE `orders` DROP COLUMN `aadhaar_verified`;
ALTER TABLE `orders` DROP COLUMN `aadhaar_verified_at`;
ALTER TABLE `orders` DROP COLUMN `aadhaar_masked`;
ALTER TABLE `orders` DROP COLUMN `aadhaar_ref_no`;
ALTER TABLE `orders` DROP COLUMN `aadhaar_name`;
ALTER TABLE `orders` DROP COLUMN `aadhaar_dob`;
ALTER TABLE `orders` DROP COLUMN `aadhaar_gender`;
ALTER TABLE `orders` DROP COLUMN `aadhaar_address`;
ALTER TABLE `orders` DROP COLUMN `aadhaar_journey_status`;

-- ---------------------------------------------------------------------------
-- KEEP (do NOT drop):
--   * `aadhaar_verification_id`  → FK to partner_aadhaar_verifications.id
--   * `plan_id`, `price`, `quantity`, `duration` → still used as the order's
--      primary plan snapshot. Per-line data is in `order_items`, but a lot of
--      reporting / wallet / invoice code reads these directly from `orders`.
-- ---------------------------------------------------------------------------
