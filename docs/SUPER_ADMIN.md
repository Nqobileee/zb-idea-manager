# Super admin portal

A separate control room at **`/super`** for the person running the app. It shows everything and lets you make members executive admins or remove them.

## Sign-in
Its own login at `/super/login`. The details are not a row in `users`; they live in the private environment:

| Variable | Purpose |
|---|---|
| `IDEAS_SUPER_EMAIL` | Super admin email |
| `IDEAS_SUPER_PASSWORD` | Super admin password |

If either is empty the portal is switched off (404). Five wrong tries from one address lock it for 10 minutes. Being an Executive admin on the main app does **not** open it. Change the password by changing the variable.

## What it shows
- **Overview:** members, executive admins, WhatsApp-linked members, ideas (public and private), approved, challenges, comments, likes, ideas by stage and the newest ideas.
- **Members:** everyone, with email, phone, role and idea count. Search by name, email or number.
- **Ideas:** every idea, **including private ones**, with author, stage, who can see it and likes.
- **Challenges:** every challenge with who set it, the closing date and idea count.

## What you can do
- **Make admin / Remove admin** on any member (asks to confirm).
- **Remove** a member. Their ideas, comments and likes are deleted with them and it cannot be undone (asks to confirm).
- **Remove** an idea.

Every action checks the super admin session again on the server, so a stale or copied page cannot be used after signing out.
