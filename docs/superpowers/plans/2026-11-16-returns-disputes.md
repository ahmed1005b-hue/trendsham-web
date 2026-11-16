# Returns and Disputes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** After delivery, a customer can return some or all units of an order's items; the seller decides first, the admin settles disputes, the seller confirms receipt (which reverses stock, seller earnings and loyalty points for exactly those units), and the admin records the manual refund.

**Architecture:** A self-contained `app/Returns` module (models, one `ReturnService` that is the only entry point for status changes, a `RefundCalculator`, a `ReturnSettlement` that applies the money/stock effects once) on new tables, wired into the existing `OrderItem` settlement, `Product::adjustStock`, `LoyaltyLedger`, `CouponRedeemer`, `AppNotification` and Policies. Web (customer/seller/admin), API (customer/seller) and Flutter (customer/seller) are thin layers over the service.

**Tech Stack:** Laravel 12 / PHP 8.2, MySQL, PHPUnit, Blade, Sanctum; Flutter/Dart (`AppText`/`context.tr`, `assets/l10n/{en,tr}.json`).

**Spec:** `docs/superpowers/specs/2026-11-16-returns-disputes-design.md` (Arabic). Read sections 4, 5 and 10 before any task.

## Global Constraints

- Statuses exactly: `requested`, `approved`, `rejected`, `escalated`, `received`, `refunded`, `closed`. Final: `refunded`, `closed`.
- Reasons exactly: `damaged`, `not_as_described`, `wrong_size_or_color`, `changed_mind`, `other`. Seller-fault reasons: `damaged`, `not_as_described`.
- Defaults (admin-editable): return window 7 days from `order_items.commission_settled_at`; seller response 3 days then auto-escalate; dispute window 7 days after a seller rejection; ship-back window 14 days after approval then auto-close.
- Up to 5 photos per request/event, stored with `App\Services\ImageUploadService::store()` (its existing limits).
- One return request = items of one seller (`vendor_id`) of one order.
- Money in integer minor units (×100), like the rest of the project. Refund per line is computed once at creation and stored; never recomputed.
- Refund rule (spec §5, §10.1): customer gets what they actually paid for those units (line subtotal share − line discount share − share of order-level `discount_amount` and `points_discount` by line value). Shipping refunded automatically only for a single-vendor order whose every unit has been returned with a seller-fault reason (carried by the request that completes it); otherwise admin may add up to the order `shipping_fee`, once per order, with a mandatory note.
- Seller reversal per unit = `settlementBase()` share and `commission_amount` share by quantity; platform absorbs its own coupon share (same as cancel today). Wallet and loyalty may go negative (existing `allowDebt` / wallet behaviour).
- Effects run exactly once per return line, inside a transaction with row locks; ledger entries are never edited (reversal = new entry).
- Admin cancel of a delivered order is blocked while it has a return in `requested`/`approved`/`escalated`; when allowed it must not re-reverse units already reversed by returns (money, stock, points).
- DB: new tables only, plus nullable settings columns added to the existing `platform_settings` row table (project convention for admin settings, see `app/Loyalty/LoyaltySettings.php`). Every migration has a working `down()`. Take a DB backup before the first migration runs on the dev DB.
- No change to existing API responses the Flutter app reads; new endpoints under `/api/v1/returns` (customer) and `/api/v1/seller/returns` (seller). Admin is web-only.
- User-facing strings in Arabic in code; add EN/TR translations (`lang/en.json`, `lang/tr.json`; app `assets/l10n/en.json`, `tr.json`) for every new string.
- Authorization via a `ReturnRequestPolicy` registered with `Gate::policy` in `AppServiceProvider`; every new owner-scoped route gets a case in the Phase-1 matrices (`tests/Feature/Authorization/*AccessTest.php` `*CASES`) so `RouteGuardTest` stays green.
- Work on `master`; other sessions commit concurrently and share the test DB — before every test run check no other `artisan test`/`phpunit` process runs; stage files by explicit path; never stage `storage/app/*.png`; run `vendor/bin/pint <files> --format agent` on changed PHP files; commit messages typed literally, ending `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Full suite stays green (baseline 640); `flutter analyze` clean and `flutter test` green for app tasks.

## Review Focus

1. Two concurrent "confirm receipt" calls (seller double-tap, seller + admin) → effects applied once; pinned in Task 5.
2. Admin cancels a delivered order after a partial return was received → remaining units only are reversed (stock, wallet, ledger, points); pinned in Task 5.
3. Customer requests more units than remain returnable (across open requests) or after the window, or on a cancelled/undelivered order → rejected with a clear Arabic message; pinned in Task 3.
4. Order paid partly with platform coupon + points, multi-line → sum of refunds over a full return equals what was paid for products (never more); pinned in Task 2.
5. A return whose seller never answers / customer never ships back → auto-escalated / auto-closed by the daily job and quantity released; pinned in Task 4.

---

### Task 1: Schema, models and settings

**Files:**
- Create: migrations `database/migrations/2026_11_16_000001_create_return_tables.php`, `2026_11_16_000002_add_return_settings_to_platform_settings.php`
- Create: `app/Returns/Models/ReturnRequest.php`, `ReturnItem.php`, `ReturnEvent.php`, `ReturnRefund.php`, `app/Returns/ReturnSettings.php`
- Modify: `app/Models/Order.php`, `app/Models/OrderItem.php` (relations only)
- Test: `tests/Feature/Returns/ReturnSchemaTest.php`

**Interfaces:**
- Produces tables:
  - `return_requests`: id, `number` (unique, e.g. `RT-…`), `order_id`, `user_id` (customer), `seller_profile_id`, `status` (string, indexed), `reason`, `description` (text), `refund_amount` (unsigned bigint, minor units, sum of lines + shipping), `shipping_refund` (unsigned bigint default 0), `respond_by`, `dispute_by`, `ship_back_by` (nullable timestamps), `decided_by` (nullable user id), `decision_note`, `received_at`, `closed_at`, timestamps. Index (`seller_profile_id`,`status`), (`user_id`,`status`).
  - `return_items`: id, `return_request_id`, `order_item_id`, `quantity`, `refund_amount` (minor units, stored), `seller_reversal` (minor units: settlement-base share), `commission_reversal` (minor units), `settled_at` (nullable — set when effects applied; the once-guard).
  - `return_events`: id, `return_request_id`, `actor_id` (nullable for system), `actor_role` (`customer|seller|admin|system`), `type` (`requested|approved|rejected|escalated|disputed|admin_approved|admin_rejected|received|refunded|closed|withdrawn|note`), `note` (text nullable), `photos` (json nullable, list of storage paths), timestamps.
  - `return_refunds`: id, `return_request_id` (unique), `amount`, `method` (`bank_transfer|cash|other`), `reference` (nullable), `note` (nullable), `recorded_by`, timestamps.
  - `platform_settings` nullable ints: `returns_window_days`, `returns_seller_response_days`, `returns_dispute_days`, `returns_ship_back_days`.
- Produces: `ReturnRequest::STATUSES`, `ReturnRequest::FINAL = ['refunded','closed']`, `ReturnRequest::OPEN = ['requested','approved','escalated']`, `ReturnRequest::REASONS`, `ReturnRequest::SELLER_FAULT = ['damaged','not_as_described']`, `ReturnRequest::STATUS_LABELS` (Arabic), relations `order()`, `customer()`, `seller()`, `items()`, `events()`, `refund()`; `ReturnItem::orderItem()`; `Order::returnRequests()`; `OrderItem::returnItems()`; `OrderItem::heldReturnQuantity(): int` (sum of quantities of its return items whose request status is not `closed` — a `rejected` request keeps holding until the daily job closes it after the dispute window), `OrderItem::reversedQuantity(): int` (sum of quantities of return items with `settled_at`).
- Produces: `ReturnSettings` with `windowDays(): int` (7), `sellerResponseDays(): int` (3), `disputeDays(): int` (7), `shipBackDays(): int` (14), same pattern as `LoyaltySettings` (`PlatformSetting::current()`, defaults const).

- [ ] Step 1: Test `test_tables_and_relations_exist` (create a request with 2 items and an event via the models, assert relations and casts: `photos` array, timestamps as Carbon) and `test_settings_defaults_and_overrides` (defaults 7/3/7/14; setting `returns_window_days=10` on the platform settings row → 10).
- [ ] Step 2: Run, see it fail. Step 3: migrations + models + settings. Step 4: run it and `php artisan migrate:rollback --step=2 --pretend` shows clean downs. Step 5: Take the dev DB backup (`C:\xampp\backups\trendsham\`, mysqldump of `trendsham`), then `php artisan migrate` on the dev DB. Commit `feat(returns): tables, models and settings`.

### Task 2: RefundCalculator

**Files:** Create `app/Returns/RefundCalculator.php`; Test `tests/Unit/Returns/RefundCalculatorTest.php` (Tests\TestCase + RefreshDatabase).

**Interfaces:**
- `RefundCalculator::forLine(OrderItem $item, int $quantity): array{refund:int, seller_reversal:int, commission_reversal:int}` — all minor units, proportional to `$quantity / $item->quantity`; the last units of a line get the remainder so that returning a line in several requests sums exactly to the full-line value (use already-returned quantity on that line to compute the remainder).
- `RefundCalculator::shippingFor(ReturnRequest $request): int` — the order `shipping_fee` when the order is single-vendor, the request reason is seller-fault, and after this request every unit of the order is covered by non-closed/non-rejected returns; else 0.
- Order-level discount allocation: share of (`order.discount_amount` that is not already allocated to lines as `order_items.discount_amount`) + `order.points_discount`, by line value (`subtotal − discount_amount`) over the sum of line values; remainder to the largest line (deterministic).

- [ ] Tests (exact): `test_single_line_full_return_refunds_line_paid_amount`; `test_partial_quantity_is_proportional_and_remainder_goes_to_last_units` (line qty 3, subtotal 1000 → returns of 1+1+1 sum to 1000); `test_platform_coupon_and_points_are_shared_by_line_value` and `test_full_return_of_every_line_never_exceeds_products_paid` (order with 2 lines, platform coupon and points: Σrefund = subtotal − discount − points_discount); `test_seller_reversal_uses_settlement_base_and_commission_by_quantity`; `test_shipping_only_for_single_vendor_full_seller_fault_return` (3 cases: multi-vendor → 0, changed_mind → 0, damaged full → shipping_fee).
- [ ] Fail → implement → pass → commit `feat(returns): refund calculator`.

### Task 3: Creating and withdrawing a return

**Files:** Create `app/Returns/ReturnService.php` (create/withdraw now; later tasks add methods), `app/Returns/ReturnNotifier.php`; Test `tests/Feature/Returns/CreateReturnTest.php`.

**Interfaces:**
- `ReturnService::create(User $customer, Order $order, array $lines /* [order_item_id => qty] */, string $reason, ?string $description, array $photos /* UploadedFile[] ≤5 */): ReturnRequest` — throws `\DomainException` with Arabic messages for each rule; status `requested`, `respond_by = now + sellerResponseDays`, lines priced with `RefundCalculator::forLine`, `refund_amount` = Σlines + `shippingFor`, event `requested` with photos, notifies the seller.
- `ReturnService::withdraw(User $customer, ReturnRequest $r): ReturnRequest` — only from `requested`/`approved`; → `closed`, event `withdrawn`.
- `ReturnNotifier` wraps `AppNotification::send` for each transition (Arabic titles/bodies, links to the right web route; the push/translation layer already exists). Later tasks call it.
- Returnable quantity of a line = `quantity − OrderItem::heldReturnQuantity()` (Task 1).

- [ ] Tests (exact names and messages asserted): `test_customer_returns_part_of_a_delivered_line`; `test_rejects_after_window` ("انتهت مدة الإرجاع لهذا الطلب."); `test_rejects_undelivered_or_cancelled_order` ("لا يمكن إرجاع طلب لم يُسلَّم."); `test_rejects_more_than_returnable_quantity_across_open_requests` ("الكمية المطلوبة أكبر من الكمية القابلة للإرجاع."); `test_rejects_lines_from_two_sellers` ("طلب الإرجاع يجب أن يخص متجراً واحداً."); `test_rejects_more_than_five_photos`; `test_rejects_unknown_reason`; `test_customer_cannot_return_another_customers_order` (403/404); `test_withdraw_closes_and_releases_quantity`; `test_seller_is_notified`.
- [ ] Fail → implement → pass → commit `feat(returns): customer creates and withdraws returns`.

### Task 4: Decisions, disputes, admin and the daily job

**Files:** Modify `app/Returns/ReturnService.php`; Create `app/Console/Commands/ReturnsDaily.php` (`returns:daily`), schedule in `routes/console.php` (`dailyAt('02:45')`); Test `tests/Feature/Returns/ReturnDecisionsTest.php`.

**Interfaces (all lock the request row, check the current status, write an event, notify via `ReturnNotifier`, throw `\DomainException` on an illegal transition):**
- `sellerApprove(SellerProfile $s, ReturnRequest $r, ?string $note)`: `requested` → `approved`, `ship_back_by = now + shipBackDays`.
- `sellerReject(SellerProfile $s, ReturnRequest $r, string $note)`: note required; `requested` → `rejected`, `dispute_by = now + disputeDays`.
- `dispute(User $customer, ReturnRequest $r, string $note, array $photos)`: `rejected` before `dispute_by` → `escalated`.
- `adminApprove(User $admin, ReturnRequest $r, ?string $note)`: `escalated` → `approved` (sets `ship_back_by`); `adminReject(User $admin, ReturnRequest $r, string $note)`: `escalated` → `closed`.
- `confirmReceived(User $actor, ReturnRequest $r)`: seller of the request or admin; `approved` → `received` (Task 5 adds the effects; here only the transition + event).
- `runDaily(): array{escalated:int, closed:int}`: `requested` past `respond_by` → `escalated` (event actor `system`); `rejected` past `dispute_by` → `closed`; `approved` past `ship_back_by` → `closed`. Notifies parties; idempotent.

- [ ] Tests: one per transition (allowed path + each wrong-status attempt raises), `test_seller_of_another_store_cannot_decide` (policy), `test_daily_job_escalates_closes_and_is_idempotent` (travel time; run twice → second returns zeros), `test_dispute_after_window_is_refused`, `test_admin_can_confirm_receipt_for_unresponsive_seller`.
- [ ] Fail → implement → pass → commit `feat(returns): seller decisions, disputes, admin and daily job`.

### Task 5: Settlement on receipt and the cancel interaction

**Files:** Create `app/Returns/ReturnSettlement.php`; Modify `app/Models/OrderItem.php` (partial reversal), `app/Models/Order.php` (`settleForStatusChange` cancel branch, `restockItems`), `app/Loyalty/LoyaltyLedger.php` (partial clawback + full-reverse subtracting earlier partial clawbacks), the admin cancel paths that call `Order::changeStatus('cancelled')` (block when open returns exist — put the guard inside `changeStatus` so every path is covered); Test `tests/Feature/Returns/ReturnSettlementTest.php`.

**Interfaces:**
- `ReturnSettlement::apply(ReturnRequest $r): void` — called by `confirmReceived` inside its transaction; for each return item with `settled_at` null: restock product (`adjustStock(+qty,'return', "إرجاع #{number}")`) and variant, `OrderItem::reverseSettlementQuantity(ReturnItem $line)`, loyalty clawback, then `settled_at = now`. When after this every unit of the order has been reversed by returns → `CouponRedeemer::reverse($order)` and seller shipping charge refund, exactly as the cancel branch does.
- `OrderItem::reverseSettlementQuantity(ReturnItem $line): void` — wallet debit of `$line->seller_reversal − share already in vendor_earning terms` (use `vendor_earning × qty/quantity` with remainder on the last units, same rule as Task 2), ledger `refund` + `commissionReversal` with source `('return_item', $line->id)`; once-guard = a wallet transaction with `return_item_id`/note marker or `settled_at` (choose one; must survive a retry).
- `OrderItem::reverseSettlement()` (full, used by cancel) must reverse only what remains: earnings, ledger amounts and stock minus what return lines already reversed.
- `LoyaltyLedger::reverseEarnForReturn(ReturnItem $line): ?Tx` — debit `round(earn.points × line.refund / paidBase)` with `onceKey 'reverse_earn_return:'.$line->id`, `allowDebt: true`; `reverseEarnForOrder` debits `earn.points − Σ earlier return clawbacks`.
- `Order::changeStatus` throws `\DomainException('لا يمكن إلغاء الطلب وعليه طلب إرجاع مفتوح.')` when `$to === 'cancelled'` and the order has a return in `ReturnRequest::OPEN`.

- [ ] Tests: `test_receipt_restocks_reverses_wallet_ledger_and_points_for_returned_units_only`; `test_concurrent_or_repeated_confirmation_applies_once` (call `apply` twice + call `confirmReceived` twice → one restock movement, one wallet debit, one ledger pair, one points debit); `test_cancel_after_partial_return_reverses_only_the_rest` (stock, wallet, ledger sums, points sum equal full-order values exactly once); `test_cancel_blocked_while_return_open`; `test_full_return_restores_coupon_like_cancel`; `test_wallet_and_points_may_go_negative`; plus the existing suite (cancel/settlement tests) still green.
- [ ] Fail → implement → pass → full suite → commit `feat(returns): settle received returns and keep cancel consistent`.

### Task 6: Manual refund recording

**Files:** Modify `app/Returns/ReturnService.php`; Test `tests/Feature/Returns/RecordRefundTest.php`.

**Interfaces:**
- `ReturnService::recordRefund(User $admin, ReturnRequest $r, int $amount, string $method, ?string $reference, ?string $note, int $extraShipping = 0): ReturnRefund` — only from `received`; `$amount ≤ refund_amount + $extraShipping`; `$extraShipping ≤ order.shipping_fee`, allowed once per order across its returns, requires `$note`; → `refunded`, event `refunded`, notify customer. No ledger entry (seller-side reversal was posted at receipt; this records the platform's cash-out to the customer) — note this in a docblock.

- [ ] Tests: happy path; over-amount refused; second refund refused; extra shipping cap and once-per-order; only admin (policy).
- [ ] Commit `feat(returns): admin records manual refunds`.

### Task 7: Policy and customer web pages

**Files:** Create `app/Policies/ReturnRequestPolicy.php` (`view`, `withdraw`, `dispute` → customer owner; `decide`, `confirmReceived` → seller owner via `sellerProfile?->id === seller_profile_id`, plain 403 `Response::deny('')`; admin bypass only where Task 4/6 allow: `confirmReceived`, `adminDecide`, `recordRefund`), register in `AppServiceProvider`; `app/Http/Controllers/Customer/ReturnController.php`; views `resources/views/customer/returns/{index,create,show}.blade.php`; routes in the `customer.` group (`customer/returns`, `customer/orders/{order}/returns/create`, `POST customer/orders/{order}/returns`, `customer/returns/{returnRequest}`, `POST …/withdraw`, `POST …/dispute`); a "طلب إرجاع" button on `customer/orders/show` when any line is returnable; translations in `lang/en.json`, `lang/tr.json`; Tests `tests/Feature/Returns/CustomerReturnWebTest.php` + new rows in `CustomerWebAccessTest::CASES` (B's return show/withdraw/dispute, create on B's order) with positive controls.

- [ ] Tests: page flows (create with photos via `UploadedFile::fake()->image`, list, show with events, withdraw, dispute), button visibility rules, matrix rows denied 403/404, `RouteGuardTest` green.
- [ ] Commit `feat(returns): customer web pages`.

### Task 8: Seller and admin web pages

**Files:** `app/Http/Controllers/Seller/ReturnController.php` + views `resources/views/seller/returns/{index,show}.blade.php` (filter by status, badge count of `requested` in the seller nav); `app/Http/Controllers/Admin/ReturnController.php` + views `resources/views/admin/returns/{index,show}.blade.php` (tabs: escalated, awaiting refund, all; approve/reject with note; confirm receipt; record refund form with method/reference/note/extra shipping); routes in `seller.` and `admin.` groups; nav links; translations; Tests `tests/Feature/Returns/SellerAdminReturnWebTest.php` + rows in `SellerWebAccessTest::CASES` (B's return show/approve/reject/confirm).

- [ ] Commit `feat(returns): seller and admin web pages`.

### Task 9: API for the app

**Files:** `app/Http/Controllers/Api/ReturnController.php` (customer: `GET /api/v1/returns`, `GET /api/v1/returns/{returnRequest}`, `GET /api/v1/orders/{order}/returnable` → lines with returnable qty and window end, `POST /api/v1/orders/{order}/returns` multipart, `POST …/{returnRequest}/withdraw`, `POST …/dispute`), `app/Http/Controllers/Api/Seller/ReturnController.php` (`GET /api/v1/seller/returns`, `GET …/{returnRequest}`, `POST …/approve`, `…/reject`, `…/received`), `app/Http/Resources/ReturnRequestResource.php` (status + Arabic label, reason + label, lines with product name/variant/qty/refund, totals, deadlines, events with photo URLs, `actions` booleans the app uses to show buttons); `OrderResource` gains nothing (no change to existing responses) — the app calls `returnable`; Tests `tests/Feature/Returns/ReturnApiTest.php` + rows in `CustomerApiAccessTest::CASES` and `SellerApiAccessTest::CASES`.

- [ ] Commit `feat(returns): customer and seller API`.

### Task 10: Flutter — customer returns

**Files (app repo `C:\Users\istanbul\Desktop\trendsham_flutter`):** `lib/models/return_request.dart`, `lib/services/return_service.dart` (uses `ApiClient` incl. multipart), `lib/screens/returns/{returns_screen,return_detail_screen,create_return_screen}.dart`, entry button in `lib/screens/orders/order_detail_screen.dart` (shown when `returnable` has lines), link from profile/orders; strings via `AppText` with `assets/l10n/en.json`/`tr.json`; tests `test/return_request_model_test.dart` (JSON parsing incl. actions/events) and a widget test for the create form validation.

- [ ] `flutter analyze` clean, `flutter test` green. Commit in the app repo `feat(returns): customer return screens` (never stage `.artifacts/`).

### Task 11: Flutter — seller returns

**Files:** `lib/services/seller_service.dart` (or a new `seller_return_service.dart`), `lib/screens/seller/{seller_returns_screen,seller_return_detail_screen}.dart`, entry in `seller_main_screen.dart` with the `requested` count; approve/reject (note required) / confirm received; strings + l10n; widget/model tests.

- [ ] `flutter analyze` clean, `flutter test` green. Commit `feat(returns): seller return screens`.
