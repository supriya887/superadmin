# Add Order — Technical Documentation

**Module:** Super Admin → Transactions → Orders → *Add Order*
**Primary controller:** `App\Http\Controllers\SuperAdmin\BillingController`
**Primary view:** `resources/views/superadmin/orders/form.blade.php`
**Framework:** Laravel 10, MySQL/MariaDB, Blade + jQuery + AJAX

---

## 1. Purpose

The Add Order flow lets a super-admin record a new subscription order (B2C or B2B) for a customer. It collects:

1. **Provider + Plan + Quantity** (and optional promo) — Step 1.
2. **PO Details** (purchase-order metadata) — Step 2 (B2B only).
3. **Aadhaar OTP verification** — Step 3 (B2B only).

The flow then persists the order, opens a wallet/credit ledger entry (B2B), generates a GST invoice, optionally generates a Sandbox e-invoice (IRN + QR), and emails the customer.

---

## 2. URL Map (Routes)

All routes are inside the `superadmin.` route-group prefix (`routes/web.php`, lines 244–263) and one API endpoint pair (`routes/api.php`, lines 124–125).

| Method   | URI                                                  | Route name                                | Controller method                | Purpose |
|----------|------------------------------------------------------|-------------------------------------------|----------------------------------|---------|
| GET      | `/superadmin/orders-add`                             | `superadmin.orders.create`                | `create`                         | Redirects to step 1 |
| GET      | `/superadmin/orders-add/{step}/{id?}`                | `superadmin.orders.create.step`           | `createStep`                     | Renders the wizard for step 1/2/3 |
| POST     | `/superadmin/orders-store`                           | `superadmin.orders.store`                 | `store` → delegates to `saveStep` | Legacy entry; still works |
| POST     | `/superadmin/orders-add/{step}/{id?}`                | `superadmin.orders.save.step`             | `saveStep`                       | Saves a wizard step |
| GET      | `/superadmin/orders-plans-by-provider`               | `superadmin.orders.plans_by_provider`     | `orderPlansByProvider`           | AJAX: plans for provider+variant |
| GET      | `/superadmin/orders-customer-domains/{id}`           | `superadmin.orders.customer_domains`      | `customerDomains`                | AJAX: domains used previously by customer |
| GET      | `/superadmin/orders-customer-meta/{id}`              | `superadmin.orders.customer_meta`         | `customerOrderMeta`              | AJAX: customer profile + order count |
| GET      | `/superadmin/orders-check-domain?domain_name=...`    | `superadmin.orders.domain_check`          | `checkDomainAvailability`        | AJAX: domain registered? |
| GET      | `/superadmin/orders-edit/{id}`                       | `superadmin.orders.edit`                  | `edit` → redirect to step 1 |  |
| POST     | `/superadmin/orders-update/{id}`                     | `superadmin.orders.update`                | `update` → delegates to `saveStep` |  |
| GET      | `/superadmin/orders-details/{id}`                    | `superadmin.orders.details`               | `orders_details`                 | Final landing page |
| **API**  | `POST /api/v1/orders/aadhaar/init`                   | `api.v1.orders.aadhaar.init`              | `aadharVerifyInit`               | Step 3: send Aadhaar OTP |
| **API**  | `POST /api/v1/orders/aadhaar/otp-verify`             | `api.v1.orders.aadhaar.otp_verify`        | `aadharVerifyOtp`                | Step 3: verify Aadhaar OTP |

> **Note on `{step}`:** server-side clamped to `max(1, min(3, $step))`.

---

## 3. High-Level Flow

