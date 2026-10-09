# Zernio chatbot endpoints

The Zernio "ZBIF Registration Chatbot" runs the WhatsApp conversation and calls this app for data. Code: `app/Http/Controllers/ZernioController.php`, routes in `routes/web.php`, tests in `tests/Feature/ZernioTest.php`.

## Setup

1. Set `ZERNIO_SECRET` in the server environment to a long random string.
2. In every Zernio webhook node use the base URL `https://<your-domain>/api/zernio` and send the header `X-Zernio-Secret: <the same string>`.
3. Without the secret set, every endpoint answers `503`. A wrong or missing header answers `401`.

All endpoints are `POST`. Zernio sends the contact and the chat variables as JSON; the body can be empty of anything else.

## Who is calling

Each request is matched to a person by **email** (`contact.fields.email`, or the `email` / `imEmail` variable), then by **WhatsApp number** (`contact.phone` or `phone`). Variables are read from the top level of the JSON, or from `variables`, `vars`, `data`, `contact.fields` and `contact`, so the exact Zernio layout does not matter. Unknown people get `404` with `ok: false`; call `/link` first.

## Replies

Every reply is JSON: `{ "ok": true|false, "message": "<WhatsApp text>", ...extra }`. `message` is at most 4,000 characters. Errors the person can fix (an over-long title, a number not in the list) come back with `ok: false` and a `message` to show. Executive endpoints answer `403` to everyone else, checked on the server.

## Endpoints

| Path | Reads | Returns besides `message` |
|---|---|---|
| `/link` | `contact.phone`, `email`, `name`, `category`, `password` | `user_id`, `first_name`, `is_executive`, `greeting`, `greeted_today`. Finds the account by WhatsApp number; creates it if new (tag **Hub member**). `greeting` is a time-aware hello (Africa/Harare) on the first call of the day, `""` after that. |
| `/web-link` | | One-use sign-in link (10 minutes) in `message` |
| `/notifications` | `menuReply` (`stop` or `start notifications`) | |
| `/challenges/options` | | Numbered open challenges with `0. None` first; remembers the order for `options:N` |
| `/challenges` | | Numbered open challenges with deadlines; remembers `list:N` |
| `/challenges/detail` | `chRef` (`list:2`) | `challenge_id`, `is_executive`; full text: who set it, closing date, idea count, the brief, and `View on the web: <link>` as the last line |
| `/challenges/ideas` | `chDetail.body.challenge_id` | **Executives.** Up to 10 ideas by likes, plus a web link when there are more; remembers `chideas:N` |
| `/ideas/preview` | `ideaTitle`, `ideaSummary`, `ideaDetails`, `ideaChallengeRef`, `ideaVisibility` | `ok: false` if title > 90 or summary > 300; otherwise the "Ready to post?" text |
| `/ideas` | same as preview | `idea_id`, `code`. Saved with source `whatsapp`. |
| `/ideas/mine` | | Numbered latest 8 ideas with stage, likes, comments, Public/Private; remembers `mine:N` |
| `/ideas/top` | | Top 5 the person is allowed to see; remembers `top:N` |
| `/ideas/top5` | | **Executives.** Top 5 by score; remembers `top5:N` |
| `/ideas/detail` | `ideaRef` | `idea_id`, `approved`, `is_executive`; full text (header, Summary, Details trimmed under 4,000 characters) ending with `View on the web: <link>` |
| `/ideas/like` | `ideaRef` | `liked` (true/false); toggles |
| `/ideas/approve` | `ideaRef`, `approveNote` (`Skip` = no note) | **Executives.** Approves and notifies the author |
| `/pipeline` | | Ideas grouped by stage the person can see, one running number, up to 3 per stage; remembers `pipeline:N`; ends with `Full pipeline: <link>` when some are hidden |
| `/notifications/list` | | `enabled`; whether alerts are on and the 5 latest activity items with dates |
| `/account/check` | `regEmail` | `exists`, and `first_name` when found. Nothing else is revealed. |
| `/account/login` | `regEmail`, `regPassword`, `contact.phone` | `ok`, `full_name`, `role` (`executive` or `general`). Checks the hash, attaches the number if missing. 5 wrong tries lock that email+number for 15 minutes (`locked: true`). |
| `/account/register` | `regName`, `regEmail`, `regPassword` (8+), `regRole`, `regExecCode` (Executive admin only), `contact.phone` | `ok`, `role`. Same email and hashed password work on the web sign-in page. |
| `/reports` | `reportChoice` | **Executives.** `Programme summary` (default), `Top 10 by likes`, `Awaiting approval`, `Ideas by stage`, `By challenge` |

## References

The number a person types comes back as a reference:

| Reference | Meaning |
|---|---|
| `top:3`, `top5:2`, `chideas:4`, `list:2`, `options:1`, `mine:2`, `pipeline:5` | Position in the last list of that name shown to this person. The order is cached for 30 minutes per person, so call the list endpoint first. |
| `id:42` | The record with id 42 |
| `0` (for `ideaChallengeRef`) | No challenge |

