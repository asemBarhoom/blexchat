# Backend tasks: Figma and API alignment

| | |
|---|---|
| **Backend** | `blexchat` `master` (branch `feat/alignment-tracker`) |
| **Frontend** | `blexchat-website` `origin/main` @ `b5890be` |
| **Figma** | [blexchat (Copy)](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-): pages **App** (`5:7`) and **Website** (`5:2`) |
| **Tracker** | Admin panel → **المطابقة** → **مطابقة فيغما والـ API** (`/admin/alignment-items`) |

**Status:** 5 done, 13 open. Design has 6 open items to decide (DS-01–DS-06). Frontend items (FE-01–FE-26) are in `FRONTEND-TASKS.md` and in the same tracker.

**Website page:** every text on the landing page, pricing (annual and monthly), the navbar, the footer and the 8 solution pages comes from the API, in English and Arabic. The 404 copy is the only exception (BE-16).

---

## ✅ Done (commit `9407971`)

| Task | What | Where |
|---|---|---|
| BE-01 | Go-live refused when the channel is disconnected: `422 {code: channel_disconnected, channel, message}` | `app/Models/User.php:104`, `AutomationController.php:221`, `:292` |
| BE-02 | `plans.live_automations_limit` (Free = 3), and `422 {code: live_limit, limit}` | `app/Models/User.php:104`, Filament `PlanResource` |
| BE-03 | `remind_at` on conversations and `?folder=reminders` | `ConversationController.php:64`, `:80` |
| BE-04 | `post.comments` from Instagram's `comments_count` | `PostResource.php`, `InstagramClient.php` |
| BE-05 | `meta.total` on `GET /conversations` | `ConversationController.php` (`index`) |

---

## 🟠 P1: open

### BE-06 · Nothing happens when a reminder is due
- **Figma:** [Inbox · Reminders](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087)
- **Now:** `remind_at` is stored and listed (`ConversationController.php:64`). Nothing fires when the time comes.
- **Do:**
  - Get a product decision: an email, a push notification, or only a mark in the inbox.
  - Then add a scheduled command that picks the conversations whose `remind_at` has passed.

### BE-07 · WhatsApp is not connected for real
- **Figma:** [App page](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=5-7): W05–W07 channels, and أم فيصل's thread in the Inbox.
- **Now:** `POST /conversations/{id}/messages` sends only when `platform === 'instagram'` (`ConversationController.php:201`). A WhatsApp reply is stored and never delivered.
- **Do:** WhatsApp Cloud API: the connection, a webhook for incoming messages, and sending.

### BE-08 · TikTok is not connected for real
- Same as BE-07, for TikTok (Lulu's thread in the Inbox). `ConversationController.php:201`.

### BE-10 · Inbox teammates and team roles are sold but not built
- **Figma:** [Pricing · compare, Team rows](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-821)
- **Now:** `GET /pricing` → `compare.team` sells teammates 1 / 1 / 3 / 10 / 25 and "Team roles and assignment" (`PricingPageSeeder.php:176`). Plans list "Inbox for 3 teammates". A user has no team.
- **Do:** invites, a seat count per plan, and assigning a conversation to a teammate.

---

## 🟢 P2: open

### BE-09 · Messenger is not connected for real
- Same as BE-07, for Messenger. `ConversationController.php:201`.

### BE-11 · Many accounts, one seat (Advanced, agencies)
- **Figma:** [Solution · agencies](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-2269), [Pricing](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-821)
- **Now:** the agencies FAQ answers "Yes, on the Advanced plan" (`PricingPageSeeder.php:178`). The API has no workspace or account switch.

### BE-12 · No AI engine behind Blexchat AI
- **Figma:** [Blexchat AI](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6308). "Try it" is marked as a demo.
- **Now:** `PATCH /automations/{id}/ai` only switches `ai_enabled`. Pro and up sell "+ Blexchat AI".

### BE-13 · Handover reason is generic
- **Figma:** [Inbox](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087): "Asked about a size and a delivery date — outside the automation."
- **Now:** `message.reason` is a sentence per code (`app/Models/Message.php:16`, `Conversation::HANDOVER_REASONS`). A sentence per conversation needs BE-12.

### BE-14 · Automations search and live count
- **Figma:** [Automations](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4564): "Search automations" and "3 live of 5".
- **Do:** `GET /automations?search=` and `meta.live` (`AutomationController.php:68`). The frontend loads `per_page=100` and can do both itself for now.

### BE-15 · Contacts import (CSV)
- **Figma:** [Contacts · Import](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4242). The design itself says "Import is part of the full build", so it can wait.

### BE-16 · Marketing 404 copy is not in the API
- **Figma:** [Marketing 404](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-1308)
- **Now:** "Is this page still here?", "This page doesn’t exist." and "Back to the homepage →" live only in the frontend (`src/i18n/locales/en.json:2390`).
- **Do:** add a `not_found` block to `GET /home` (or the auth screens), editable in the admin.

### BE-17 · "Help center" has nowhere to go
- **Figma:** [Pricing · compare, Support](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-821)
- **Now:** Free gets "Help center" (`PricingPageSeeder.php:186`), but there is no help center or link.

### BE-18 · Coupon admin says usage is not counted
- **Where:** `app/Filament/Resources/CouponResource.php:60`.
- **Now:** the helper text says card payment isn't available. Stripe is wired, and `times_used` goes up after payment (`app/Models/Coupon.php:114`). Update the text.

---

## 🎨 Design: to decide

| Task | In Figma | What the API does |
|---|---|---|
| DS-01 | [Automations](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4564): runs 412 / 1,284 / 3,907 with only 6 contacts | Real runs (13, 9, 34). CTR matches |
| DS-02 | [Home](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-2212): "1 connected channel", but contacts on WhatsApp and TikTok | 3 channels |
| DS-03 | [Contact detail](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4433): Reem's source is "Auto-DM links from comments", a first move | The automation that answered her |
| DS-04 | W12 comment times (2m, 5m, 9m, 12m) differ from the [Inbox](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087) (5m, 14m, 41m, 3h) | Follows the inbox |
| DS-05 | [Solution · e-commerce](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-1579): `nourboutique.com/winter-abaya`; App: `abaya-winter` | Each page as drawn |
| DS-06 (P1) | [Landing page](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=38-42): "Meta Business Partner", "TikTok Marketing Partner", 4.8/5 from 2,000+ businesses, 1.2M+ messages, $2M+ revenue, creator quotes | Same text, editable in the admin. Confirm the claims are true before launch |

---

## Running the tracker on a server

Run the migration, then seed the tracker on its own (never a full `db:seed`):

```bash
php artisan migrate --force
```

```bash
php artisan db:seed --class=AlignmentItemSeeder --force
```

The seeder adds only missing codes, so it never overwrites a status changed in the panel.
