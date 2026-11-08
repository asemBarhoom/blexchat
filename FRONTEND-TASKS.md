# Frontend tasks: API and Figma alignment (round 3)

| | |
|---|---|
| **Frontend** | `blexchat-website` `origin/main` @ `a2473f2` (after PR #6) |
| **Backend** | `blexchat` `master` @ `930f85c` (live on Laravel Cloud) |
| **Figma** | [blexchat (Copy)](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-), page **App** |
| **API base** | `/api/v1`, Sanctum bearer token, `?lang=ar\|en` |

**Status:** PR #6 closed almost everything. **3 tasks left**, all small. Task numbers stay as in rounds 1 and 2.

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

## Coming from the backend (no frontend work yet)

| What | When it lands |
|---|---|
| `GET /automations?search=` and `meta.live` (Figma "Search automations", "3 live of 5") | Can replace the local search on `per_page=100` |
| Marketing 404 copy in `GET /home` | Can replace `src/i18n/locales/en.json:2390` |
