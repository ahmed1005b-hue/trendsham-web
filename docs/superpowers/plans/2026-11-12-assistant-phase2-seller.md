# Smart Assistant — Phase 2 (Seller Assistant) Plan

> Executed inline. Design approved in chat 2026-11-11 (part B of option 4).

**Goal:** When a seller chats with the assistant, a seller "brain" answers about *their own store*: sales summary by period, orders waiting for them, low stock, wallet balance, practical tips; plus a free template-based product-description writer usable from chat and from the product form (web + Flutter).

**Architecture:**
- `BrainResolver::for(?User)` picks `config('assistant.seller_driver')` for users with role `seller` and a store, else the customer brain (`AssistantBrain` binding unchanged). `ChatAssistant` asks the resolver.
- `Brains/SellerRuleBrain` + `Nlp/SellerIntentDetector` + `Nlp/Period`.
- `Skills/Seller/StoreInsights` (sales, pending orders, low stock, wallet, tips).
- `Seller/DescriptionWriter` (facts only: name, category, colors, sizes, store; never invents specs).
- Endpoints: web `POST seller/assistant/describe` (seller group), API `POST /api/v1/seller/assistant/describe`.
- Cards: `link` with route `seller_orders | seller_products | seller_wallet | seller_support`.

## Global Constraints
- Everything is scoped to the seller's own `vendor_id`. A seller never sees another store's data.
- Money is in minor units ×100. Sales = Σ `OrderItem::settlementBase()` of non-cancelled orders.
- Description writer states only facts given; output Arabic.
- Customer behaviour is unchanged for non-sellers.

## Review Focus
1. Seller A's numbers must exclude seller B's items in a shared multi-vendor order.
2. Cancelled orders are excluded from sales.
3. Period parsing: اليوم / امبارح / هالاسبوع / هالشهر / default = this month.
4. Description endpoint only for sellers; validates input lengths.
5. A seller whose store is suspended still gets their own data, and nothing else.
