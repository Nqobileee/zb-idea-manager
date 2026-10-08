# ZB Idea Manager

Where verified ZB staff post ideas, answer executive challenges and follow ideas from sketch to launch. Executives rank and approve the best ones. Works on the web, as an Android app and through WhatsApp.

**Stack:** PHP 8.3 / Laravel 13, Livewire 4 (Blade, Alpine, Tailwind), Laravel Reverb for realtime, Supabase (Postgres, and optionally Storage). The Android app is a Capacitor wrapper around the same web app.

- Full documentation, diagrams and setup: [docs/DOCUMENTATION.md](docs/DOCUMENTATION.md)
- The original single-file prototype is in [prototype/](prototype/)

## Quick start

```bash
composer install
cp .env.example .env && php artisan key:generate
# paste POSTGRES_URL_NON_POOLING (or POSTGRES_URL) from Supabase into .env, and set IDEAS_ACCEPT_ANY_CODE=true for a demo
php artisan migrate        # empty tables, no sample data
php artisan storage:link
npm install && npm run build
php artisan serve
```

The database starts empty. Sign in with any email address and choose Employee or Executive admin (temporary). In demo mode any 6 digits work. An empty Postgres template for the Supabase SQL editor is in `database/schema/zb_ideas.postgres.sql`.

Realtime: `php artisan reverb:start`. Tests: `php artisan test`. Android: `npm run android:open` (see the docs).
