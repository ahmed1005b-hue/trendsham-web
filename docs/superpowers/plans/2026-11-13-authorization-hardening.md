# Authorization Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prove, with tests, that no customer or seller can read or change another user's resources through any API or web route, close every hole the tests find, and move the ownership rules into Laravel Policies.

**Architecture:** First a test matrix: for every authenticated route that takes a resource ID, a user from tenant A calls it with tenant B's resource and must be denied (403 or 404) with no side effect. Holes found are fixed in the same task with the smallest change. Then a guard test makes the matrix mandatory for any future route, and finally the scattered `abort_unless(... user_id ...)` checks move into Policies registered in `AppServiceProvider`, with the matrix tests protecting every move.

**Tech Stack:** Laravel 12, PHP 8.2, PHPUnit 11 (`php artisan test`), Sanctum (`actingAs($user, 'sanctum')`), MySQL test DB `trendsham_testing` with `RefreshDatabase`.

**Spec:** TrendSham Ecosystem Specification v1.0, section 18.2 (pasted by the owner in the session, not stored in the repo). Binding lines are copied verbatim into Global Constraints below.

## Global Constraints

- Spec 18.2: "سياسات Laravel Policies وGates." / "اختبار منع الوصول الأفقي بين حسابات البائعين." / "التحقق من ملكية كل طلب ومنتج ومتجر."
- Spec 4.2: "لا يجوز الاعتماد على إخفاء عناصر الواجهة كوسيلة حماية؛ يجب التحقق من الصلاحيات في Laravel على كل طلب API."
- No database changes: no migrations, no column changes.
- Work directly on `master`; one commit per task (or per fix inside a task), message style `test(authz): …` / `fix(authz): …` / `refactor(authz): …`, ending with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Before every commit run `git status --short` and stage only the task's files.
- The full suite (`php artisan test`, currently 547 passing) must pass at the end of every task.
- A denial is HTTP 403 or 404. Keep each endpoint's current denial status; do not change 403↔404 on routes that already deny.
- Do not change response bodies or status codes of successful (owner) requests — the Flutter app depends on them.
- Employee routes (`role:employee`) and admin routes are platform-wide by design: they get vertical tests only (other roles are rejected), never horizontal ones.
- `app/Http/Controllers/Api/Seller/CouponController.php` is unrouted dead code: do not route, use, or delete it.
- User-facing messages in code stay Arabic (they are translated by the existing l10n layer).

## Review Focus

1. Resource IDs inside the request body (not the path) that belong to another tenant — `product_ids` in seller coupon/showcase/daily-deal creation, `customer_coupon_id` / card / address in checkout — must be rejected, not silently used. → Task 3 and Task 4 add body-ID cases.
2. Nested routes where the parent is mine but the child is not — `seller/products/{myProduct}/images/{imageOfOtherProduct}` — must be denied and must not touch the other image. → Task 3.
3. A multi-seller order: seller A opening an order that also contains seller B's items must see only A's items, never B's prices or earnings. → Task 3.
4. Lookups by string reference instead of model ID — `cards/status/{reference}`, `customer/cards/return/{reference}` — must check ownership of the referenced payment/card. → Task 2 and Task 4.
5. Withdrawal receipts and invoices are files: a seller must not download another seller's receipt, a customer must not download another customer's invoice. → Task 5.

---

## File Structure

- `tests/Concerns/BuildsTenants.php` — fixture trait: builds one complete tenant (customer + seller + their resources) and the `assertDenied()` helper. Used by every test in this plan.
- `tests/Feature/Authorization/CustomerApiAccessTest.php` — horizontal matrix, customer API routes.
- `tests/Feature/Authorization/SellerApiAccessTest.php` — horizontal matrix, seller API routes + body IDs + nested + shared order.
- `tests/Feature/Authorization/CustomerWebAccessTest.php` — horizontal matrix, customer web routes.
- `tests/Feature/Authorization/SellerWebAccessTest.php` — horizontal matrix, seller web routes + shared web routes (invoices, receipts, addresses, notifications).
- `tests/Feature/Authorization/RouteGuardTest.php` — every owner-scoped route must appear in a matrix; every role-gated route rejects the other roles.
- `app/Policies/*.php` — one Policy per owned model (Task 7, Task 8).
- `app/Providers/AppServiceProvider.php` — explicit `Gate::policy()` registration.
- Controllers listed per task — only their ownership checks change.

---

### Task 1: Tenant fixtures and the denial helper

**Files:**
- Create: `tests/Concerns/BuildsTenants.php`
- Test: `tests/Feature/Authorization/TenantFixtureTest.php`

