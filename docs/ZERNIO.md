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
| `/link` | `email`, `phone`, `contact.name` (or `name`), `category` | `user_id`, `first_name`, `is_executive`. Creates the account if new (tag **Hub member**, or Employee when the category says employee) and attaches the number if missing. |
| `/web-link` | | One-use sign-in link (10 minutes) in `message` |
| `/notifications` | `menuReply` (`stop` or `start notifications`) | |
| `/challenges/options` | | Numbered open challenges with `0. None` first; remembers the order for `options:N` |
| `/challenges` | | Numbered open challenges with deadlines; remembers `list:N` |
| `/challenges/detail` | `chRef` (`list:2`) | `challenge_id`, `is_executive`; who set it, closing date, idea count |
| `/challenges/ideas` | `chDetail.body.challenge_id` | **Executives.** Up to 10 ideas by likes, plus a web link when there are more; remembers `chideas:N` |
| `/ideas/preview` | `ideaTitle`, `ideaSummary`, `ideaDetails`, `ideaChallengeRef`, `ideaVisibility` | `ok: false` if title > 90 or summary > 300; otherwise the "Ready to post?" text |
| `/ideas` | same as preview | `idea_id`, `code`. Saved with source `whatsapp`. |
| `/ideas/mine` | | The person's latest 8 ideas with stage, likes, comments, Public/Private |
| `/ideas/top` | | Top 5 the person is allowed to see; remembers `top:N` |
| `/ideas/top5` | | **Executives.** Top 5 by score; remembers `top5:N` |
| `/ideas/detail` | `ideaRef` | `idea_id`, `approved`, `is_executive`; author, stage, likes, comments, score |
| `/ideas/like` | `ideaRef` | `liked` (true/false); toggles |
| `/ideas/approve` | `ideaRef`, `approveNote` (`Skip` = no note) | **Executives.** Approves and notifies the author |
| `/reports` | `reportChoice` | **Executives.** `Programme summary` (default), `Top 10 by likes`, `Awaiting approval`, `Ideas by stage`, `By challenge` |

## References

The number a person types comes back as a reference:

| Reference | Meaning |
|---|---|
| `top:3`, `top5:2`, `chideas:4`, `list:2`, `options:1` | Position in the last list of that name shown to this person. The order is cached for 30 minutes per person, so call the list endpoint first. |
| `id:42` | The record with id 42 |
| `0` (for `ideaChallengeRef`) | No challenge |

Ideas are resolved with the same visibility rules as the web app: executives see everything, everyone else sees public ideas, their own, and projects they are tagged on.

## Notes

- `ideaVisibility` is **private** unless the text contains "public" (case-insensitive).
- Drafts live in the Zernio chat variables, not here, so there is no server-side draft to resume.
- The older in-repo bot (`WhatsappBot`, `/webhooks/whatsapp`) still works and is independent of these endpoints. Use one or the other for a given number.