```
                ┌────────────────────────────────────────────┐
                │  GET /superadmin/orders-add  → step 1 form │
                │  (BillingController::createStep)           │
                └─────────────────────┬──────────────────────┘
                                      │ user fills provider/plan/qty
                                      ▼
                ┌────────────────────────────────────────────┐
                │  POST /orders-add/1                        │
                │  saveStep(step=1, id=null)                 │
                │   1. validateOrderRequest()                │
                │   2. DB::transaction:                      │
                │       a. buildOrderPayload()               │
                │       b. nextOrderIdentifiers()            │
                │       c. INSERT INTO orders                │
                │       d. syncB2bOrderWalletLedger()        │
                │       e. ensureGstInvoiceForOrder()        │
                │       f. maybeGenerateSandboxEInvoice()    │
                └─────────────────────┬──────────────────────┘
                                      │
              ┌───────────────────────┴────────────────────────┐
              │                                                │
       order_type = b2c                              order_type = b2b
              │                                                │
              ▼                                                ▼
   redirect → orders.details              redirect → step 2 (PO Details)
                                                              │
                                              POST /orders-add/2/{id}
                                              upsertOrderPoDetails()
                                                              │
                                                              ▼
                                                      step 3 (Aadhaar)
                                                              │
                                  ┌───────────────────────────┴─────────────┐
                                  │   AJAX: api.v1.orders.aadhaar.init      │
                                  │   AJAX: api.v1.orders.aadhaar.otp_verify│
                                  └───────────────────┬─────────────────────┘
                                                      │
                                  POST /orders-add/3/{id}    (final commit)
                                   - mark journey_status=placed
                                   - status=completed (if Aadhaar verified)
                                   - sendOrderPlacedClientMail()
                                                      │
                                                      ▼
                                          redirect → orders.details
```

---

## 4. Database Tables Touched

### 4.1 `orders` (primary)

Created by `database/migrations/2026_03_18_072000_create_orders_table.php`.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | |
| `order_no` | VARCHAR(20) UNIQUE | `TNX00001`, generated by `nextOrderIdentifiers()` |
| `sr_no` | INT | Monotonic |
| `user_id` | BIGINT | FK → `customers.id` |
| `partner_id` | BIGINT NULL | FK → `partners.id`, B2B only |
| `order_type` | VARCHAR(10) | `b2b` \| `b2c` |
| `order_category` | ENUM | `new` \| `renewal` \| `upgrade` (auto-detected on create — see §6.4) |
| `credit_used` | DECIMAL(15,2) | Wallet+credit applied (B2B) |
| `plan_id` | BIGINT | FK → `plans.id` |
| `domain_name` | VARCHAR(255) | Validated as registered (§6.5) |
| `price` | DECIMAL(10,2) | Unit price |
| `quantity` | INT | ≥ 1 |
| `duration` | VARCHAR | From plan billing cycle |
| `promo_code`, `discount_amount` | | Promo handling (§6.3) |
| `tax_amount`, `gst_rate`, `gst_amount`, `apply_gst` | | GST split |
| `subtotal`, `total_amount` | DECIMAL | |
| `payment_mode`, `bank_name`, `cheque_ref_no`, `upi_transaction_id` | | Conditional payment fields |
| `subscription_start_date`, `subscription_end_date` | DATE | Auto-derived from plan billing cycle (§6.6) |
| `renewal_at` | DATE | |
| `status` | VARCHAR | `pending` \| `completed` \| ... (lifecycle in §6.7) |
| `irn`, `signed_qr_code`, `signed_invoice`, `ack_no`, `ack_date`, `is_cancelled` | | Sandbox e-invoicing (§7.4) |
| `created_at`, `updated_at` | TIMESTAMPS | |

> The controller uses `Schema::hasColumn()` defensively — every field above is gated, so older deployments missing a column won't crash.

### 4.2 `order_po_details` (B2B only)

Created independently. Written via `upsertOrderPoDetails()`.

| Column | Notes |
|---|---|
| `order_id` | FK → `orders.id` |
| `po_mode` | Always `'generated'` in current flow (`normalizePoInputsForValidation`) |
| `po_number`, `po_date`, `po_amount` | Auto-filled when blank (next sequential `PONxxxxx`, today, order.amount) |
| `po_document_url` | If a `po_document` PDF was uploaded (≤ 5 MB) |
| `manual_name`, `manual_email`, `manual_mobile`, `manual_gstin`, `manual_company_name`, `manual_company_address`, `manual_city`, `manual_state`, `manual_country`, `manual_pincode` | Buyer/PO contact override |
| `aadhaar_masked`, `aadhaar_ref_no` | Filled by `aadharVerifyInit` |
| `aadhar_verified` (TINYINT), `aadhar_verified_at` | Flipped by `aadharVerifyOtp` |
| `journey_status` | `po_pending` → `po_done` → `aadhar_done` → `otp_done` → `placed` |

### 4.3 `invoice_mst` + `invoice_sub` (GST invoice)

