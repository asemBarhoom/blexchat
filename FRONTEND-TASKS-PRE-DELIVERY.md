# Frontend tasks before delivery

This file collects every frontend task left before delivery: 11 tasks. It covers the Trello list «قبل التسليم · اليوم» and what QA turned up on 2026-11-12.

Each task lists the reference (card, file, line), the evidence (what was seen in production or in the code), what is wanted, and how to check it.

**Repo:** `uxbyissa/blexchat-website` (React + Vite + react-i18next). Base everything on `origin/main`.
**Site:** https://blexchat-website.vercel.app
**API:** https://blexchat-production-ewv0u6.laravel.cloud/api/v1

**Rules for every task:**
- One branch per task, from `origin/main`, in a separate git worktree.
- Put every new string in `src/i18n/locales/en.json` and `src/i18n/locales/ar.json`. The Arabic must read naturally in RTL.
- Check every task with `npm run build`, in Arabic and English, at desktop width and at 375px phone width.
- Reply for each task with the PR link (against `main`), the list of screens that changed, and before/after screenshots.
- Don't change any API behaviour. Everything below is already ready on the backend unless noted.

---

## Priority order

| # | Task | Card | Section | What the client sees |
|---|---|---|---|---|
| 1 | The app opens without signing in, on demo data | P-18 | §8 | Anyone can get in; tests run on fake data |
| 2 | Pricing page sends a signed-in user to sign-up | P-13 | §1 | A customer who wants to pay can't |
| 3 | Hard-coded coupon on the checkout page | P-9 | §2 | A discount that isn't real |
| 4 | Buttons that do nothing or show a "preview" | P-19 | §9 | Looks unfinished |
| 5 | Dashboard says "one channel connected" with no channel | P-12 | §3 | Wrong number on the first screen |
| 6 | Automation wizard dead end with no channel | P-14 | §4 | The user gets stuck in the main feature |
| 7 | Channels "coming soon" + channel limit | P-11 | §5 | Raw errors on Messenger / WhatsApp / TikTok |
| 8 | Team and AI settings not shown at all | P-20 | §10 | Features paid for and not shown |
| 9 | No language switch inside the app + English leftovers | P-21 | §11 | An Arabic customer can't switch the language |
| 10 | Help centre has no public page and no link | P-16 | §6 | Help articles can't be reached |
| 11 | Check the Instagram return after Unified Login | P-17 | §7 | Connecting Instagram finishes or fails |

---

## 1. P-13 · Pricing page sends a signed-in user to sign-up

**Reference:** https://trello.com/c/abMW0ZaY
**Branch:** `fix/pricing-signed-in-checkout`
**Files:** `src/pages/marketing/PricingPage.tsx`, `src/app/plans.ts`, the marketing navbar.

**Evidence (production QA):**
- A signed-in Free user opens `/pricing` and presses "Choose Pro" (or Essential / Business).
- They land on `/signup?plan=pro&billing=annual`, the "CREATE YOUR FREE ACCOUNT" screen.
- "Get started free" also goes to `/signup`.

**Wanted:**
1. When the user is signed in (the session / token in authStore):
   - A paid plan button opens the existing checkout modal (`CheckoutBoundary`, the same one "Upgrade" opens inside `/app`), with the plan and billing period preselected.
   - "Get started free" and the Free plan buttons go to `/app`.
   - The user's current plan shows as "Current plan" and is disabled.
2. Signed out: no change (`/signup?plan=...`). After signing up, the chosen plan must open checkout. Check this already works, and fix it if not.
3. In the marketing navbar, when signed in: show "Open app" pointing to `/app`, in place of "Sign in" / "Get started".

**Check:**
- Signed in: every plan button opens checkout with the right plan and period, and Free goes to `/app`.
- Signed out: unchanged.

---

## 2. P-9 · Checkout: the coupon comes from the API only

**Reference:** https://trello.com/c/bd6Fql3h
**Branch:** `fix/checkout-coupon-from-api`
**Files:** `src/app/Checkout.tsx`

**Evidence (code):**
- At ~line 73: `export const COUPON = { code: 'BLEX20', off: 0.2 }`.
- At ~405 it's suggested in the coupon field to visitors who aren't signed in.
- At ~233 and ~430 it's accepted locally at 20% without asking the server.

**Evidence (API):**
- `POST /api/v1/coupons/check` with BLEX20 returns 200 with 20% off.
- An invalid code returns 422 with a translated message.

