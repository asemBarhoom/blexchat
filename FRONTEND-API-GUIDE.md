# Blexchat frontend API guide

The API is rooted at `/api/v1`. Requests that belong to an account use a Sanctum bearer token:

Public support content is available at `GET /help`. It returns localized FAQ articles and the configured support email.

Message delivery failures return `code`, `platform`, and `retryable`. Retry only when `retryable` is true; `channel_not_live` and provider policy errors are permanent.

```http
Authorization: Bearer <token>
Accept: application/json
```

The API returns JSON in a `data` property. Paginated responses also return `meta`; cursor based lists expose `meta.next_cursor` and clients should request the next page with `?cursor=...` until it is `null`. The generated OpenAPI document is available at `/api/documentation` and is written to `storage/api-docs/api-docs.json` by `php artisan l5-swagger:generate`.

## Authentication and account setup

| Use | Endpoint | Notes |
| --- | --- | --- |
| Request a sign-in link | `POST /auth/login-link` | Development responses may expose a link only when `AUTH_EXPOSE_LOGIN_LINKS=true`. |
| Confirm a sign-in link | `POST /auth/confirm` | Returns the bearer token and user. |
| Current user | `GET /auth/me` | `role` is `owner` or `agent`. |
| Connect a channel | `POST /channels/connect` | Uses the selected channel driver's connection payload. |
| Test a local channel | `POST /channels/{id}/test-events` | Available with the fake driver; use `type=message` or `type=comment`. |

Real OAuth requires provider applications and redirect configuration. Start a connection with `GET /channels/oauth/{platform}/start`, then return to `GET /channels/oauth/{platform}/callback?state=...`. Messenger uses Facebook Login and page selection (`page_id`), WhatsApp uses Embedded Signup (`phone_number_id`), and TikTok uses TikTok Login (`open_id`). The local fake driver exercises the same callback and persistence paths without provider credentials.

## Inbox and automations

| Use | Endpoint |
| --- | --- |
| List conversations | `GET /conversations` |
| Filter inbox | `?status=all|open|resolved`, `?folder=reminders`, `?unread=1`, `?assigned=me|unassigned`, `?channel_id=...`, `?label=...`, `?state=...` |
| Continue a long list | `?cursor=<meta.next_cursor>` |
| Read messages | `GET /conversations/{id}/messages` |
| Update a conversation | `PATCH /conversations/{id}` with `read`, `status`, `labels`, `remind_at`, or `assigned_to` |
| List automations | `GET /automations?search=...&status=active&folder_id=...` |
| Create or edit an automation | `POST /automations`, `PATCH /automations/{id}` |
| Toggle an automation | `PATCH /automations/{id}/toggle` |
| AI switch | `PATCH /automations/{id}/ai` with `{ "enabled": true }` |
| Folders | `GET|POST /automation-folders`, `PATCH|DELETE /automation-folders/{id}` |

Conversation messages identify AI replies with `by_ai: true`. Handover messages include a `reason`. A due reminder is represented by `remind_due: true`.

`GET /automations` also returns `meta.live`: how many of the filtered automations are active ("3 live of 5"), whatever `status` asks for.

## Contacts, team, and integrations

| Use | Endpoint |
| --- | --- |
| Contacts | `GET /contacts`, `GET /contacts/{id}`, `PATCH /contacts/{id}` |
| CSV import | `POST /contacts/import` multipart form with `file` and `consent=true` |
| AI business settings | `GET|PATCH /ai/settings` |
| Team settings | `GET /team` |
| Invite a teammate | `POST /team/invitations` |
| Accept an invitation | `POST /team/invitations/accept` with `token` |
| Remove or leave team | `DELETE /team/members/{id}`, `POST /team/leave` |
| Webhooks | `POST /webhooks/whatsapp`, `POST /webhooks/messenger` |

`GET /team` returns `members`, `invitations`, `seats` (`used`, `limit`), `assignment` and `role`. `GET /ai/settings` returns `business_info`, `included` (the plan includes Blexchat AI) and `live` (the server has the AI engine configured); `PATCH` takes `business_info`.

Webhook requests are verified by the server and queued for processing. The fake channel test events are the recommended local smoke test; no real Meta or TikTok secret should be placed in source files.

Replying on TikTok returns `502` (`channel_not_live`) until TikTok opens its messaging API; WhatsApp and Messenger deliver for real once the channel is connected.

For Messenger and TikTok comment automations, send a fake `comment` event with `text`, `from`, and optional `post`. A matching `reply_comment` action is recorded and delivered through the fake messaging client.

## Marketing content

`GET /home` is public and returns every marketing block. `not_found` is the 404 page (`null` until it is written in the admin panel): `question`, `replied` and `answer` for the comment and its auto-reply, then `code`, `title`, `body` and `cta` for the page itself.

## Errors and limits

Errors return `message` and `code`; a `422` validation error also returns `errors`. Branch on `code`, not on the message.

| `code` | Status | When |
| --- | --- | --- |
| `missing_columns` | 422 | `POST /contacts/import`: the first row has no name or phone column; `columns` lists the missing ones |
| `seat_limit` | 403 | `POST /team/invitations` with every seat in use; `seats` is `{used, limit}` (show Upgrade) |
| `invitation_invalid` | 404 | `POST /team/invitations/accept`: expired, withdrawn or unknown token |
| `other_email` | 403 | Accepting while signed in with another email; `email` names the invited address |
| `has_own_account` | 403 | The account already has channels or a team of its own |
| `owner_only` | 403 | A teammate (`role: agent`) calling anything outside the inbox, contacts and their own team entry |
| `plan_required` | 403 | Reminders, conversation assignment, or Blexchat AI below the plan that includes them |
| `channel_not_live` | 502 | The channel cannot send yet (TikTok) or is disconnected; permanent |

## Local verification

Run the API tests with:

```bash
php artisan test --compact tests/Feature/Api
php artisan l5-swagger:generate --no-interaction
```

The API guide describes the Laravel backend contract. The frontend should implement loading states, pagination/cursor continuation, `401` sign-in recovery, `403` plan/role errors, and the documented validation `422` payloads.