Filled by `ensureGstInvoiceForOrder()` immediately after the order insert. Uses `App\Services\InvoiceService` if invoice prefix + financial year are configured; otherwise falls back to `insertGstInvoiceFallback()`.

### 4.4 `account_masters` + `ledgers` (B2B wallet/credit)

Synced by `App\Services\PartnerCreditService::syncB2bOrderWalletLedger()` so the partner's wallet balance and credit ledger reflect the new order.

### 4.5 `plans`, `customers`, `partners`, `providers`, `variants`

Read-only lookups for the form (`getPlanOptions`, `getOrderFormCustomers`, `getPartnerOptions`, `buildOrderFormProviderMeta`, `getPlansForProviderVariant`).

---

## 5. The Multi-Step Form (Frontend)

`resources/views/superadmin/orders/form.blade.php`

### 5.1 Tabs

```html
<ul class="nav nav-tabs" id="orderStepTabs">
  <li>Step 1 — Order</li>
  <li class="d-none-when-not-b2b">Step 2 — PO Details</li>
  <li class="d-none-when-not-b2b">Step 3 — Aadhaar Details</li>
</ul>
```

Tabs 2 & 3 are hidden for B2C and disabled for B2B until step 1 returns the new `order.id` (`$wizardOrderId`). Server controls visibility via `$showPoTab`, `$showAadhaarTab`, `$skipProviderStep`.

### 5.2 Form action

```html
<form action="{{ route('superadmin.orders.save.step',
                       ['step' => $currentStep, 'id' => $wizardOrderId]) }}">
```

After step 1 successfully saves a B2B order, the JS rewrites the action to `/superadmin/orders-add/2/{id}` and progresses the tab.

### 5.3 AJAX endpoints used by the form

| JS variable | Route | Purpose |
|---|---|---|
| `plansUrl` | `superadmin.orders.plans_by_provider` | Refresh plan dropdown when provider/variant changes |
| `custDomainsUrl` | `superadmin.orders.customer_domains` | Suggest domains the customer has used before |
| `custMetaUrl` | `superadmin.orders.customer_meta` | Auto-fill name/email/mobile/GSTIN; first-vs-renewal hint |
| `domainCheckUrl` | `superadmin.orders.domain_check` | Block submit if domain isn't registered |
| `aadhaarOtpInitApiUrl` | `api.v1.orders.aadhaar.init` | Step 3 “Send OTP” button |
| `aadhaarOtpVerifyApiUrl` | `api.v1.orders.aadhaar.otp_verify` | Step 3 OTP verification |

---

## 6. Step 1 — Order Details (Deep Dive)

Entry: `BillingController::saveStep($request, 1, $id)` — `app/Http/Controllers/SuperAdmin/BillingController.php` lines **1024–1071**.

### 6.1 Pre-validation normalisation

```php
$this->normalizePoInputsForValidation($request);   // forces po_mode='generated'
$this->validateOrderRequest($request, $id === null);
```

### 6.2 Validation rules (`validateOrderRequest`, lines 2275–2417)

The rule list is **dynamic** — every rule is gated by `Schema::getColumnListing('orders')`, so it adapts to the live schema.

Required fields:

```php
'user_id'      => required, integer
'partner_id'   => nullable; required_if order_type ∈ {b2b, B2B}
'plan_id'      => nullable, integer
'domain_name'  => required, string, max 255
'order_type'   => required, in {b2b, b2c, B2B, B2C}
'amount'       => required, numeric, min 0     // when amount column resolves
```

Optional fields:

```php
'order_no'        => nullable, max 20, unique:orders,order_no
'payment_mode'    => in {cash_bank, cash_hand, upi}
'bank_name'       => string, max 255 (cheque/cash_bank)
'cheque_ref_no'   => string, max 255 (cash_bank)
'upi_transaction_id' => string, max 255 (upi)
'order_note'      => string
'promocode'       => string, max 100
'discount_amount' => numeric, min 0
'apply_gst'       => in {0,1}
'gst_rate'        => numeric, 0–100
'gst_amount', 'tax_amount' => numeric, min 0
'po_document'     => file, mimes:pdf, max 5 MB
... full PO buyer fields ...
'po_journey_status' => in {po_pending, po_done, aadhar_done, otp_done, placed}
'status'           => string, max 50
'credit_used'      => numeric, min 0
'quantity'         => integer, min 1
```

