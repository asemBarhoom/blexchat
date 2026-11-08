# Blexchat frontend API guide

The API is rooted at `/api/v1`. Requests that belong to an account use a Sanctum bearer token:

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

Real OAuth requires provider applications and redirect configuration. The local fake driver exercises the same callback and persistence paths without provider credentials. Messenger uses Facebook Login and page selection, WhatsApp uses Embedded Signup, and TikTok uses TikTok Login.

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

Webhook requests are verified by the server and queued for processing. The fake channel test events are the recommended local smoke test; no real Meta or TikTok secret should be placed in source files.

## Local verification

Run the API tests with:

```bash
php artisan test --compact tests/Feature/Api
php artisan l5-swagger:generate --no-interaction
```

The API guide describes the Laravel backend contract. The frontend should implement loading states, pagination/cursor continuation, `401` sign-in recovery, `403` plan/role errors, and the documented validation `422` payloads.
