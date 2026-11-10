# Frontend tasks: round 5

| | |
|---|---|
| **Backend** | `blexchat` `master` @ `8f24319` |
| **API base** | `/api/v1`, Sanctum bearer token |
| **Earlier rounds** | `FRONTEND-TASKS.md` (FE-01–FE-37; FE-08, FE-17 and FE-26 are still open there) |

**Status:** this round adds **7 tasks** (FE-38 to FE-44). Every endpoint is in Swagger, with its fields and error codes.

**Deploy:** the work under "What changed" is on `master`. Workspaces (FE-41) goes live with the next deploy.

---

## What changed in the backend

| Area | What |
|---|---|
| Arabic and English | Every API message, validation error, inbox system message and email comes in the language the request asks for. The order is `?lang=ar\|en`, then `Accept-Language`, then English. Arabic emails are right to left. |
| The user's language | The last language a signed-in user's frontend asked for is saved. Reminder emails and Blexchat AI's handover reason use it, even when nobody is online. |
| Facebook comments | Comments on a connected Facebook Page arrive through the webhook. Comment automations answer them publicly and privately (Messenger), the same way they answer on Instagram. |
| Connect a channel yourself | OAuth for Messenger (Facebook Login), WhatsApp (Embedded Signup) and TikTok (Login Kit). The code is ready, but it can't be tried live until the Meta and TikTok apps are approved. |
| Workspaces (BE-11) | One owner can run several businesses (agencies). Each one has its own channels, automations, folders, contacts and team. There is one plan for all of them. |

---

## 🟠 Tasks

### FE-38 · Send the app's language on every request
- Send `Accept-Language: ar` or `en`, matching the language picked in the UI, on **every** API request. `?lang=` works too.
- **Done when:** switching the language changes API errors and toasts with no reload, and reminder emails arrive in the language last used.
- **Remove:** any frontend-side translation of API `message` strings. Show `message` as it comes.

### FE-39 · Arabic layout for API text
- API messages in Arabic can mix in names such as `Instagram` and `:platform`. Render them with `dir="auto"`, or inside the RTL layout.
- Inbox system messages ("Blexchat AI handed this over…") now come in the reader's language. Don't hard-code them.

### FE-40 · Facebook Page comments
- Facebook comments can now trigger automations. Comment automations on a Messenger channel are allowed in the automation wizard; don't hide them for Messenger.
- In the inbox, a conversation that a Facebook private reply started looks the same as one from Instagram. Nothing new is needed beyond checking that it renders.
- TikTok comments still work only in local or test mode. A real TikTok reply answers `502` with a message. Show that message.

### FE-41 · Workspace switcher (BE-11)
- `GET /workspaces` → `data: [{id, name, channels_count, current}]` and `meta: {used, limit}`.
- Send the chosen workspace on **every** request as the header `X-Workspace: {id}`. Keep it in local storage.
  - Without the header, the API uses the owner's first workspace, so the current app keeps working as it is.
  - When the header names a workspace that isn't the user's, the API answers `404 code: workspace_not_found`. Drop the stored id and fall back to the first workspace.
- Switching workspaces refetches everything: channels, automations, folders, contacts, inbox, dashboard and stats.
- **Add:** `POST /workspaces` {name}. At the plan's limit it answers `403 code: workspace_limit` (with `limit`), so show Upgrade. Only Advanced has more than one workspace (10).
- **Rename:** `PATCH /workspaces/{id}` {name}.
- **Delete:** `DELETE /workspaces/{id}`. This deletes everything in the workspace, so ask for a strong confirmation. The last workspace answers `409 code: last_workspace`.
- Contact and seat limits count **all** of the owner's workspaces together (`meta.usage` and `seats` already reflect that).

### FE-42 · Team per workspace
- Invitations go to the workspace currently selected (the `X-Workspace` header on `POST /team/invitations`). `GET /team` lists the teammates of the selected workspace.
- A teammate (`role: agent`) belongs to one workspace only, so hide the switcher for them. If they send another workspace's id, they get `404`.

### FE-43 · Connect a channel with OAuth (wire it up now, try it live later)
- `GET /channels/oauth/status` → `data: {driver, providers: {messenger|whatsapp|tiktok: {configured}}}`. Show the Connect button only when `configured` is true. Otherwise keep the current manual or request flow.
- `GET /channels/oauth/{platform}/start` → `data: {authorization_url, state}`. Send the browser to `authorization_url`.
  - When OAuth isn't set up on the server it answers `501`. Show `message`.
- After login, the provider returns to the backend, which redirects to `CHANNEL_OAUTH_REDIRECT_URL?platform=…&id={channel id}`. Build that page on Vercel: it reads the query, refetches channels, and shows "Connected".
- **Several Facebook Pages:** the backend keeps the list for 10 minutes. `GET /channels/oauth/messenger/pages` returns it, and `POST /channels/oauth/messenger/pages` {state, page_id} picks one. ⚠️ This step is still being finished on the backend and is turned off on production, so build the screen but keep it behind a flag.
- **Errors:**
  - `400`: the session expired; start again.
  - `409`: the account is already connected to another workspace.
  - `422`: the provider didn't return an account.

### FE-44 · Locally: simulate instead of OAuth
- With `CHANNEL_OAUTH_DRIVER=fake` (local only), `start` returns an `authorization_url` that connects a fake channel straight away. Use it to build and test FE-43 before the Meta and TikTok apps are ready.

---

## Waiting on setup (no frontend work yet)
- Trying OAuth live (Meta and TikTok apps): the WhatsApp Embedded Signup response, choosing a Messenger page, TikTok scopes, and the real redirect to Vercel.
- Real TikTok messages and comments: waiting for TikTok's approval.