**Interfaces:**
- Produces: trait `Tests\Concerns\BuildsTenants` with
  - `protected function tenant(string $tag): array` — creates and returns, all owned by the tenant, keys exactly: `customer` (User role customer), `seller` (User role seller), `store` (SellerProfile, status approved, `identity_verified_at` set), `product` (Product of `store`, status active, admin approved, stock 10), `image` (ProductImage of `product`), `cartItem` (CartItem of `customer` for `product`), `order` (Order of `customer`, status `delivered`, one OrderItem of `product` with `vendor_id` = store id), `orderItem`, `address` (UserAddress of `customer`), `card` (CustomerCard of `customer`, active), `payment` (Payment of `order` with a `reference`), `notification` (AppNotification of `customer`), `conversation` (buyer–seller Conversation between `customer` and `store`, one message), `question` (ProductQuestion by `customer` on `product`), `supportConversation` (StaffConversation of `store`), `deal` (DailyDeal of `product`), `story` (Story of `store`), `paymentMethod` (seller PaymentMethod), `showcase` (Showcase of `store` on a ShowcaseSlot, status pending_review), `campaign` (seller CouponCampaign of `store`, status active), `withdrawal` (WithdrawalRequest of the seller). Every name/phone/email includes `$tag` so two tenants never collide. Use `Category::firstOrCreate` for a shared category.
  - `protected function assertDenied(\Illuminate\Testing\TestResponse $response, string $case): void` — asserts status is 403 or 404, failure message includes `$case` and the actual status.

- [ ] **Step 1: Write the failing test** `TenantFixtureTest::test_two_tenants_are_complete_and_disjoint`

```php
$a = $this->tenant('a'); $b = $this->tenant('b');
foreach (['customer','seller','store','product','image','cartItem','order','orderItem','address','card','payment',
          'notification','conversation','question','supportConversation','deal','story','paymentMethod',
          'showcase','campaign','withdrawal'] as $key) {
    $this->assertNotNull($a[$key]?->getKey(), "a.$key");
    $this->assertNotSame($a[$key]->getKey(), $b[$key]->getKey(), "$key shared between tenants");
}
$this->assertSame($a['store']->id, $a['orderItem']->vendor_id);
$this->assertSame($a['customer']->id, $a['order']->user_id);
```

- [ ] **Step 2: Run it — expect FAIL (trait missing)**

Run: `php artisan test tests/Feature/Authorization/TenantFixtureTest.php`

- [ ] **Step 3: Implement the trait.** Read each model's migration/`$fillable` for required columns; create rows directly with Eloquent (only `UserFactory` exists). Do not call HTTP endpoints to build fixtures.

- [ ] **Step 4: Run it — expect PASS**, then `php artisan test` — expect all green.

- [ ] **Step 5: Commit** `test(authz): tenant fixtures for horizontal access tests`

---

### Task 2: Customer API matrix

**Files:**
- Create: `tests/Feature/Authorization/CustomerApiAccessTest.php`
- Modify (only if a case fails): the controller that serves the failing route, e.g. `app/Http/Controllers/Api/*Controller.php`

**Interfaces:**
- Consumes: `BuildsTenants::tenant()`, `assertDenied()`.
- Produces: `public const CASES` on the test class — list of `[method, uriTemplate, tenantKey, payload]`; `uriTemplate` uses `{key}` placeholders resolved from tenant B. Task 6 reads every `CASES` constant.

- [ ] **Step 1: Write the matrix test.** Attacker = tenant A `customer` (`actingAs(..., 'sanctum')`), victim = tenant B. One test method iterates `CASES`, resolves placeholders from B, calls the route, calls `assertDenied`, then asserts B's resource is unchanged (`fresh()` equals original attributes; for DELETE, still exists). `CASES` must contain exactly these routes:

```
DELETE api/v1/cards/{card}                         POST api/v1/cards/{card}/default
GET    api/v1/cards/status/{payment.reference}     DELETE api/v1/cart/{cartItem}   PUT api/v1/cart/{cartItem} ['quantity'=>2]
DELETE api/v1/profile/addresses/{address}          PUT api/v1/profile/addresses/{address}   POST api/v1/profile/addresses/{address}/default
DELETE api/v1/questions/{question}
GET    api/v1/conversations/{conversation}         POST api/v1/conversations/{conversation}/messages ['body'=>'x']
GET    api/v1/orders/{order}   GET api/v1/orders/{order}/invoice   GET api/v1/orders/{order}/items
GET    api/v1/orders/{order}/payment-status        POST api/v1/orders/{order}/cancel
POST   api/v1/notifications/{notification}/read
POST   api/v1/order-items/{orderItem}/review ['rating'=>5]   POST api/v1/order-items/{orderItem}/seller-review ['rating'=>5]
```

