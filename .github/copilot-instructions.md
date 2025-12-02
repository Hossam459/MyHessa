<!-- .github/copilot-instructions.md - guidance for AI coding agents -->
# Copilot / Agent Instructions — MyHessa (Laravel)

Purpose: help AI coding agents work productively in this Laravel application by documenting the repository's structure, workflows, and concrete examples.

Quick Setup
- **Install dependencies:** `composer install` then `npm install`.
- **One-step setup (recommended):** `composer run setup` (copies `.env`, generates app key, runs migrations, installs npm deps and builds assets — see `composer.json` -> `scripts.setup`).
- **Run dev environment:** `composer run dev` (runs several dev processes concurrently as defined in `composer.json` — `php artisan serve`, queue listener, `php artisan pail`, and `npm run dev`).

Running & Building
- Start the frontend dev server: `npm run dev` (Vite).
- Build production assets: `npm run build`.
- Serve the app standalone: `php artisan serve` (or use `composer run dev` for the full concurrent dev stack).

Tests
- Use the composer script: `composer run test` (this runs `php artisan test`).
- phpunit config is `phpunit.xml` — tests use an in-memory SQLite DB and `QUEUE_CONNECTION=sync`, so unit/feature tests shouldn't require external services by default.

Key Project Facts (discoverable)
- Framework: Laravel (see `composer.json`, `laravel/framework`), PHP >= 8.2.
- Autoload: PSR-4 mapping `App\` -> `app/` (add new classes under `app/` and run `composer dump-autoload` when needed).
- Routes: `routes/web.php` and `routes/api.php` (HTTP entry points). Controllers live in `app/Http/Controllers`.
- Middleware & auth: `app/Http/Middleware` and `app/Http/Controllers/Auth` contain auth/middleware patterns. Check `app/Http/Kernel.php` for middleware registration.
- Service providers: `app/Providers` (notably `RouteServiceProvider.php`) for route bootstrapping.
- Frontend: Vite + `laravel-vite-plugin` (`vite.config.js`, `resources/js`, `resources/css`). Tailwind is configured via `package.json` and devDependencies.
- Localization: `resources/lang/en` and `resources/lang/ar` (strings and validation translations live here).

Project-specific patterns and conventions
- Use `composer.json` scripts for higher-level workflows: `setup`, `dev`, `test`. Prefer these scripts over ad-hoc sequences so local environment matches expectations.
- Dev multitasking: `composer run dev` uses `npx concurrently` to run server, queue listener, pail (log streamer) and vite together. When inspecting issues, replicate parts individually (e.g., `php artisan queue:listen` or `npm run dev`).
- Tests expect sqlite in-memory. If creating integration tests that require a file DB, update `phpunit.xml` rather than relying on `.env` alone.
- Auth uses `tymon/jwt-auth` (see `composer.json` require). When adding auth flows, check `app/Http/Controllers/Auth` and `config/auth.php`.

Integration & external deps
- External deps are managed via Composer and npm. Key deps: `laravel/framework`, `tymon/jwt-auth`, `laravel-vite-plugin`, `vite`, `tailwindcss`.
- Background jobs: queues configured in `config/queue.php`; local dev runs `queue:listen` in dev script. For changes that touch queue behavior, test with `QUEUE_CONNECTION=sync` or run a worker locally.

Edit/Merge Guidance for AI
- When modifying routes, update `routes/*.php` and `app/Providers/RouteServiceProvider.php` if namespace/bootstrapping changes are required.
- When adding classes, follow PSR-4 and run `composer dump-autoload` if necessary.
- For localization changes, add keys to `resources/lang/{en,ar}/...` and keep translation keys consistent between languages.
- Modify config values in `config/*.php`. If you add a new env var, add it to `.env.example` (do not commit `.env`).

References (examples to inspect)
- `composer.json` (scripts: `setup`, `dev`, `test`) — authoritatively describes setups and dev orchestration.
- `phpunit.xml` — test environment (sqlite in-memory, queue sync).
- `routes/web.php`, `routes/api.php` — HTTP endpoints.
- `app/Http/Controllers`, `app/Http/Middleware`, `app/Providers` — application entry points and wiring.
- `vite.config.js`, `package.json` — frontend build/dev flow.

What I cannot assume
- Credentials, external service credentials, and `.env` contents are not present in the repo and must not be fabricated.

If unclear or incomplete
- Ask for the repository owner's preferred development commands, CI steps, or any private infra details (e.g., queue driver, 3rd-party APIs) before making changes that depend on them.

Please review and tell me which areas need more detail (CI, deployment, or example PR conventions) and I'll iterate.