**Wanted:**
1. Remove `COUPON` and every path that accepts `BLEX20` locally.
2. Visitor not signed in:
   - Don't suggest a code, and give no local discount.
   - Show a short note that the coupon applies at checkout after signing in.
   - Keep the code they typed until they sign in, then check it.
   - If `coupons/check` works without signing in (test it with curl), use its response in place of the note.
3. Signed in: the text and the discount percentage come only from the API response.

**Check:** a valid code and an invalid one, both signed in and signed out.

---

## 3. P-12 · Dashboard says "one channel connected" with no channel

**Reference:** https://trello.com/c/fhXRFUcE
**Branch:** `fix/home-connected-channels`
**Files:** `src/pages/app/Home.tsx` (~79, ~122, ~151) and wherever `useSession()` fills `channels`.

**Evidence (production QA):**
- A Free user with no channel sees "قناة واحدة متصلة" / "1 channel connected" on `/app`.
- Meanwhile `GET /api/v1/channels` returns `{ "data": [] }`, and the Settings page correctly says "no channel connected yet".

**Cause:** `channels` in `useSession()` includes the channel the user *picked* during onboarding, not only the ones actually connected.

**Wanted:**
1. The dashboard's channel count, the channel marks and the "connect a channel" step use only connected channels, from `GET /api/v1/channels` or the dashboard API.
2. Fix it at the source if other screens show the same wrong count (sidebar, settings, inbox). Keep the onboarding pick separately if onboarding needs it.
3. With 0 channels, show the zero line: "No channels connected" / "لا توجد قنوات متصلة". `app.home.channelLine_zero` already exists in ar.json; check en.json.

**Check:**
- 0 channels: the zero line shows, and the step isn't marked done.
- 1 channel: shows 1, with the Instagram mark.

---

## 4. P-14 · Automation wizard with no channel + small QA fixes

**Reference:** https://trello.com/c/Ro9JbH9P
**Branch:** `fix/automation-wizard-no-channel`
**Don't touch:** `Checkout.tsx` (P-9), the channel screens (P-11), `Home.tsx` (P-12), the pricing page (P-13).

**Evidence (production QA, Free user with no channel):**
1. `/app/automation` → "Start with Comment to DM" opens `/onboarding/automation/post`. It shows "No posts yet. Choose 'Your next post or reel'", but that option carries an UPGRADE badge on Free. Dead end.
2. After "Create contact", the "FREE CONTACTS 0/25" counter in the sidebar stays at 0 until the page reloads.
3. The new-contact form defaults to Egypt (EG +20).
4. In the English UI, `/app/ai` shows Arabic sample messages ("كم السعر؟", ...). The wizard preview shows "إعجاب" and the demo account `nourboutique`.

**Wanted:**
1. If `GET /api/v1/channels` returns `[]`, don't start the wizard. Show "Connect Instagram first" / «اربط Instagram أولاً», with a button to connect, then return to the wizard after connecting.
   - Apply the same guard to every entry point: the dashboard cards, the templates, "+ New automation".
   - With a channel but no posts: the empty-state text mustn't suggest an option the plan doesn't include.
2. Refresh `GET /api/v1/contacts/usage` after adding, importing or deleting contacts.
3. Default country: the account's country if known, else Saudi Arabia (SA +966).
4. Samples follow the UI language. The preview uses the user's own account name and handle when known.

**Check:**
- With no channel, every automation entry point leads to "connect first".
- The counter updates without a reload.

---

## 5. P-11 · Channels screen: "coming soon" + channel limit

**Reference:** https://trello.com/c/qY3UubTH
**Branch:** `feat/channels-pending-and-limit`

**Evidence (API, already on production):**
- `GET /api/v1/onboarding/channels`: each item has `available` and `pending_reason`.
- `GET /api/v1/channels/oauth/{platform}/start` returns `503` with `{ message, code: "provider_pending", platform }` for channels Meta hasn't approved yet.
- `GET /api/v1/plans` has `channels_limit` (Free = 1, `null` = no limit).
- A connection over the limit returns `403 { code: "channel_limit", limit, message }`, with a translated message.

**Wanted:**
1. Messenger, WhatsApp and TikTok: when `available: false` or `provider_pending`, show a calm "Coming soon" badge with `pending_reason`, and disable the connect button. Never show a raw error or "Something went wrong". Instagram stays as it is.
2. Channel limit:
   - Show `message` with an "Upgrade" button, in the same pattern as `live_limit` / `workspace_limit`.
   - Show "1 / 1 channels" when there's a limit.
   - Show the message before OAuth starts once the limit is reached.

**Check:** mock responses for `403 channel_limit` and `503 provider_pending`.

---