After Laravel rules pass, an extra **domain registration check** runs only on create:

```php
if ($isCreate && in_array('domain_name', $orderColumns, true)) {
    $openForRegistration = $this->isDomainAvailableForRegistration($domainName);
    if ($openForRegistration === true) {
        throw ValidationException::withMessages([
            'domain_name' => 'This domain is not registered yet. Buy and register the domain first.',
        ]);
    }
}
```

If the third-party API throws (config missing, network down), the check is **skipped silently** — order placement continues so an outage on the registrar API never blocks revenue.

### 6.3 Payload assembly (`buildOrderPayload`, lines 2535–2684)

Steps:

1. **Field mapping** — request keys are mapped to DB columns, gated by schema.
2. **System-controlled `status`** —
   - **Create + B2C** → `completed`
   - **Create + B2B** → `pending` (becomes `completed` only after Aadhaar OTP)
   - **Update** → status preserved (the field is `unset`)
3. **`apply_gst` cleanup** — when 0, all GST columns are zeroed.
4. **`quantity`** — defaults to 1 on create.
5. **Promo pricing** (`applyPromoPricingOnPayload`) —
   - Resolves `baseAmount = plan.price × qty` (B2B/B2C aware).
   - If a `promocode` is supplied, looks it up in `promocodes` (`active()` scope), throws validation error if not found.
   - Computes `discount = baseAmount × (promo.discount_percent / 100)`.
   - Writes back `promo_code`, `discount_amount`, and the resolved amount column.
6. **`order_category` auto-detection** —
   - Counts existing B2B orders for `(user_id, partner_id)`.
   - If found → `renewal`, otherwise `new`.
7. **B2C cleanup** — `partner_id = null`, `credit_used = 0`.
8. **B2B credit** — `credit_used = amount`; `partner_id` falls back to `customers.partner_id` if not posted.
9. **Subscription dates** (`applySubscriptionDatesToOrderPayload`) — only on create:
   - If both `subscription_start_date` & `subscription_end_date` posted, parse and use them.
   - Else compute from plan `billing_cycle`:
     - `monthly` → `+1 month`
     - `12 month` / `12 months` → `+365 days`
     - everything else → `+1 year`

### 6.4 Identifier generation (`nextOrderIdentifiers`, lines 932–949)

