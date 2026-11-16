# Smart Assistant — Phase 1 Implementation Plan

> Executed inline (superpowers:executing-plans). Design approved in chat 2026-11-11 (option 4: free, pluggable brain).

**Goal:** Replace the keyword `ClaudeAssistantService` with a pluggable assistant ("brain" interface, free rule-based driver) that understands Syrian Arabic, searches products smartly with cards, compares, tracks/cancels orders, lists coupons, answers admin-written FAQs, offers suggestion chips, and hands off to human support — on web and Flutter.

**Architecture:** `app/Assistant/` — `AssistantBrain` interface resolved from `config('assistant.driver')`; `RuleBasedBrain` = `Normalizer` + `IntentDetector` + `QueryParser` + skills. `ChatAssistant` service persists messages; reply metadata (cards, chips, pending action, intent, agent flag) lives in existing `chat_messages.tool_calls` JSON. Human agent replies are `role=assistant` with `meta.agent=true` (no enum change).

**Tech:** Laravel 12, Blade widget JS, Flutter.

## Global Constraints
- Money stored ×100; displayed `number_format(x/100) ل.س`.
- Customers see only their own orders/coupons; guests get search + FAQ only (orders/coupons → login prompt).
- Never invent store policies: policy questions without a matching FAQ → offer handoff.
- Cancel only `pending` orders owned by the user, after explicit confirmation, reusing the existing cancel logic.
- `/chat/send` throttled 20/min.
- All user-facing text Arabic.

## Review Focus
1. Guest asking about orders/coupons gets login prompt, never someone else's data.
2. Confirmation "نعم" with no pending action must not cancel anything; pending action for another user's order impossible.
3. Escalated conversation: bot stops answering; agent replies visible to the right user/guest only.
4. Price parsing: "تحت 200 ألف", "200الف", Arabic-Indic digits, "بين 50 و 100 الف".
5. Empty/unknown searches don't return the whole catalogue.

## Tasks
1. **Nlp** — `Normalizer`, `IntentDetector`, `QueryParser` + unit tests.
2. **Brain + skills** — `AssistantReply`, `AssistantContext`, `AssistantBrain`, `RuleBasedBrain`, skills (SmallTalk, ProductSearch/Deals, Compare, Orders + cancel confirm, Coupons, Faq, Handoff), `config/assistant.php`, `ChatAssistant` service, `OrderCanceller` extracted from both cancel controllers. Feature tests.
3. **Data + admin** — migration: `assistant_faqs` table, `chat_conversations.escalated_at`; `AssistantFaq` model; admin web FAQ CRUD + handoff inbox (list/show/reply/close); dashboard link. Tests.
4. **API + web widget** — controller returns `meta` + `status`; throttle; widget renders cards/chips/agent badge. Tests.
5. **Flutter** — `ChatMessage` cards/chips/isAgent; chat screen renders them; tests.
6. Final review + security review.