## 6. P-16 · Help centre: no public page and no link

**Reference:** https://trello.com/c/lyNd8UdM (QA on 2026-11-12, item 4)
**Suggested branch:** `feat/public-help-centre`
**Files:** `src/App.tsx` (~129), `src/pages/app/help/HelpCenter.tsx`, the marketing navbar and footer (+ their data from `GET /api/v1/home` → `navigation` / `footer`).

**Evidence:**
- The help centre exists only inside the app, at `/app/help` (`src/App.tsx` ~129), and reads `GET /api/v1/help`.
- `GET /api/v1/help` works **without signing in**. It currently returns real articles on production (the seeder ran).
- There's no public page (`/help`) and no "Help" link in the marketing navbar or footer. A visitor or customer who isn't signed in can't reach the answers.

**Wanted:**
1. A public route `/help` that shows the same content as `HelpCenter` inside `MarketingLayout`. Reuse the component, don't copy it.
2. Add a "Help centre" / «مركز المساعدة» link to the footer, and to the navbar if the design has room.
   - If the links come from the API (`footer.columns`), tell us and we'll add it from the admin. Don't hard-code it in the frontend in that case.
3. Keep `/app/help` as it is inside the app.

**Check:** `/help` opens while signed out, shows the articles in both languages, and the link works from every marketing page.

---

## 7. P-17 · Check the Instagram return after Unified Login

**Reference:** https://trello.com/c/tT5jkjX7 · backend commit `ce44076`, "Merge Instagram Unified Login", now on production.
**Files:** `src/pages/auth/liveOnboarding.tsx` (~125–165, `InstagramReturn`), `src/app/onboardingApi.ts` (~72, ~145).

**Evidence:**
- Meta now accepts only **Unified Login** for the Blexchat app. The old route (`instagram.com/oauth/authorize`) was returning "Invalid platform app".
- The backend changed `GET /api/v1/channels/instagram/authorize`, which now returns `https://www.facebook.com/dialog/oauth?client_id=…&config_id=…&redirect_uri=https://blexchat-website.vercel.app/onboarding/connect&response_type=code&state=…`.
- Tested on production: "Connect via Meta" opens the "Facebook Login for Business" window correctly.
- Meta sends the user back to the same `/onboarding/connect?code=…&state=…`. The frontend should send that to `POST /api/v1/channels/instagram` as before.
- Not tested all the way through yet: the Instagram account first has to be linked to the business portfolio in Meta.

**Wanted (check, not a rewrite):**
1. Make sure `InstagramReturn` handles the return from `facebook.com`. That includes Meta's extra parameters, such as `#_=_` at the end of the URL, and `error=access_denied` / `error_reason=user_denied` when the user cancels.
2. The texts on the connect screen:
   - "Log in to Facebook → Choose your Instagram account" is correct for the new flow.
   - Add a short note that the Instagram account has to be **Professional and linked to a business portfolio in Meta Business Suite**. That's the most common reason the "Instagram Account" list in Meta's window shows up empty.
3. If the backend returns `422 account_ineligible` with no `account` (Meta came back without an Instagram account), the screen should explain that, not show an empty account.

**Check:** connect a real Professional account that's in the portfolio, then cancel from Meta's window, then come back with no account selected.

---

## 8. P-18 · The app opens without signing in

**Reference:** https://trello.com/c/MWHet4Ky (the owner's note on 2026-11-12)
**Suggested branch:** `fix/app-requires-sign-in`
**Files:** `src/App.tsx` (~106, `<Route path="/app" element={<AppShell />}>`), `src/app/AppShell.tsx`, `src/pages/auth/authLinks.ts` (~58, `exploreApp: '/app'`), `src/app/Sidebar.tsx` (~150, `nour@nourboutique.com`).

**Evidence:**
- `/app` and every route under it has no sign-in guard. `AppShell` renders whether or not there's a session.
- With no account, the app opens on demo data (`Nour`, `nour@nourboutique.com`, `@nourboutique`).
- "Explore without connecting" on the connect screen points to `/app` (`AUTH_LINKS.exploreApp`).
- A QA agent tested on this demo data and thought it was a real account.

**Wanted:**
1. Any route under `/app` with no session → `/signin?next=<the path>`. After signing in, return to `next`.
2. A session that expired, or a 401 from any call → the same thing, with a short "Your session ended, sign in again" message.
3. "Explore without connecting" is for a **signed-in** user who hasn't connected a channel. It opens the app on their own account with empty states, not on Nour's data.
4. Remove demo data from any screen a real user can reach, or mark it clearly as an "Example" where it's meant to be one, like the samples in the wizard.