Inside the same `DB::transaction` (so it's race-safe):

```php
$nextSrNo = (int) DB::table('orders')->lockForUpdate()->max('sr_no') + 1;
$prefix   = readOrderPrefix();           // from admin_settings.order_prefix, default 'TNX'
$orderNo  = $prefix . str_pad($nextSrNo, 5, '0', STR_PAD_LEFT);
while (orders.order_no exists) { nextSrNo++; $orderNo = ...; }
```

### 6.5 Domain registration check (`isDomainAvailableForRegistration`, lines 2419–2470)

- Reads credentials from `services.domaincheck.*` (env-backed) **and** allows DB overrides via `pv_setting` table (`domaincheck_base_url`, `domaincheck_auth_userid`, `domaincheck_api_key`).
- Calls `GET /api/domains/available.json?auth-userid=…&api-key=…&domain-name[]=sld&tlds[]=tld` against `https://domaincheck.httpapi.com`.
- `extractDomainAvailabilityFromResponse()` walks the JSON looking for `status` ∈ `{available, regthroughothers, registered, …}`.
- **Returns `true` ⇒ domain is OPEN (not registered)** → block placement.
- Returns `false` ⇒ domain is registered → allow placement.

### 6.6 Insert + side-effects

```php
DB::transaction(function () use ($request, $id) {
    $payload = buildOrderPayload($request, $id === null);
    if ($id === null) {
        // assign order_no/sr_no
        $oid = DB::table('orders')->insertGetId($payload);
        $this->syncB2bOrderWalletLedger($oid, $payload);   // PartnerCreditService
        $this->ensureGstInvoiceForOrder((int) $oid);       // InvoiceService → invoice_mst/sub
        $this->maybeGenerateSandboxEInvoice((int) $oid);   // SandboxEInvoiceService
        return $oid;
    }
    // edit path: update + re-sync
    DB::table('orders')->where('id', $id)->update($payload);
    $this->syncB2bOrderWalletLedger($id, (array) DB::table('orders')->where('id', $id)->first());
    $this->syncInvoiceForUpdatedOrder($id);
    $this->maybeGenerateSandboxEInvoice($id);
    return $id;
});
```

### 6.7 Status lifecycle

| Path | Initial | Final |
|---|---|---|
| B2C create | `completed` | `completed` |
| B2B create | `pending` | `completed` after Aadhaar OTP success |
| B2B Aadhaar skipped | `pending` | stays `pending` until verified |

`orderSuccessfulStatuses()` returns `['completed', 'paid', 'success', 'active']` — used by listings & metrics.

### 6.8 Redirect

| order_type | After step 1 |
|---|---|
| `b2c` | → `superadmin.orders.details/{id}` (`Order saved successfully.`) |
| `b2b` | → step 2 (`Order details saved. Continue with PO details.`) |

---

## 7. Step 2 — PO Details (B2B only)

Entry: `saveStep($request, 2, $id)` → `upsertOrderPoDetails($id, $request)` — lines **3130–3224**.

Behaviour:

- **Auto-numbering** — `po_number` defaults to next sequential `PONxxxxx` (pulled from existing `order_po_details.po_number`).
- **Auto-date** — `po_date` defaults to the order's `created_at`.
- **Auto-amount** — `po_amount` defaults to the order's `amount`/`total_amount`/`price`/`subtotal` (first non-zero wins), falling back to the posted `amount` field.
- **PO PDF upload** — if a `po_document` file is posted, it's stored at `storage/app/public/order-po/...` and `po_document_url = Storage::url($stored)` is saved.
- Sets `journey_status = 'po_done'` on completion.
- Idempotent: `updateOrInsert(['order_id' => $orderId], $payload)`.

Redirect → step 3 (`PO details saved. Continue with Aadhaar verification.`).

---

## 8. Step 3 — Aadhaar OTP (B2B only)

Two AJAX calls happen first, then a final `POST /superadmin/orders-add/3/{id}` finalises the wizard.

### 8.1 `POST /api/v1/orders/aadhaar/init` → `aadharVerifyInit` (lines 822–874)

Request:

```json
{ "order_id": 123, "aadhaar_number": "123456789012" }
```

- Validates `digits:12`.
- Stores `aadhaar_masked = XXXXXXXX1234` and a fresh `aadhaar_ref_no = AADH-XXXXXXXX`.
- Sets `journey_status = 'po_done'`, `aadhar_verified = 0`.
- Marks `orders.status = 'pending'`.

Response:

```json
{
  "success": true,
  "data": { "aadhaar_masked": "XXXXXXXX1234", "aadhaar_ref_no": "AADH-XXXXXXXX" }
}
```

### 8.2 `POST /api/v1/orders/aadhaar/otp-verify` → `aadharVerifyOtp` (lines 876–925)

Request:

```json
{ "order_id": 123, "otp": "123456" }
```

- Validates `digits:6`. (No real OTP provider check here — placeholder for the third-party Aadhaar gateway. Plug your provider's verify call inside this method.)
- Sets `journey_status = 'placed'`, `aadhar_verified = 1`, `aadhar_verified_at = now()`.
- Marks `orders.status = 'completed'`.
- Calls `sendOrderPlacedClientMail($orderId)`.

Response:

```json
{
  "success": true,
  "redirect_url": "/superadmin/orders-details/123"
}
```

### 8.3 Final step-3 POST

`saveStep($request, 3, $id)` (lines 1090–1124):

1. Forces `po_journey_status = 'placed'` and runs `upsertOrderPoDetails()` again.
2. If Aadhaar **was** verified → status stays `completed`, calls `sendOrderPlacedClientMail()`.
3. If Aadhaar **was not** verified (admin bypassed the OTP) → status downgraded to `pending` and a warning flash is shown.
4. Honours `post_submit_action` (`list` → orders index, default → details page).

---

## 9. Side-Effect Services

### 9.1 `PartnerCreditService::syncB2bOrderWalletLedger($orderId, $payload)`

Maintains the partner's running wallet balance and credit ledger. Called for B2B orders on every insert and update.

### 9.2 `InvoiceService::createInvoice($payload)`

Creates the GST invoice (`invoice_mst` master + `invoice_sub` line items). Resolves invoice number from financial-year + invoice prefix configured in `admin_settings`. Falls back to `insertGstInvoiceFallback()` when prefix/FY isn't configured (logged as a warning, never blocks).

Called automatically immediately after the order is inserted via `ensureGstInvoiceForOrder($orderId)`.

### 9.3 `SandboxEInvoiceService::generateForOrder($orderId)`

When `SANDBOX_AUTO_GENERATE=true` (default) and the e-invoice columns exist (`irn`, `signed_qr_code`, …), this fires after every successful insert/update. Wrapped in `maybeGenerateSandboxEInvoice()` — exceptions are swallowed and logged, **never** roll back the order.

Stores: `irn`, `signed_qr_code`, `signed_invoice`, `ack_no`, `ack_date` on the `orders` row. See `app/Services/SandboxEInvoiceService.php` and `docs/` SandboxEInvoiceService.

### 9.4 Customer email — `sendOrderPlacedClientMail($orderId)` (lines 3807–3842)

- Sends `App\Mail\OrderPlacedClientMail` to `customer_email`.
- Attaches the auto-generated PO PDF (if `po_mode = 'generated'`) using `dompdf`/`barryvdh/laravel-dompdf` if available, otherwise an HTML attachment.
- Falls back silently on any failure.

---

## 10. Sequence Diagram (Step 1 — B2B)

```
Admin browser           BillingController          DB                   PartnerCreditService     InvoiceService     SandboxEInvoiceService
     │                          │                   │                            │                     │                       │
     │ POST /orders-add/1       │                   │                            │                     │                       │
     ├─────────────────────────►│                   │                            │                     │                       │
     │                          │ validateOrderRequest()                          │                     │                       │
     │                          │ DB::transaction { │                            │                     │                       │
     │                          │   buildOrderPayload()                          │                     │                       │
     │                          │   nextOrderIdentifiers() (lockForUpdate)        │                     │                       │
     │                          │   INSERT orders   │                            │                     │                       │
     │                          ├──────────────────►│                            │                     │                       │
     │                          │   syncB2bOrderWalletLedger()                   │                     │                       │
     │                          ├──────────────────────────────────────────────►│                     │                       │
     │                          │                   │ INSERT account/ledger     │                     │                       │
     │                          │   ensureGstInvoiceForOrder()                   │                     │                       │
     │                          ├──────────────────────────────────────────────────────────────────►│                       │
     │                          │                   │ INSERT invoice_mst/sub                            │                       │
     │                          │   maybeGenerateSandboxEInvoice()                                     │                       │
     │                          ├────────────────────────────────────────────────────────────────────────────────────────►│
     │                          │                   │ POST /authenticate (cached) + /e-invoice/generate                  │
     │                          │ }                 │                            │                     │                       │
     │ 302 → step 2             │                   │                            │                     │                       │
     │◄─────────────────────────┤                   │                            │                     │                       │
```

---

## 11. Error Handling & Idempotency

| Concern | How it's handled |
|---|---|
| Race on `order_no`/`sr_no` | `DB::transaction` + `lockForUpdate()` + while-exists fallback (`nextOrderIdentifiers`) |
| Missing column on older deploy | Every read/write gated by `Schema::hasColumn()` / `Schema::hasTable()` |
| Domain registrar API down | Soft-fail: order placement continues, only the explicit-error path blocks (when API is reachable AND returns "available") |
| Promo lookup fails | `ValidationException` (HTTP 422) with field-level error |
| Invoice creation fails | Falls back to `insertGstInvoiceFallback()`; warning logged |
| Sandbox e-invoice fails | Wrapped in `maybeGenerateSandboxEInvoice()`; warning logged; order saves normally |
| Email send fails | Try/catch in `sendOrderPlacedClientMail()`; never bubbles |
| Wallet ledger fails | Bubbles inside transaction → entire order insert is rolled back (intentional) |

---

## 12. Permissions / Auth

The whole route group is protected by the `superadmin.` prefix middleware stack. Verify with:

```bash
php artisan route:list --name=superadmin.orders
```

The form submits with the standard CSRF token (`<meta name="csrf-token">`). The Aadhaar API endpoints are under `routes/api.php` and use API-token auth; the form passes the admin's session token automatically through the global jQuery setup.

---

## 13. Configuration Touch-Points

`config/services.php`:

```php
'domaincheck' => [
    'base_url'    => env('DOMAINCHECK_BASE_URL', 'https://domaincheck.httpapi.com'),
    'auth_userid' => env('DOMAINCHECK_AUTH_USERID'),
    'api_key'     => env('DOMAINCHECK_API_KEY'),
],
'sandbox' => [   // Sandbox e-invoicing
    'base_url'      => env('SANDBOX_BASE_URL', 'https://api.sandbox.co.in'),
    'api_key'       => env('SANDBOX_API_KEY'),
    'api_secret'    => env('SANDBOX_SECRET'),
    'auto_generate' => env('SANDBOX_AUTO_GENERATE', true),
],
```

`admin_settings`:

| Setting | Used for |
|---|---|
| `order_prefix` | Order number prefix (default `TNX`) |
| `invoice_prefix`, `financial_year` | GST invoice numbering |
| `gstin`, `state_code`, `address`, `title`, ... | Seller meta on invoice + Sandbox payload |

`pv_setting` (key/value) — optional override for Domaincheck creds:
- `domaincheck_base_url`, `domaincheck_auth_userid`, `domaincheck_api_key`

---

## 14. Manual QA Checklist

**B2C happy path**
- [ ] `Add Order` → pick provider → pick plan → enter qty → enter known registered domain → save → `status=completed`, redirected to details.
- [ ] Promo code applied: `discount_amount` and `total_amount` reflect the percentage.
- [ ] Apply GST off: `gst_amount = 0`.

**B2B happy path**
- [ ] Step 1 saved → tab moves to Step 2 with `wizardOrderId`.
- [ ] Step 2 saved with PO PDF upload → `order_po_details.po_document_url` populated; `po_number` auto-generated.
- [ ] Step 3 → Send OTP → mock OTP `123456` → status flips to `completed`, customer email fires, redirect to details.
- [ ] Skipping Step 3 OTP submit → status `pending`.

**Edge cases**
- [ ] Unknown domain blocks save with the right field error.
- [ ] Promo `INVALID` → 422 on `promocode` field.
- [ ] Amount column resolution — confirm `getAmountColumn()` returns the column you expect (looks at `amount`, `total_amount`, `subtotal` in priority).
- [ ] Wallet/credit ledger entry is created/updated (B2B only).
- [ ] `irn` column populated when Sandbox auto-gen is on and credentials are valid.

---

## 15. Known Limitations / TODO

1. **Aadhaar OTP is currently a stub** — `aadharVerifyOtp` accepts any 6-digit OTP. Wire the real Aadhaar gateway inside this method.
2. **Domain registrar response shape varies** — `extractDomainAvailabilityFromResponse()` covers common keys (`status`, `available`); extend it if a new provider is plugged in.
3. **No row-level locking on `orders.id`** — the wizard relies on `wizardOrderId` from the client. Two admins editing the same draft simultaneously will last-write-wins.
4. **PO PDF storage** — files go to `storage/app/public/order-po/`. Make sure `php artisan storage:link` is run on every environment.
5. **Status options are hard-coded** — see `statusOptions()`. Move to `admin_settings` if business needs custom statuses.

---

## 16. File Map (quick reference)

| File | Role |
|---|---|
| `routes/web.php` (244–263) | Web routes |
| `routes/api.php` (124–125) | Aadhaar OTP API routes |
| `app/Http/Controllers/SuperAdmin/BillingController.php` | All controller actions |
| `app/Models/Order.php` | Eloquent model + `$fillable`/`$casts` |
| `resources/views/superadmin/orders/form.blade.php` | Wizard UI + AJAX |
| `app/Services/InvoiceService.php` | GST invoice creation |
| `app/Services/PartnerCreditService.php` | Wallet/credit ledger |
| `app/Services/SandboxEInvoiceService.php` | Sandbox IRN/QR |
| `app/Mail/OrderPlacedClientMail.php` | Customer notification |
| `database/migrations/2026_03_18_072000_create_orders_table.php` | Base orders schema |
| `database/migrations/...add_einvoice_fields_to_orders_table.php` | (deleted) — see ALTER SQL in Sandbox docs |
