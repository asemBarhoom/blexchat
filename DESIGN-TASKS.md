# Design tasks

| | |
|---|---|
| **Figma** | [blexchat (Copy)](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-), page **App** |
| **Frontend** | `blexchat-website` `origin/main` @ `a2473f2` |

The **Website** page needs nothing: every text matches the live API.

---

## 1. Draw what the frontend built without a frame

PR #6 added these to the app. Figma has no frame for them, so the frontend made its own. Draw them, so the frontend has something to match.

| # | Feature | Where it starts in Figma | Built in |
|---|---|---|---|
| D-1 | **Remind me:** picking a date and time, the reminder shown on the chat, and clearing it. On Free it is refused with an upgrade | The "Reminders" folder in the [Inbox](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087) | `src/pages/app/inbox/Inbox.tsx:335` |
| D-2 | **Labels:** adding and removing a label on a chat (up to 10, each up to 30 characters) | The "Labels" folder in the [Inbox](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087) | `src/pages/app/inbox/Inbox.tsx:286` |
| D-3 | **Contact edit:** the Details block in edit mode (name, phone, email, city, gender), with save and error states | [Contact detail](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4433) | `src/pages/app/contacts/ContactDetail.tsx:122` |
| D-4 | **Blexchat AI switch** on each automation, and its "needs a paid plan" state | [Blexchat AI](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6308) | `src/pages/app/ai/AiLanding.tsx:160` |
| D-5 | **Folders:** moving an automation to a folder, renaming a folder, and deleting one (its automations go to no folder) | "+ Create folder" in [Automations](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4564) | Not built yet (frontend FE-08) |

---

## 2. One decision

| # | Question |
|---|---|
| D-6 | **Help center.** Pricing gives the Free plan "Help center" ([Pricing · compare](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-821)), but in the app it's only a [preview note](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6414). Either draw a help center, or change the pricing cell to "Email" (the backend changes the text) |
