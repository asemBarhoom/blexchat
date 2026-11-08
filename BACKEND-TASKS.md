# Backend tasks: Figma and API alignment

| | |
|---|---|
| **Backend** | `blexchat` `master` (branch `feat/alignment-tracker`) |
| **Frontend** | `blexchat-website` `origin/main` @ `b5890be` |
| **Figma** | [blexchat (Copy)](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-): pages **App** (`5:7`) and **Website** (`5:2`) |
| **Tracker** | Admin panel → **المطابقة** → **مطابقة فيغما والـ API** (`/admin/alignment-items`) |

**Status:** 17 of 18 done. Only BE-11 (many accounts, one seat) is left, by decision.

---

## ✅ Done

| Task | What | Where |
|---|---|---|
| BE-01 | Go-live refused when the channel is disconnected: `422 {code: channel_disconnected}` | `User.php`, `AutomationController.php` |
| BE-02 | `plans.live_automations_limit` (Free = 3), `422 {code: live_limit}` | `User.php`, Filament `PlanResource` |
| BE-03 | `remind_at` and `?folder=reminders` | `ConversationController.php` |
| BE-04 | `post.comments` from Instagram's `comments_count` | `PostResource.php` |
| BE-05 | `meta.total` on `GET /conversations` | `ConversationController.php` |
| BE-06 | A due reminder emails the owner (template `reminder.due`) and sets `remind_due` | `SendDueReminders` (every minute), `ReminderDueMail` |
| BE-07 | WhatsApp Cloud API: webhook `/webhooks/whatsapp`, inbox replies sent | `MessagingClient`, `ProcessMetaChannelWebhook` |
| BE-08 | TikTok: messages in the inbox on the fake driver; real replies answer 502 until TikTok opens its API | `MessagingClient`, `ChannelTestEventController` |
| BE-09 | Messenger: webhook `/webhooks/messenger`, inbox replies sent | `MessagingClient`, `ProcessMetaChannelWebhook` |
| BE-10 | Team: invite by email, seats per plan (1/1/3/10/25), teammates (role `agent`) see the inbox and contacts only, assignment on Business and Advanced | `TeamController`, `EnsureAccountOwner`, `TeamInvitationMail` |
| BE-12 | Blexchat AI (Claude) answers what no keyword matches, from the owner's business info | `ClaudeClient`, `AutomationRunner::answerWithAi`, `/ai/settings` |
| BE-13 | When unsure, the AI hands over with a one-line reason (`message.reason`) | `Conversation::handOver`, `MessageResource` |
| BE-14 | `GET /automations?search=` and `meta.live` | `AutomationController.php` |
| BE-15 | Contacts CSV import | `ContactImporter`, `POST /contacts/import` |
| BE-16 | Marketing 404 copy in `GET /home` → `not_found` | `HomeController.php`, `HomeContentSeeder` |
| BE-17 | Free plan's help is Email | `PricingPageSeeder.php` |
| BE-18 | Coupon redemptions shown in the admin | `CouponResource` |

## Left

### BE-11 · Many accounts, one seat (Advanced, agencies)
- Kept for later, by decision. The API has no workspace switch.

## To set on Laravel Cloud

| Setting | For |
|---|---|
| `ANTHROPIC_API_KEY` | Blexchat AI. Without it, AI is off and conversations are handed over as before |
| `META_APP_SECRET`, `META_WEBHOOK_VERIFY_TOKEN` | The WhatsApp and Messenger webhooks |
| The scheduler, and mail | Reminder emails (BE-06) and team invitations (BE-10) |