**Check:** a private (Incognito) window → `/app`, `/app/contacts`, `/app/inbox` all go to sign-in. After signing in, you land on the page you asked for.

---

## 9. P-19 · Buttons that do nothing or go to a "preview" note

**Reference:** https://trello.com/c/mO5xKqib · the owner's note ("lots of buttons aren't clickable") + a code review on 2026-11-12.
**Suggested branch:** `fix/wire-placeholder-actions`

**Evidence (code, `origin/main`). Each of these buttons shows a toast or a "this is a preview" page, when the API for it is ready on the backend:**

| Button | Location | What it does now | API ready on the backend |
|---|---|---|---|
| Import (Contacts) | `src/pages/app/contacts/Contacts.tsx` ~131 | Toast: "Import is part of the full build" | `POST /api/v1/contacts/import` |
| Manage accounts (My Profile menu) | `src/app/Sidebar.tsx` ~159 | `/app/preview-note` | `GET/POST/PATCH/DELETE /api/v1/workspaces` (the switcher already uses it) |
| Inbox settings (the icon) | `src/pages/app/inbox/Inbox.tsx` ~638 | `/app/preview-note` | `GET/PATCH /api/v1/inbox/settings` |
| Advanced filters (+ filter) | `src/pages/app/inbox/Inbox.tsx` ~705 | `/app/preview-note` | Filters on `GET /api/v1/conversations` (check the `status`/channel params) |
| Start from a blank canvas | `src/pages/app/TemplatesModal.tsx` ~272 | `/app/preview-note` | The automation wizard (`POST /api/v1/automations`) |
| "Next best" card of kind `note` | `src/pages/app/AutomationDetail.tsx` ~95 | `/app/preview-note` | Depends on the card. Point it at a real screen or hide it |
| More connect options | `src/pages/auth/ConnectChannel.tsx` ~391 | `previewNote(...)` | Point it at the real channel, or "Coming soon" (P-11) |
| "How do I convert my account" (ineligible) | `src/pages/auth/ConnectChannel.tsx` ~302 | `previewNote(...)` | An external link to Instagram's help: switching to a Professional account |
| Menu item with no destination | `src/components/nav/MegaMenu.tsx` ~66 | `<a href="#">` | Any menu item without `to`/`section` must not render as a link |

**Wanted:**
1. Every button in the table does its real job through the API in its column. If there's no API and no real screen for it, **hide the button**. Don't show a "preview" page or a "full build" toast in the version handed to the client.
2. Contacts Import: upload a CSV → `POST /api/v1/contacts/import`, show the result (how many added, how many skipped, why), and refresh the counter (P-14).
3. `/preview-note` and `/app/preview-note` should no longer be reachable from any button. Keep the route only if something outside still needs it.
4. After the fix, go through every screen in the app and the site, press every button, and list anything that still does nothing.

**Check:** `git grep -n "preview-note\|previewNote\|importIsPartOfThe\|href=\"#\""` returns only the route itself, or nothing.

---

## 10. P-20 · Show all the data the API returns (Team + Blexchat AI settings)

**Reference:** https://trello.com/c/gFRbOUcn · the owner's note ("make sure all the data the API returns is shown") + a comparison of backend routes with frontend calls on 2026-11-12.
**Suggested branch:** `feat/team-and-ai-settings`

**Evidence:** I compared every `GET` route under `/api/v1` with every `/api/v1/...` call in `src` (`origin/main`). These endpoints are ready on the backend and **the frontend never calls them**:

| Endpoint | What it's for | Where it should show |
|---|---|---|
| `GET /api/v1/team` | Team members and pending invitations | Settings → Team |
| `POST /api/v1/team/invitations` | Invite by email | Settings → Team → "Invite" |
| `DELETE /api/v1/team/invitations/{invitation}` | Cancel an invitation | Next to each pending invitation |
| `DELETE /api/v1/team/members/{member}` | Remove a member | Next to each member (owner only) |
| `POST /api/v1/team/leave` | Leave a team | Settings for someone who isn't the owner |
| `POST /api/v1/team/invitations/accept` | Accept an invitation | The page the invitation link opens |
| `GET /api/v1/ai/settings` + `PATCH` | Blexchat AI settings | `/app/ai` (it shows only a demo today) |

`git grep -i "team\|invite\|ai/settings" src` finds no screen that uses them. So "team invite" in the QA list can't be tested at all.

