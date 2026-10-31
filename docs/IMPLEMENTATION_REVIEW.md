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

## Complete portal menu specification

Applied the supplied menu and submenu specification to the Vue portal. The committed catalogue at `resources/navigation.json` contains the exact 17 administration menus and 237 entries, arranged into the requested six sections, plus nine Supervisor menus with 35 entries and ten Agent menus with 36 entries. Counts reflect the complete specification; visible counts are filtered by the signed-in account's permissions and enabled modules.

The directory supports feature-name search and behaves as an accordion: the group that owns the page in use stays expanded, opening another group collapses the previous one, and deep links or module switches always reveal the relevant menu. It links to working configuration screens. Supervisor and Agent have separate routes, navigation and role permissions. Agent and Supervisor built-in roles now enter their assigned portals instead of inheriting administration read access. Administrators can access all three portals. Existing custom roles can receive `portal.admin`, `portal.supervisor` and `portal.agent` permissions as needed. An existing `contact-center.view` permission continues to grant administration access for compatibility.

Implemented entries connect to existing configuration, security or Horizon views. Entries for later-phase capabilities show a clearly labelled planned status page. The menu change does not implement live calls, monitoring, IVR execution, CRM or AI workflows. See `docs/FEATURE_COVERAGE.md` for backend dependencies and delivery gates. The pasted specification explicitly describes the custom Asterisk architecture; no live PBX provisioning or host changes were performed.

Module groups can be disabled with `DISABLED_MODULES` in `.env` followed by `php artisan config:clear`. For example, `DISABLED_MODULES=ai,campaigns,infrastructure` hides those groups. Disabling a group containing implemented resources also blocks the corresponding direct configuration API access. Shared links follow their canonical module's flag: extensions → `extensions`; agents, queues, skills and routing profiles → `contact-center`; users, roles and audits → `security`; settings, sites, teams, departments and business hours → `administration`; Horizon → `developer`. Account security remains available independently so authentication recovery is not hidden by a menu switch.

## Completion review and fixes

A final review of the permission-driven navigation and access foundation found and fixed four defects:

1. **Workspace redirects assumed administration.** The header brand linked to `/admin`, and the security page's post-enrollment buttons did the same. Agent-only and Supervisor-only accounts were sent into a route they cannot open. `Navigation::homeFor` now returns the first permitted workspace (or null), `HandleInertiaRequests` shares it as `home`, and the brand and security page follow it with a portal-specific label.
2. **Fortify's two-factor response bypassed that rule.** `App\Http\Responses\TwoFactorLoginResponse` is now bound to the Fortify contract, so finishing an MFA challenge lands in the same workspace as a normal login instead of `fortify.home`.
3. **Administration module selection could drift.** `Admin/Index.vue` copied `selectedModule` once at mount; a later Inertia visit (history navigation) changed the prop without updating the screen. A watcher now keeps the local module in sync.
4. **Search stopped at menu titles.** The specification calls for direct access to configuration. `/admin-api/search` and `App\Services\UnifiedSearch` now also look up implemented records, respecting tenant and site scope, module switches and view permissions, and return module links. The directory shows debounced record results alongside the existing menu filter, with planned items still labelled.

`EnsureEnabledModule` now applies only to known module-backed API paths, so the cross-module search endpoint is not disabled when a single module group is switched off. New feature tests cover the exact 17-menu/section mapping, shared workspace destination, two-factor destination, and search isolation across permissions, disabled modules and site restrictions.

Verification for this pass: 27 PHP tests with 444 assertions, two Playwright browser workflows (administration MFA/configuration plus record search, and the separate agent workspace) including accordion assertions on the menu group toggles, TypeScript checking with `vue-tsc --noEmit`, a production Vite build and PHP formatting. Live telephony, recording search, CRM and AI workflows remain later-phase work exactly as described above; no PBX, host or CRM changes were made.
