# Frontend tasks: API and Figma alignment (round 2)

| | |
|---|---|
| **Frontend** | `blexchat-website` `origin/main` @ `b5890be` (after PRs #3, #4, #5) |
| **Backend** | `blexchat` `master` @ `cc2b51c` (pushed) |
| **Figma** | [blexchat (Copy)](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-), page **App** |
| **API base** | `/api/v1`, Sanctum bearer token, `?lang=ar\|en` |

**Status:** 11 tasks done, 6 partly done, 9 open (FE-26 is new). Task numbers stay as in round 1.

---

## ✅ Done in PRs #3–#5: verified in code

| Task | What | Where it landed |
|---|---|---|
| FE-01 | Handover line reads `reason` | `liveInbox.ts:96–99` |
| FE-02 | Conversation `state` read as sent | `liveInbox.ts:82` |
| FE-03 | Email question gated on `has_email_capture` | `liveAutomations.ts:105`, `plans.ts:109–114` |
| FE-04 | Leads card lock from the dashboard's `locked` | `Home.tsx:110` |
| FE-05 | `@` only before a username | `liveApi.ts:108` `handleText` |
| FE-09 | Zero runs shows "n/a" | `liveAutomations.ts:148` |
| FE-10 | Connect steps of any length | `liveOnboarding.tsx:46` |
| FE-12 | Plan features of any length | `pagesApi.ts:185` |
| FE-21 | Post shown above a comment message | `inbox/thread.tsx:65` |
| FE-22 | Privacy and Terms pages | `App.tsx:97–98`, `pages/legal/LegalPage.tsx` |
| FE-23 | Navbar and footer words from `/home` | `homeApi.ts:49`, `apiToLocale.ts:304` |

---

## 🔴 P0: open

### FE-07 · "Couldn't go live" isn't driven by the API
- **Where:**
  - `src/app/liveAutomations.ts` → `saveWizard` (around line 202).
  - `src/pages/wizard/ReadyToLive.tsx:35`: `FREE_AUTOMATIONS = 3`, still hard-coded.
  - `src/pages/wizard/Wizard.tsx`: refused states come only from presets.
  - The detail page's Start button (`PATCH /automations/{id}/toggle`).
- **API:** `POST /automations/wizard` and `PATCH /automations/{id}/toggle` (with `{ active: true }`) can return `422`:
  ```json
  { "code": "channel_disconnected", "channel": "instagram", "message": "Couldn’t go live: Instagram is disconnected. …" }
  { "code": "live_limit", "limit": 3, "message": "Couldn’t go live: your plan allows 3 live automations. …" }
  ```
  `GET /plans` → `live_automations_limit` (`null` means no limit).
- **Fix:**
  - Branch on `error.body.code` to show the channel or limit notice.
  - Count from `limit`.
  - Delete `FREE_AUTOMATIONS`.
  - Handle the same `422` on Start.
- **Figma:** [W15 · failed-channel](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-10433) and [W15 · failed-limit](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-10523).

---

## 🟠 P1: partly done or open

### FE-06 · Posts past the first 100 (partly done)
- **Done:** `meta.synced` is read with a retry (`liveAccount.ts:299–302`).
- **Left:** `loadPosts` asks `?per_page=100` once (`liveAccount.ts:297`). Page with `?page=` while `meta.total` is above what's loaded, or load more on "Show all N posts".

### FE-08 · Folders: moving, renaming, deleting (partly done)
- **Done:** list and create (`liveAutomations.ts:177–190`); `automation_folder_id` is read (`:139`).
- **Left:**
  - Move an automation into a folder: `PATCH /automations/{id}` with `{ automation_folder_id: number | null }`.
  - Rename: `PATCH /automation-folders/{id}` with `{ name }`.
  - Delete: `DELETE /automation-folders/{id}` → `204`. Its automations move to no folder.
  - `src/app/automationsStore.ts:116` still falls back to `'instagram'`; use `'none'` like `liveAutomations.ts:146`.

### FE-11 · Connect screen: `can` and `waiting_in` (partly done)
- **Done:** `options[].url` and `ineligible.help_url` (`liveOnboarding.tsx:49–54`, `ConnectChannel.tsx:363`).
- **Left:** both are text from `GET /onboarding/channels/{platform}/connect`, and neither is read (`onboardingApi.ts`):
  - `can`: what Blexchat can do once connected (W07).
  - `waiting_in`: the connecting state's "Finish in …".

### FE-13 · `coupons_on_trial` and the checkout's saving (partly done)
- **Done:** `annual_saving_up_to` from `/plans` (`pagesApi.ts:290`).
- **Left:** `GET /checkout` → `coupons_on_trial` (boolean) and `annual_saving_up_to` aren't read in `src/app/liveCheckout.ts`. Hide the coupon field on a trial when `coupons_on_trial === false`.

### FE-14 · Contact edit sends only tags
- **Where:** `src/app/liveContacts.ts:81–82` (`saveTags`).
- **API:** `PATCH /contacts/{id}` accepts `name`, `phone`, `email`, `city`, `gender` and `tags`.
- **Fix:** a `saveContact(id, fields)` for the detail panel's Details block.
- **Figma:** [Contact detail](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4433).

### FE-15 · Follow-up is sent only when there's a link
- **Where:** `src/app/liveAutomations.ts:106`: `follow_up: paid && !!d.followUp && !!d.link`.
- **API:** the wizard accepts `follow_up` without `link_url`.
- **Fix:** drop `!!d.link`, unless it's a deliberate product rule. If it is, say so and the backend will refuse it too.

### FE-16 · Comment count on the post (partly done)
- **Done:** the detail uses `post` from `GET /automations/{id}` (`liveAutomations.ts:158`, `AutomationDetail.tsx:308`).
- **Left:** `post.comments` (a number, beside `likes`) isn't mapped, so "عرض كل التعليقات (٤)" can't show it. Live mode passes `comments={[]}` (`AutomationDetail.tsx:309`). Add it to the post mapping in `liveAutomations.ts:158` and `liveAccount.ts` (`toPost`, `:281`).
- **Figma:** [Automation detail · preview](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-5759).

### FE-17 · Inbox count and lists past 100
- **Where:** `src/app/liveInbox.ts:118`: one `GET /conversations?status=all&limit=100`; then filters and counts run on that page (`Inbox.tsx:357–406`).
- **Problem:** with more than 100 chats, counts and filters miss the rest.
- **API:**
  - `meta.total`: the count of everything the filters match.
  - `meta.next_cursor`: the next page; pass it as `?cursor=`.
  - The filters run on the server:
    - `folder=favorites|labels|reminders`
    - `label=`
    - `state=human`
    - `unread=1`
    - `channel_id=`
    - `sort=oldest`
- **Fix:** show "All chats N" from `meta.total`, and page with the cursor (or filter on the server).

### FE-26 · Contact detail: Subscribed and Language (new)
- **Where:** `src/app/liveContacts.ts:20–40` (`toContact`).
- **API:** `GET /contacts/{id}` → `subscribed` (boolean) and `language` (`'ar'`, `'en'`, …).
- **Figma:** [Contact detail](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4433) shows the "Subscribed" badge and "Language · Arabic".

---

## 🟢 P2: open

### FE-18 · Blexchat AI switch
- **API:** `PATCH /automations/{id}/ai` with `{ enabled: boolean }` → `{ data: { id, ai_enabled } }`.
  - Returns `403 { code: 'plan_required' }` when the plan's `has_ai` is false.
  - `ai_enabled` is on every automation.
- **Where:** nothing calls it; `src/pages/app/ai/`.

### FE-19 · Editing labels (partly done)
- **Done:** favourites (`liveInbox.ts:185` `setStarred`); `starred` and `labels` are read (`:84–85`).
- **Left:** no call sets labels. `PATCH /conversations/{id}` with `{ labels: string[] }` (the whole list; max 10, each up to 30 characters; `[]` clears them).

### FE-20 · Reminders
- **Where:** `src/pages/app/inbox/Inbox.tsx:40`: `{ id: 'reminders', locked: true }`.
- **API:**
  - `PATCH /conversations/{id}` with `{ remind_at: ISO-8601 }` (in the future; `null` clears it).
  - On Free, setting one returns `403 { code: 'plan_required' }`.
  - `GET /conversations?folder=reminders` lists them soonest first.
  - `remind_at` is on every conversation.
- **Fix:** unlock on paid plans, add a "Remind me" action, and list the folder.
- **Note:** nothing fires at `remind_at` yet. The folder is the reminder.

### FE-24 · Expired channel → reconnect
- **API:** `GET /channels` → `status: 'active' | 'inactive' | 'expired'`.
- **Where:** nothing in `src/app` or `src/pages/app` handles `'expired'`.
- **Fix:** treat `expired` like disconnected: the banner, plus a Reconnect CTA.
- **Figma:** [Home · Instagram disconnected](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-2546).

### FE-25 · Sample content still shows the jacket and dollars
- **Problem:** the API demo data and Figma use the winter abaya at ٢٤٩ ريال, in Gulf Arabic.
  - Link `nourboutique.com/abaya-winter`, button "شوفي المنتج".
  - DM: "هلا والله! 👋 هذي عباية الشتاء اللي سألتِ عنها — ٢٤٩ ريال. المقاسات والطلب من هنا:"
  - Post: "عباية الشتاء وصلت ✨ اكتبي «السعر» بالتعليقات ونرسل لك التفاصيل بالخاص"
  - People: Hessa Q. and أم فيصل.
- **Files that still say جاكيت / winter-jacket / $65:**
  - `src/components/preview/nourBoutique.ts`
  - `src/pages/app/inbox/inboxContent.ts`
  - `src/pages/app/flowContent.ts`
  - `src/pages/wizard/wizardState.ts`
  - `src/app/automationsStore.ts`
  - `src/pages/solutions/solutionsContent.ts`
  - `src/i18n/locales/en.json` and `ar.json`
- **Fix:** align the copy that renders without the API (logged-out preview and review presets).

---

## Behaviour changes to be aware of

| Field | Change |
|---|---|
| `automations[].ctr` | `0` instead of `null`; the list already shows n/a for zero runs |
| `contact.email` | can be `null` ("Not collected", as Reem in Figma) |
| `plans[].live_automations_limit` | new (FE-07) |
| `post.comments` | new (FE-16) |
| `conversation.remind_at` | new (FE-20) |
| conversations `meta.total` | new (FE-17) |

## Optional: fields returned and not read

| Endpoint | Fields |
|---|---|
| `GET /dashboard` | `user`, `channels`, `automations` |
| `GET /onboarding` | `channels`, `survey_completed_at`, `subscription` |
| `GET /channels` | `connected_at` |
| `GET /channels/{id}/posts` | `media_type`, `posted_at` |