**Wanted:**
1. A **Team** section in Settings:
   - The members list with their role.
   - Pending invitations with "Cancel".
   - An "Invite" form (email).
   - "Remove", for the owner only.
   - "Leave the team", for anyone who isn't the owner.
   - Plan limits on seats, if any: show the API's `message` with "Upgrade", in the same pattern as `live_limit`.
2. An invitation-accept page that calls `POST /team/invitations/accept`, then takes the user into the app.
3. **Blexchat AI settings** on `/app/ai`: read `GET /ai/settings`, show every field it returns, save with `PATCH`. Keep the demo only as an example, labelled as one.
4. **A full review:** for each screen, compare the API response fields (see `FRONTEND-API-GUIDE.md` and `/api/documentation`) with what's shown. Put every field that isn't shown into a table in the PR: show it, or a reason for leaving it out. Start with:
   - `GET /dashboard` (`checklist`, `start_here`, `last_seven`)
   - `GET /contacts` (`meta.usage`)
   - `GET /automations` (`meta.live`)
   - `GET /plans` (`tagline`, `channels_limit`, `live_automations_limit`, `workspaces`)

**Check:** invite an email, cancel the invitation, accept an invitation from another account, then change an AI setting and reload: it stays saved.

---

## 11. P-21 · No language switch inside the app + leftover English strings

**Reference:** the Arabic QA report on 2026-11-12 (`/app?lang=ar`).
**Suggested branch:** `fix/in-app-language-and-ar-leftovers`

**Evidence:**
- **No language switch inside the app.** The QA had to force Arabic with `?lang=ar`.
  - `LanguageMenu` (`src/components/nav/LanguageMenu.tsx`) exists only in the marketing `Navbar`.
  - Inside the app, the language is set only once, at sign-in, from `user.locale` (`src/app/authStore.ts` ~91).
  - The backend saves the language in `locale` (`ar` | `en`) through `PATCH /api/v1/auth/me`. Emails and Blexchat AI use it.
- `n/a` hard-coded in English, in the runs column of the automations list: `src/pages/app/automationsContent.ts` ~216 (`noRuns: 'n/a'`).
- Mixed text: "أنت على باقة Free", the "FREE" / "Essential" card in the sidebar. The plan name is placed into Arabic text as it is.

**Checked and fine (no change needed):**
- RTL is correct on every screen. No layout breaks, and icons flip correctly.
- In the CSS there are only 5 `left: 50%` used for centering, plus one comment. That's sound in RTL. No `margin-left` / `padding-right` / `text-align: left` to convert.

**Wanted:**
1. A language switch inside the app, under My Profile or Settings → Profile:
   - Calls `PATCH /api/v1/auth/me` with `{ "locale": "ar" | "en" }`, then changes the UI right away, with no reload.
   - Every API response after that comes in the chosen language, since the request sends `Accept-Language` / `lang`.
   - The choice persists after signing out and back in.
2. Move `noRuns: 'n/a'` to en.json / ar.json (e.g. "—" or «لا يوجد»). Check `automationsContent.ts` and the other `*Content.ts` files for any other text not in the translation files.
3. The plan name: show `plan.name` from the API as it comes. The backend now sends the Arabic name (مجانية، أساسية، احترافية، أعمال، متقدمة), and the API messages say «الباقة المجانية». Don't translate it in the frontend, and don't hard-code `FREE`/`Essential`.
4. "Blexchat AI" and the channel names (Instagram / WhatsApp / TikTok) are brand names and stay in English. Wrap them with `<bdi>` or `unicode-bidi: isolate` so the order of an Arabic sentence that contains them doesn't break (e.g. «رد على reem.k عبر Instagram»).

**Check:** switch the language from inside the app, reload, sign out and back in: it stays. No English left in the Arabic UI except the brand names.

---

## Not a frontend task (for the record)

| Note | Why it isn't on the frontend |
|---|---|
| "My Profile" goes to a 404 | Not a bug. "My Profile" in the sidebar is a menu, not a page (`src/app/Sidebar.tsx` ~137). The QA agent typed `/app/profile` by hand. |
| Plan names stay in English in Arabic | Fixed on the backend: `GET /api/v1/plans?lang=ar` now returns «مجانية», «أساسية», and so on. Any "Free" left in the Arabic UI is written into the frontend: P-21 item 3. |
| The magic-link email never arrives | Production is on Mailtrap **Sandbox**, which catches every email. A real sending service on Cloud is needed. |
| Image 403 on `/storage` (P-15) | Fixed on the backend. Images come from the Cloud bucket. |
| The demo data ("Nour", "nourboutique") | That's what shows in Explore mode with no account. Don't count it as a finding when testing the real account. |
