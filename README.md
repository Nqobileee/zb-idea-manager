# ZB Idea Manager

Where verified ZB staff post ideas, answer executive challenges and follow ideas from sketch to launch. Executives rank and approve the best ones. Works on the web, as an Android app and through WhatsApp.

**Stack:** PHP 8.3 / Laravel 13, Livewire 4 (Blade, Alpine, Tailwind), Laravel Reverb for realtime, MySQL. The Android app is a Capacitor wrapper around the same web app.

- Full documentation, diagrams and setup: [docs/DOCUMENTATION.md](docs/DOCUMENTATION.md)
- The original single-file prototype is in [prototype/](prototype/)

## Quick start

```bash
composer install
cp .env.example .env && php artisan key:generate
# set DB_* in .env and IDEAS_ACCEPT_ANY_CODE=true for a demo
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
php artisan serve
```

Sign in as `tinashe.moyo@zb.co.zw` (employee) or `tapiwa.dube@zb.co.zw` (executive). In demo mode any 6 digits work.

Realtime: `php artisan reverb:start`. Tests: `php artisan test`. Android: `npm run android:open` (see the docs).
