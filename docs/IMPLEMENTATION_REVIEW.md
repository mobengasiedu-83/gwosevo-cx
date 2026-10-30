# Phase 1 and Phase 2 implementation review

Updated 30 October 2026 against the supplied Vue 3 implementation guide. Its final Vue architecture update supersedes the React references left in the earlier stack table and roadmap. Phases 1 and 2 are implemented for local development. Production rollout, live telephony and operational hardening remain later milestones.

## Phase 1 foundation

The application uses Laravel 12 with Vue 3, TypeScript, Inertia 2, Vite and Tailwind CSS. Inertia serves the login, MFA, security and administration pages; same-origin JSON endpoints handle configuration forms with session authentication and CSRF protection. This architecture does not require a separate SPA token or Sanctum for the current portal. The installed PHP 8.3 runtime supports the locked dependencies; CI targets the guide's recommended PHP 8.4 and Node 22.

The Git repository uses `main` with origin `https://github.com/mobengasiedu-83/gwosevo-cx.git`. `.env`, databases, test screenshots, logs and dependencies are ignored. GitHub Actions checks dependency installation, TypeScript, production assets, PHP formatting, tests, MySQL migration application and Redis round trips. GitHub Actions is configured to run on pushes and pull requests; its remote execution status is separate from the local verification results.

The configured MySQL database has both domain and foundation migrations applied. Redis now backs cache, sessions and jobs, with application-specific prefixes and cookie names. An infrastructure command verifies cache, session and job round trips. Horizon is installed and configured; `composer run dev` starts Horizon with the application and Vite. Long-running services still need a service manager for production. Reverb is installed with its configuration ready for the live events milestone; broadcasting credentials and live call subscriptions are not part of these phases.

Authentication uses Laravel Fortify with login throttling, CSRF protection, regenerated sessions, active-account checks, password confirmation, authenticator MFA and single-use recovery codes. Accounts with management permissions must complete MFA enrollment before accessing administration. Confirmed accounts receive an MFA challenge on subsequent logins. MFA codes cannot be replayed; users must wait for a fresh code if the previous code was just used. MFA secrets and recovery codes are encrypted in the database. User passwords use Laravel's hashed cast.

A Super Admin account was created for `techmgmt@gwosevo.com` in the GWO SEVO tenant using the password supplied by the user. No plaintext credential was placed in `.env`, seeders or source files. MFA is intentionally not pre-enrolled on this account: its owner must enroll their own authenticator on first login and save the recovery codes. The Super Admin has every application permission within its assigned tenant; it does not bypass tenant isolation.

Administration includes user creation and updates, role assignment, active-account controls, site assignments, session revocation, tenant settings, custom roles, explicit domain permissions and read-only audit history. Built-in roles are shared immutable templates through the portal. Custom roles are scoped to a tenant and can only receive permissions held by the administrator editing them. Only an existing Super Admin can grant the Super Admin role or edit another Super Admin. Self-disable and self-demotion protections preserve access. Authorization changes invalidate prior sessions using a database-backed version counter, including Redis sessions.

Audit history includes domain changes, account provisioning and updates, role changes, settings, session revocation, authentication successes and failures for known accounts, and MFA enrollment changes. Logs omit passwords, password hashes and MFA secrets. Domain, user, role and settings changes store their audit snapshots transactionally. Audit API records cannot be edited or deleted through the application.

## Phase 2 contact center domain

The portal provides configurable sites, departments, teams, agents, extensions, skills, business hours, routing profiles and queues. Domain records expose ULIDs. Existing user accounts retain numeric internal primary keys. Foreign keys, tenant-specific uniqueness and indexes protect record relationships and lookups.

Agents link tenant accounts to a site, optional team and optional unique extension. Skills carry proficiency from 1 to 5. Queues have prioritized agent memberships, required skills with minimum proficiency, and a routing profile. The Vue interface uses selectors and editable assignment rows rather than raw JSON. Departments, teams, extensions, agents and queues enforce consistent site assignments. Dependent records prevent unsafe site moves and deletion.

Users normally have tenant-wide access. A restricted user can only query records within assigned sites. Site and tenant administration is reserved for users with tenant-wide access. Skills, business hours and routing profiles are shared tenant configuration and still require their relevant permissions. Account, role and audit administration cannot be used by site-restricted operators. All resource queries and foreign references enforce tenant isolation at the server.

Business hours store an IANA timezone, weekly opening windows and date holidays. ISO weekdays run from Monday 1 to Sunday 7. Omitted days are closed. One opening window per day is currently supported; overnight windows must be split across days. Routing profiles store opening hours, strategy, ring timeout, maximum wait and overflow action. Routing execution, call events, IVR flows and PBX provisioning belong to later phases.

## Verification and remaining boundaries

Local checks cover domain CRUD and assignments, tenant and site boundaries, authentication, MFA enrollment and challenges, replay prevention, account permissions and escalation controls, password hashing and audit redaction, settings, session revocation and migration application. Browser tests use an isolated SQLite database and a fictional account, preserving the real Super Admin account and its MFA enrollment. TypeScript, Vue production compilation and PHP formatting are also checked.

Verification completed successfully: 13 PHP tests with 202 assertions; one full browser workflow covering MFA, sites, skills, hours, routing profiles, extensions, agents, queue membership, settings and sign-in; TypeScript checks; production build; PHP formatting; applied MySQL migrations; Redis round trips; and Horizon startup/status/clean shutdown. Desktop and mobile screenshots were inspected.

The project is ready to continue with Phase 3 mock telephony. This is not a production release sign-off: public TLS, a dedicated production host, supervised services, restore-tested backups, external API authentication where needed, deployment secrets, monitoring, load testing and live Asterisk integration remain deployment or later-phase work. Local development retains APP_DEBUG for troubleshooting. The provided credentials and organization data are not included in the test database or CI configuration.

## Development MFA switch

Local MFA is currently disabled with `MFA_ENABLED=false` in the ignored `.env`. This disables enrollment enforcement and sign-in challenges, including for already-enrolled accounts. Existing encrypted MFA secrets and recovery codes remain in the database.

To restore MFA, set `MFA_ENABLED=true` in `.env`, run `php artisan config:clear`, and restart any long-running application processes. New requests then require enrollment for privileged accounts and challenge enrolled accounts on their next login. The committed `.env.example` defaults to enabled; automated tests explicitly exercise the enabled configuration and the disabled switch. The earlier MFA enrollment descriptions above apply when enabled.