Ideas are resolved with the same visibility rules as the web app: executives see everything, everyone else sees public ideas, their own, and projects they are tagged on.

## Notes

- `ideaVisibility` is **private** unless the text contains "public" (case-insensitive).
- Drafts live in the Zernio chat variables, not here, so there is no server-side draft to resume.
- The older in-repo bot (`WhatsappBot`, `/webhooks/whatsapp`) still works and is independent of these endpoints. Use one or the other for a given number.

## Sign-in and registration

- **Email and password.** The password made in the chatbot is stored hashed and is the same one the web sign-in page checks.
- **Executive admin needs a code.** `/account/register` with `regRole` = `Executive admin` also needs `regExecCode`, which must equal `IDEAS_EXECUTIVE_CODE` in the server environment. With no code, or a wrong one, the reply is `ok: false` and `code_required: true` so the bot can ask again. Five wrong tries lock that email and number for 15 minutes (`locked: true`). If `IDEAS_EXECUTIVE_CODE` is empty, Executive admin registration is closed and people can only register as General.
- **Passwords.** Request bodies are never logged, plain passwords are never stored, and `regPassword` is excluded from error reports.
- **Identity.** Accounts are found by the WhatsApp number Meta verified. An email typed in chat can never take over an existing account: `/link` and `/account/register` refuse an email that already exists.
- **Not built.** There is no "forgot password" page yet, so wrong-password replies link to the web sign-in page.
- **Migrations to run:** `wa_greeted_on` on users (`php artisan migrate --force`). Until it runs, the greeting is empty.

## Workflow change: the Executive admin code step (v3)

Paste this to whoever edits the Zernio workflow. The real code is **not** written here; it lives only in `IDEAS_EXECUTIVE_CODE` on the server, and whoever sets it shares it with executives directly.

After the person chooses **Executive admin** as their role:

1. Ask: `Please send the executive access code.`
2. Save the reply in the variable `regExecCode`.
3. Call `POST /api/zernio/account/register` with the usual fields plus `regExecCode`:
   `regName`, `regEmail`, `regPassword`, `regRole` (`Executive admin`), `regExecCode`, and the contact's phone.
4. Read the reply:

| Reply | What the bot does |
|---|---|
| `ok: true`, `role: executive` | Send `message` as is. The person is an Executive admin. |
| `ok: false`, `code_required: true` | Send `message` (for example "That executive access code is not right."), ask for the code again, and call register again. |
| `ok: false`, `locked: true` | Send `message`. Five wrong codes lock that email and number for 15 minutes. Offer **General Member** instead. |
| `ok: false` (anything else) | Send `message` and go back to the email question. |

5. Clear `regPassword` and `regExecCode` straight after the call so they are not sent anywhere else.

If `IDEAS_EXECUTIVE_CODE` is empty on the server, Executive admin registration is closed: the reply is `ok: false` without `code_required`, and the bot should offer **General Member**.

**Sending the secret headers.** Every Zernio node needs the header `X-Zernio-Secret` with the value of `ZERNIO_SECRET` from the server. Keep both values in Zernio's secret or variable store, not in this file or in chat.

## Update v4: switching accounts from WhatsApp

A person who has several accounts (for example a General member and an Executive admin) can swap by typing the **switch code** in the chat. The code is in the server environment as `IDEAS_SWITCH_CODE`; it is not written in this file. Typing it signs the number out of the current account and restarts sign-in, so the next email and password decide which account the number belongs to.

### Workflow change
1. At the very top of the flow, before any other condition, check whether the incoming message equals the switch code (case does not matter). Easiest is to send every message that could be the code to the endpoint and branch on `restart`.
2. Call `POST /api/zernio/account/switch` with the usual contact phone and the message text as `switchCode`.
3. Read the reply:

| Reply | What the bot does |
|---|---|
| `ok: true`, `restart: true` | Send `message` ("Switched … What is your email address?") and **jump to the email question** of the sign-in flow. Clear `regEmail`, `regPassword`, `regExecCode` and any other `reg*` variables. |
| `ok: false` | The text was not the code. Carry on with the normal flow (do not show `message`). |
| `ok: false`, `locked: true` | Five wrong tries from this number; ask them to wait 15 minutes. |

### What the server does
- Compares the text with `IDEAS_SWITCH_CODE` (trimmed, not case sensitive). If the setting is empty, switching is off.
- Removes the WhatsApp number from whichever account holds it. Nothing else about that account changes.
- Five wrong codes from one number lock it for 15 minutes.

### Signing in moves the number
`/account/login` with a correct password now **always attaches the number to that account**, taking it off any other account. Before, it only attached to an account that had no number, which would have left an account with an older number unreachable. A wrong password changes nothing.

### Notes
- The code only signs *your own* number out. It cannot open anyone's account; the other account still needs its email and password.
- Treat it like any shared code: give it only to people who need to switch, and change `IDEAS_SWITCH_CODE` if it leaks.