Add one positive control per resource family (owner A on A's own order/address/card returns 2xx) so a broken fixture cannot make the matrix pass vacuously.

- [ ] **Step 2: Run it.** `php artisan test tests/Feature/Authorization/CustomerApiAccessTest.php` — every failing case is a hole; list them in the task report.

- [ ] **Step 3: Fix each hole** with the smallest ownership check in its controller, matching the style already used there (`abort_unless($x->user_id === $request->user()->id, 403)`). One commit per hole: `fix(authz): <route> rejects other customers' <resource>`.

- [ ] **Step 4: Run the file, then `php artisan test`** — expect all green.

- [ ] **Step 5: Commit the test file** `test(authz): customer API horizontal access matrix`

---

### Task 3: Seller API matrix, body IDs, nested routes, shared orders

**Files:**
- Create: `tests/Feature/Authorization/SellerApiAccessTest.php`
- Modify (only if a case fails): `app/Http/Controllers/Api/Seller/*.php`, `app/Services/ShowcaseService.php`, `app/Coupons/SellerCampaignWriter.php`

**Interfaces:**
- Consumes/Produces: same shape as Task 2 (`public const CASES`).

- [ ] **Step 1: Write the matrix test** (attacker = tenant A `seller`, sanctum). `CASES`:

```
DELETE api/v1/seller/daily-deals/{deal}   PUT api/v1/seller/daily-deals/{deal}   POST api/v1/seller/daily-deals/{deal}/toggle
DELETE api/v1/seller/products/{product}   POST api/v1/seller/products/{product}   PUT api/v1/seller/products/{product}/quick-stock ['stock_quantity'=>99]
DELETE api/v1/seller/products/{product}/images/{image}   POST api/v1/seller/products/{product}/images/{image}/primary
DELETE api/v1/seller/stories/{story}
DELETE api/v1/seller/wallet/payment-methods/{paymentMethod}   POST api/v1/seller/wallet/payment-methods/{paymentMethod}/default
GET    api/v1/seller/showcases/{showcase}   POST api/v1/seller/showcases/{showcase}/cancel
GET    api/v1/seller/support/{supportConversation}   POST api/v1/seller/support/{supportConversation}/message ['body'=>'x']
POST   api/v1/seller/questions/{question}/answer ['answer'=>'x']
PUT    api/v1/seller/orders/{order}/status ['status'=>'shipped']
```

Plus these named tests:
- `test_nested_image_of_another_product_is_denied`: `DELETE api/v1/seller/products/{A.product}/images/{B.image}` → denied, B.image still exists.
- `test_body_ids_of_another_store_are_rejected`: creating a showcase booking, a daily deal and a seller coupon with B's `product_id(s)` → 4xx (403/404/422), and nothing is created for A.
- `test_shared_order_shows_only_own_items`: an order with one item from A's store and one from B's; A's seller order endpoint(s) return A's item and never contain B's `product_name`, `unit_price`, `vendor_earning` or `commission_amount`.
- Positive controls as in Task 2.

- [ ] **Step 2: Run it**, list holes in the report.
- [ ] **Step 3: Fix each hole** (smallest check, existing style, one commit each).
- [ ] **Step 4: Run the file, then `php artisan test`** — all green.
- [ ] **Step 5: Commit** `test(authz): seller API horizontal access matrix`

---

### Task 4: Customer web matrix

**Files:**
- Create: `tests/Feature/Authorization/CustomerWebAccessTest.php`
- Modify (only if a case fails): `app/Http/Controllers/Customer/*.php`, `app/Http/Controllers/AddressController.php`, `app/Http/Controllers/StoreFollowController.php`

- [ ] **Step 1: Write the matrix test** (attacker = tenant A `customer`, `actingAs` web guard, CSRF disabled via `withoutMiddleware(ValidateCsrfToken::class)` only). `CASES`:

```
DELETE customer/cards/{card}   POST customer/cards/{card}/default   GET customer/cards/return/{payment.reference}
DELETE customer/cart/{cartItem}   PUT customer/cart/{cartItem} ['quantity'=>2]
DELETE customer/questions/{question}
GET    customer/checkout/pay/{order}   POST customer/checkout/pay/{order}
GET    customer/conversations/{conversation}   POST customer/conversations/{conversation}/messages ['body'=>'x']
GET    customer/orders/{order}   POST customer/orders/{order}/cancel
POST   customer/order-items/{orderItem}/review ['rating'=>5]
```

Plus `test_checkout_rejects_another_customers_coupon_and_card`: web checkout with B's `customer_coupon_id` and B's card → no order discounted with B's coupon, B's coupon still `available`.

- [ ] **Steps 2–5:** run, fix holes (one commit each), full suite green, commit `test(authz): customer web horizontal access matrix`.

---

### Task 5: Seller web matrix and shared web routes

**Files:**
- Create: `tests/Feature/Authorization/SellerWebAccessTest.php`
- Modify (only if a case fails): `app/Http/Controllers/Seller/*.php`, `app/Http/Controllers/InvoiceController.php`, `app/Http/Controllers/WithdrawalController.php`, `app/Http/Controllers/AddressController.php`, `app/Http/Controllers/NotificationController.php`

- [ ] **Step 1: Write the matrix test.** Seller cases (attacker = A `seller`):

```
DELETE seller/coupons/{campaign}   GET seller/coupons/{campaign}   GET seller/coupons/{campaign}/edit
POST   seller/coupons/{campaign}/toggle   PUT seller/coupons/{campaign}
DELETE seller/daily-deals/{deal}   GET seller/daily-deals/{deal}/edit   POST seller/daily-deals/{deal}/toggle   PUT seller/daily-deals/{deal}
DELETE seller/products/{product}   GET seller/products/{product}/edit   PUT seller/products/{product}
DELETE seller/products/{product}/images/{image}   POST seller/products/{product}/images/{image}/primary
DELETE seller/stories/{story}
DELETE seller/wallet/payment-methods/{paymentMethod}   POST seller/wallet/payment-methods/{paymentMethod}/default
GET    seller/conversations/{conversation}   POST seller/conversations/{conversation}/messages ['body'=>'x']
GET    seller/orders/{order}   POST seller/orders/{order}/status ['status'=>'shipped']
GET    seller/showcases/{showcase}   POST seller/showcases/{showcase}/cancel
GET    seller/support/{supportConversation}   POST seller/support/{supportConversation}/message ['body'=>'x']
POST   seller/questions/{question}/answer ['answer'=>'x']
GET    withdrawals/{withdrawal}/receipt
```

Shared cases (attacker = A `customer`): `GET invoices/{order}`, `GET notifications/{notification}/read`, `DELETE profile/addresses/{address}`, `PUT profile/addresses/{address}`, `POST profile/addresses/{address}/default`. Also `GET invoices/{order}` with attacker = A `seller` (B's order has no A items) → denied.

- [ ] **Steps 2–5:** run, fix holes (one commit each), full suite green, commit `test(authz): seller and shared web horizontal access matrix`.

---

### Task 6: Route guard — no unprotected route can be added

**Files:**
- Create: `tests/Feature/Authorization/RouteGuardTest.php`

**Interfaces:**
- Consumes: `CASES` from the four matrix test classes.

- [ ] **Step 1: Write `test_every_owner_scoped_route_is_in_a_matrix`.** Walk `Route::getRoutes()`; select routes whose URI contains `{`, whose middleware includes an auth middleware, and whose `role:` middleware is absent or is `customer`/`seller`. Each `METHOD uri` must appear in the union of all `CASES`, or in this exclusion list (exact keys, each with its reason as the array value):

```
POST api/v1/favorites/{product}/toggle        => 'acts on a public product, owner-agnostic'
POST customer/favorites/{product}             => same
POST api/v1/products/{product}/questions      => 'any customer may ask on a public product'
POST customer/products/{product}/questions    => same
POST api/v1/products/{product}/reviews        => 'purchase rule covered by ReviewTest'
POST api/v1/questions/{question}/answers      => 'community answers on public questions'
POST customer/questions/{question}/answers    => same
POST api/v1/questions/{question}/helpful      => 'vote on a public question'
POST api/v1/stores/{sellerProfile}/follow     => 'public store'
POST store/{sellerProfile}/follow             => same
POST api/v1/stores/{sellerProfile}/conversations => 'starts the caller's own conversation'
POST store/{sellerProfile}/message            => same
POST api/v1/coupons/{campaign}/collect        => 'audience rules covered by CollectCouponTest'
POST coupons/{campaign}/collect               => same
GET  sandbox-bank/{kind}/{reference}          => 'sandbox gateway pages, reference is a one-time token'
POST sandbox-bank/{kind}/{reference}          => same
```

Failure message lists every uncovered route.

- [ ] **Step 2: Write `test_role_gated_routes_reject_other_roles`.** For every route with `role:X` middleware (X in admin, seller, customer, employee), call it with one user of each other role (route params filled with `1`; API with sanctum, web with session) and assert the status is 403 for `api/*`, and 403 or a redirect (3xx) for web. Never 2xx.

- [ ] **Step 3: Run** `php artisan test tests/Feature/Authorization/RouteGuardTest.php` — expect PASS (if a vertical case fails, fix the route's middleware in `routes/*.php`, one commit).

- [ ] **Step 4: Full suite green. Commit** `test(authz): route guard for owner-scoped and role-gated routes`

---

### Task 7: Policies for customer-owned resources

**Files:**
- Create: `app/Policies/OrderPolicy.php`, `OrderItemPolicy.php`, `UserAddressPolicy.php`, `CustomerCardPolicy.php`, `CartItemPolicy.php`, `AppNotificationPolicy.php`, `ConversationPolicy.php`, `ProductQuestionPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php` (register with `Gate::policy()`); the customer API and web controllers covered by `CustomerApiAccessTest` / `CustomerWebAccessTest` / the shared cases of `SellerWebAccessTest`.
- Test: `tests/Unit/Policies/CustomerPoliciesTest.php`

**Interfaces (each method returns `Illuminate\Auth\Access\Response`):**
- `OrderPolicy::view(User $user, Order $order)` — order owner. `cancel(...)` — owner (status rule stays in the controller/`CustomerOrderCanceller`). `viewAsSeller(...)` — user's seller profile has an item in the order. `invoice(...)` — owner, or seller with an item in the order, or admin (match the current `InvoiceController` rule exactly; read it first).
- `OrderItemPolicy::review(User, OrderItem)` — the item's order belongs to the user.
- `UserAddressPolicy::manage`, `CustomerCardPolicy::manage`, `CartItemPolicy::manage`, `AppNotificationPolicy::markRead` — owner by `user_id`.
- `ConversationPolicy::participate(User, Conversation)` — the conversation's customer, or the owner of its store.
- `ProductQuestionPolicy::delete` — the asker. `answerAsSeller` — owner of the question's product's store.
- Denials use `Response::deny()` (403) or `Response::denyAsNotFound()` (404) — whichever the endpoint returns today (the matrix test pins it).

- [ ] **Step 1: Write `CustomerPoliciesTest`** — for each ability: owner allowed, other tenant denied, using `BuildsTenants` and `Gate::forUser($user)->inspect($ability, $model)->allowed()`.
- [ ] **Step 2: Run — expect FAIL (policies missing).**
- [ ] **Step 3: Create the policies and register them** in `AppServiceProvider::boot()` with explicit `Gate::policy(Model::class, Policy::class)` lines.
- [ ] **Step 4: Run the unit test — PASS.**
- [ ] **Step 5: Replace each manual ownership check** in the covered controllers with `Gate::authorize('<ability>', $model)`, one controller per commit; after each, run that controller's matrix test file. Leave non-ownership rules (status checks, 409s) where they are.
- [ ] **Step 6: Full suite green. Final commit** `refactor(authz): customer ownership rules move to policies`

---

### Task 8: Policies for seller-owned resources

**Files:**
- Create: `app/Policies/ProductPolicy.php`, `DailyDealPolicy.php`, `StoryPolicy.php`, `PaymentMethodPolicy.php`, `ShowcasePolicy.php`, `StaffConversationPolicy.php`, `CouponCampaignPolicy.php`, `WithdrawalRequestPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`; the seller API and web controllers covered by `SellerApiAccessTest` / `SellerWebAccessTest`; `WithdrawalController` receipt.
- Test: `tests/Unit/Policies/SellerPoliciesTest.php`

**Interfaces:**
- `manage(User $user, <Model> $model): Response` on each policy — the model belongs to the user's seller profile (`$user->sellerProfile?->id`). `CouponCampaignPolicy::manage` — campaign `funder` is `seller` and its `seller_profile_id` is the user's store. `WithdrawalRequestPolicy::viewReceipt` — owner seller or admin (match current controller rule).
- Image routes: authorize `manage` on the parent product **and** check the image belongs to that product (keep or add the check; Task 3 pins it).
- Same deny/denyAsNotFound rule as Task 7.

- [ ] **Steps 1–6:** same cycle as Task 7 (`SellerPoliciesTest` owner/other cases → fail → policies + registration → pass → migrate controllers one per commit with the matrix file run after each → full suite → commit `refactor(authz): seller ownership rules move to policies`).
