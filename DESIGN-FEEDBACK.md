# Design feedback: Figma vs the API

| | |
|---|---|
| **Figma** | [blexchat (Copy)](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-): pages **App** (`5:7`) and **Website** (`5:2`) |
| **Compared with** | The API on `master` (`7d3659e`) and the frontend `origin/main` (`b5890be`) |
| **Tracker** | Admin → **المطابقة** → **مطابقة فيغما والـ API**, codes `DS-01`–`DS-12` |

**Website page:** matches the API text for text, in English and Arabic: the landing page, pricing, the navbar, the footer and all 8 solution pages. Nothing to fix there apart from DS-05 and DS-06.

---

## 🎨 For the designer

### Screens the API supports, but Figma doesn't draw

| Task | Missing screen | Where Figma points to it | What the API already has |
|---|---|---|---|
| DS-07 (P1) | **Billing and plan:** change plan, cancel, see the next bill | Pricing FAQ: "Any time, from your account" ([Pricing](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-821)); Home banners "Upgrade" | `GET`, `POST` and `DELETE /subscription` |
| DS-08 (P1) | **My Profile:** name, business name, email, language | Sidebar "My Profile" ([Home · profile menu](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-3229)) | `GET` and `PATCH /auth/me` |
| DS-09 (P2) | **Insights** | "1 connected channel · See Insights" on [Home](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-2212) | `GET /stats/overview`, `GET /automations/{id}/stats` |

### Features sold on pricing that have no screens

The designer and the backend need these together (backend tasks BE-10 and BE-11).

| Task | Feature | Where it is promised | Screens needed |
|---|---|---|---|
| DS-10 (P1) | **Team:** "Inbox teammates" (1 / 1 / 3 / 10 / 25) and "Team roles and assignment" | [Pricing · compare](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-821); Pro "Inbox for 3 teammates" | Invite a teammate, the team list with roles, and "Assign to" on a conversation in the Inbox |
| DS-11 (P2) | **Many accounts, one seat** (Advanced) | The "Manage accounts" item in the [profile menu](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-3229); the [agencies page](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-2269) FAQ | The account list, adding an account, and switching between accounts |

### Flows that are only half drawn

| Task | What is missing | Figma |
|---|---|---|
| DS-12 (P2) | **Reminders:** there is a Reminders folder, but no "Remind me" picker (date and time) and no state for a reminder that has come due (BE-06) | [Inbox](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087) |
| DS-12 | **Help center:** only a "preview note", but pricing gives Free plans "Help center" (BE-17). Draw it, or change the pricing row to "Email" | [Help center · preview note](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6414) |
| DS-12 | **Handover reason:** Figma writes a sentence per conversation ("Asked about a size and a delivery date…"), which needs AI. Until then the API sends one sentence per reason, so write the copy per reason | [Inbox](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087) |
| — | **Contacts import** is marked "full build". No change needed now | [Contacts · Import](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4242) |

### The design contradicts itself

| Task | In Figma | What the API does |
|---|---|---|
| DS-01 | [Automations](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4564): runs 412 / 1,284 / 3,907, but the account has 6 contacts on Free | Real runs (13, 9, 34). CTR matches (38%, 22%, 41%) |
| DS-02 | [Home](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-2212): "1 connected channel", but contacts come from WhatsApp and TikTok | 3 channels |
| DS-03 | [Contact detail](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4433): Reem's source is "Auto-DM links from comments", which is a first move, not an automation | Shows the automation that answered her |
| DS-04 | W12 comment times (2m, 5m, 9m, 12m) don't match the [Inbox](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087) (5m, 14m, 41m, 3h) | Follows the inbox |
| DS-05 | Same abaya, two links: [Solution · e-commerce](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-1579) `nourboutique.com/winter-abaya`; App `abaya-winter` | Each page as drawn. Pick one |

### Claims to confirm before launch

| Task | In Figma |
|---|---|
| DS-06 (P1) | [Landing page](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=38-42): "Meta Business Partner", "TikTok Marketing Partner", 4.8/5 from 2,000+ businesses, 1.2M+ messages automated, $2M+ revenue recovered, and five named creators with follower counts and quotes. The API serves the same text, editable in the admin. If a claim isn't true yet, change it in Figma and in the admin |

---

## 💻 For the frontend developer

Everything is in `FRONTEND-TASKS.md` (FE-01–FE-26) and in the tracker. Most urgent first:

1. **FE-07 (P0):** "Couldn't go live" must come from the API's `422 {code}`. Delete `FREE_AUTOMATIONS = 3` in `src/pages/wizard/ReadyToLive.tsx:35`.
2. **FE-17:** the Inbox counts only the first 100 chats. Use `meta.total` and the cursor.
3. **FE-14:** contact edit sends only tags. **FE-26:** Subscribed and Language aren't shown.
4. **FE-25:** the copy that renders without the API still shows the jacket at $65. Use the abaya at ٢٤٩ ريال.
5. Keep the marketing 404 copy in `src/i18n/locales/en.json:2390` until the API serves it (BE-16).

When the designer adds the screens above (DS-07–DS-11), the endpoints for DS-07, DS-08 and DS-09 already exist.
