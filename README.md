# GWO SEVO Contact Center

Laravel 12 + Vue 3 + TypeScript + Inertia contact center application. Phases 1 and 2 provide authentication, administration and the configurable contact center domain. See the [implementation review](docs/IMPLEMENTATION_REVIEW.md) for scope and verification boundaries.

## Local development

Use PHP 8.3+ with PDO/MySQL, Redis and pcntl, Composer, Node 22, MySQL/MariaDB and Redis. CI targets PHP 8.4. For a fresh installation:

```sh
composer install
cp .env.example .env
php artisan key:generate
# Configure your database, APP_URL and Redis connection in .env.
php artisan migrate
php artisan contact-center:sync-roles
npm ci
npm run build
php artisan contact-center:check-infrastructure
composer run dev
```

For an existing installation, preserve `.env` and APP_KEY. Never regenerate the key on an installation holding encrypted MFA secrets. Use a host rooted at `public`, or the configured ServBay subdirectory URL. The portal is at `/login`, with administration at `/admin` and account security at `/security`.

`composer run dev` runs Laravel, Horizon, logs and Vite. Redis backs sessions, cache and jobs. Use a production process manager for Horizon rather than a development terminal. Reverb is installed for later real-time features and is not yet needed for configuration screens.

## Accounts and MFA

The requested Super Admin account has been provisioned in the local database. Use the credentials supplied separately in the task; passwords are never stored in `.env` or committed seeders. First login requires authenticator enrollment before administration. Save the generated recovery codes securely. Subsequent logins require an authenticator code or unused recovery code. A just-used authenticator code cannot be replayed; wait for the app's next code.

For another installation, bootstrap a new tenant interactively:

```sh
php artisan contact-center:user admin@example.com --new-tenant="Organization" --name="Administrator" --role="Super Admin"
```

For an account in an existing tenant:

```sh
php artisan contact-center:user agent@example.com --tenant=TENANT_ULID --name="Agent One" --role=Agent
```

Passwords require 12 characters, mixed case and numbers. The admin portal also supports account creation, role assignment, site restrictions, disabling accounts and revoking sessions. Disabled accounts cannot authenticate. Session revocation takes effect on the next request.

Super Admin and Administrator are built-in management templates. Other built-in roles start with read permissions. Create tenant-specific custom roles for specific duties. Shared built-in roles cannot be edited through the portal. A Super Admin's access remains within its tenant.

## Configure the contact center

1. Create sites, departments and teams.
2. Define skills and extensions.
3. Define business hours and routing profiles.
4. Create tenant user accounts, then agent profiles with skills and extensions.
5. Create queues and assign agents, required skills and routing profiles.
6. Review tenant settings and audit history.

Site-restricted users can only access assigned sites. Tenant-wide skills, hours and profiles are shared resources subject to permissions. Accounts, roles and audit administration require tenant-wide access. Routing configuration is stored for Phase 3 simulation; there is no live PBX provisioning yet.

## API boundaries

Same-origin endpoints at `/admin-api/{resource}` support collection GET/POST and individual GET/PUT/DELETE for `sites`, `departments`, `teams`, `skills`, `extensions`, `business-hours`, `routing-profiles`, `agents`, and `queues`. Collections paginate 50 records. PUT requires required scalar fields; omitted assignment arrays are retained and empty arrays remove assignments.

`/admin-api/users` lists account choices. `/admin-api/accounts`, `/admin-api/roles`, `/admin-api/settings` and `/admin-api/audits` support foundation administration. User records are disabled rather than deleted to preserve history. The API requires an authenticated session, CSRF on writes, MFA for privileged accounts and the relevant permission. It is not an external token API.

## Verification

```sh
php artisan test
vendor/bin/pint --test app routes database tests
npm run typecheck
npm run build
npm run test:e2e
php artisan contact-center:check-infrastructure
```

PHP feature tests use in-memory SQLite. Browser tests use only `storage/testing/browser.sqlite` with fictional credentials and generate ignored desktop/mobile screenshots for inspection. Install Chromium for a fresh environment with `npx playwright install chromium`. The fixture loader refuses non-testing environments and other databases. The tests do not enroll MFA on the real Super Admin account.

The GitHub Actions workflow validates PHP, Vue, MySQL migrations and Redis. It has no production credentials. The origin remote is `https://github.com/mobengasiedu-83/gwosevo-cx.git`.

## Development MFA switch

Local MFA is currently disabled with `MFA_ENABLED=false` in the ignored `.env`. This disables enrollment enforcement and sign-in challenges, including for already-enrolled accounts. Existing encrypted MFA secrets and recovery codes remain in the database.

To restore MFA, set `MFA_ENABLED=true` in `.env`, run `php artisan config:clear`, and restart any long-running application processes. New requests then require enrollment for privileged accounts and challenge enrolled accounts on their next login. The committed `.env.example` defaults to enabled; automated tests explicitly exercise the enabled configuration and the disabled switch. The earlier MFA enrollment descriptions above apply when enabled.
