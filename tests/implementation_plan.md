# Implementation Plan - Subscription Plans Backend

Build a robust backend system for managing subscription plans, including database structure, seeders, and APIs for retrieval and order calculation.

## Proposed Changes

### Database Layer
Create the foundation for storing and managing subscription plans.

#### [NEW] [2026_03_18_000000_create_plans_table.php](file:///c:/xampp/htdocs/laravel/database/migrations/2026_03_18_000000_create_plans_table.php)
- Define `plans` table with fields: `id`, `name`, `slug`, `storage`, `price`, `description`, `is_active`, and `timestamps`.

#### [NEW] [Plan.php](file:///c:/xampp/htdocs/laravel/app/Models/Plan.php)
- Eloquent model for the `plans` table.

#### [NEW] [PlanSeeder.php](file:///c:/xampp/htdocs/laravel/database/seeders/PlanSeeder.php)
- Insert default plans: Basic (₹792), Silver (₹1716), and Corporate (₹8000).

---

### API and Logic Layer
Implement the business logic and API endpoints.

#### [NEW] [PlanController.php](file:///c:/xampp/htdocs/laravel/app/Http/Controllers/Home/PlanController.php)
- `index()`: Returns all active plans as JSON.
- `calculateOrder(Request $request)`: Validates inputs and returns calculated subtotal and total based on `plan_id`, `duration`, and `quantity`.

#### [MODIFY] [api.php](file:///c:/xampp/htdocs/laravel/routes/api.php)
- Register `GET /plans` and `POST /calculate-order` routes.

#### [MODIFY] [web.php](file:///c:/xampp/htdocs/laravel/routes/web.php)
- Update or ensure `/select-plan` route exists and can handle an optional `plan_id`.

---

### UI Integration (Logic Only)
Update the "Buy Now" links to pass the selected plan ID.

#### [MODIFY] [index.blade.php](file:///c:/xampp/htdocs/laravel/resources/views/home/index.blade.php)
- Update "Buy Now" links to include the plan ID (e.g., `/select-plan?plan_id=1`).

## Verification Plan

### Automated Tests
- No existing automated tests were found for plans. I will verify using manual API calls.

### Manual Verification
1. **Migration & Seeding**:
   - Run `php artisan migrate` and `php artisan db:seed --class=PlanSeeder`.
   - Verify table content via `php artisan tinker` or database tool.
2. **API GET /plans**:
   - Call `GET http://127.0.0.1:8000/api/plans`.
   - Expect: JSON list of 3 active plans.
3. **API POST /calculate-order**:
   - Call `POST http://127.0.0.1:8000/api/calculate-order` with:
     ```json
     {
       "plan_id": 1,
       "duration": 12,
       "quantity": 5
     }
     ```
   - Expect: Correct calculation (792 * 5 * 12 = 47520).
4. **Link Verification**:
   - Click "Buy Now" on [index.blade.php](file:///c:/xampp/htdocs/laravel/resources/views/home/index.blade.php).
   - Verify it redirects to `/select-plan?plan_id=X`.
