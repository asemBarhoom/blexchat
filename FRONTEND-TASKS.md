# Frontend tasks: API and Figma alignment (round 3)

| | |
|---|---|
| **Frontend** | `blexchat-website` `origin/main` @ `a2473f2` (after PR #6) |
| **Backend** | `blexchat` `master` @ `930f85c` (live on Laravel Cloud) |
| **Figma** | [blexchat (Copy)](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-), page **App** |
| **API base** | `/api/v1`, Sanctum bearer token, `?lang=ar\|en` |

**Status:** PR #6 closed almost everything. **3 tasks left**, all small, plus **11 new screens** the API now supports (FE-27–FE-37, at the end). Task numbers stay as in earlier rounds.

---

## 🟠 Left to do

### FE-26 · Contact detail: Subscribed and Language are hard-coded
- **Where:**
  - `src/pages/app/contacts/ContactDetail.tsx:232`: the "Subscribed" badge always shows.
  - `src/pages/app/contacts/ContactDetail.tsx:308–312`: Language always shows "Arabic".
  - `src/app/liveContacts.ts:20` (`toContact`): neither field is mapped.
- **API:** `GET /contacts/{id}` → `subscribed` (boolean) and `language` (`'ar'`, `'en'`, …, or `null`).
- **Fix:**
  - Map both in `toContact`.
  - Show the badge only when `subscribed === true`.
  - Show the language's name, or "Not collected" when it's `null`.
- **Figma:** [Contact detail](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4433).

### FE-17 · Inbox list stops at 100 chats (partly done)
- **Done:** every folder's count comes from `meta.total` (`src/app/liveInbox.ts:127–144`).
- **Left:** the list is still one `GET /conversations?status=all&limit=100` (`src/app/liveInbox.ts:131`). Past 100 chats, the rest never show and the filters miss them.
- **API:** `meta.next_cursor`. Pass it back as `?cursor=` until it is `null`. Or load more on scroll.
- **Figma:** [Inbox](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087).

### FE-08 · Folders: move, rename, delete (partly done)
- **Done:** list and create (`src/app/liveAutomations.ts:180`, `:189`).
- **Left:**
  - Move an automation: `PATCH /automations/{id}` with `{ automation_folder_id: number | null }`.
  - Rename: `PATCH /automation-folders/{id}` with `{ name }`.
  - Delete: `DELETE /automation-folders/{id}` → `204`. Its automations move to no folder.
  - `src/app/automationsStore.ts:116` still falls back to `'instagram'`. Use `'none'`.
- **Figma:** [Automations](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4564).

---

## ✅ Done in PR #6: verified in code

| Task | What | Where |
|---|---|---|
| FE-06 | Posts past 100, page by page | `liveAccount.ts:315` |
| FE-07 | Go-live refusals from the API, on the wizard and on Start | `liveAutomations.ts:263–271`, `AutomationDetail.tsx:183`; `FREE_AUTOMATIONS` removed |
| FE-11 | `can` and `waiting_in` on the connect screen | `liveOnboarding.tsx:46–47`, `ConnectChannel.tsx:342` |
| FE-13 | No coupon on a trial unless `coupons_on_trial` | `liveCheckout.ts:62`, `Checkout.tsx:391` |
| FE-14 | Contact edit sends every field | `liveContacts.ts:89` (`saveContact`), `ContactDetail.tsx:122` |
| FE-15 | Follow-up without a link | `liveAutomations.ts:107` |
| FE-16 | `post.comments` | `liveAutomations.ts:168`, `liveAccount.ts:290` |
| FE-18 | Blexchat AI switch, with `plan_required` | `AiLanding.tsx:160`, `automationsStore.ts:164` |
| FE-19 | Editing labels | `liveInbox.ts:220`, `Inbox.tsx:286` |
| FE-20 | Remind me and the Reminders folder | `liveInbox.ts:232`, `Inbox.tsx:335` |
| FE-24 | `expired` treated as disconnected | `liveAccount.ts:114` |
| FE-25 | Abaya sample copy instead of the jacket | `nourBoutique.ts`, `inboxContent.ts`, `wizardState.ts`, … |

Earlier rounds: FE-01–05, 09, 10, 12, 21–23.

---

## New in the API (round 4): screens to wire up

These are on `master` and go live with the next deploy. Every endpoint is in Swagger with its fields and error codes.

| Task | Screen | API |
|---|---|---|
| FE-27 | Automations list: search and "3 live of 5" | `GET /automations?search=` and `meta.live` (can replace the local search on `per_page=100`) |
| FE-28 | Marketing 404 page | `GET /home` → `not_found` {question, replied, answer, code, title, body, cta} (can replace `src/i18n/locales/en.json:2390`) |
| FE-29 | Inbox: a reminder that has come | `remind_due: true` on the conversation; the owner also gets an email |
| FE-30 | Contacts · Import (CSV) | `POST /contacts/import` (multipart `file` + `consent: true`) → `{imported, skipped: [{row, reason}], usage}`; `422 code: missing_columns` with `columns`. Headers in English or Arabic: `name` (or `first name`/`last name`), `phone`, optional `email`, `city`, `gender`, `tags` |
| FE-31 | Blexchat AI: the business info the AI answers from | `GET` / `PATCH /ai/settings` {business_info, included, live}. The automation's AI switch stays `PATCH /automations/{id}/ai` |
| FE-32 | Inbox: AI replies and the handover reason | Messages have `by_ai: true` for the AI's replies. A handover message's `reason` is now the AI's own line ("Asked about size 40 and a delivery date.") when it wrote one |
| FE-33 | Settings · Team | `GET /team` {members, invitations, seats {used, limit}, assignment, role}; `POST /team/invitations` {email} (`403 code: seat_limit`); `DELETE /team/invitations/{id}`; `DELETE /team/members/{id}` |
| FE-34 | Join page `/team/join?token=…` (the invitation email links here) | Sign the person in as usual, then `POST /team/invitations/accept` {token}. Errors: `404 invitation_invalid`, `403 other_email` (with `email`), `403 has_own_account` |
| FE-35 | A teammate's app | `GET /auth/me` → `role: owner \| agent`. An agent sees only the inbox and contacts; every other endpoint answers `403 code: owner_only`. `POST /team/leave` |
| FE-36 | Inbox: assign a conversation | `PATCH /conversations/{id}` {assigned_to: user id \| null} (ids from `GET /team` members; `403 plan_required` below Business). The list has `assigned_to` {id, name} and `?assigned=me\|unassigned` |
| FE-37 | WhatsApp, Messenger and TikTok threads | Replies now send for real on WhatsApp and Messenger. TikTok answers `502` with a message until TikTok opens its API. Locally, `POST /channels/{id}/test-events` {type: message} simulates a customer on all three |
