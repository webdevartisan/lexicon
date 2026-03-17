## Architecture Overview

Lexicon is a Laravel‑inspired MVC framework with clear separation between the core framework, the application layer, and feature‑specific domain code.

---

## Layered Design

- **Framework layer** (`Framework\*`)
  - `BaseController`: HTTP and response handling primitives
  - `Model`: database access wrapper and transaction helpers
  - Core services: `Database`, `Session`, `Router`, `Request`, `TemplateRenderer`, `CacheService`, `Csrf`, DI `Container`
- **Application layer** (`App\AppController`, `App\AppModel`, services)
  - `AppController`: blog‑specific helpers (auth, flash, error handling)
  - `AppModel`: blog‑specific database helpers (e.g. audit integration)
  - Services: `UserDeletionService`, `BlogDeletionService`, `ThemeService`, `MailService`, `CacheManagementService`, `NavigationService`, `ProfileService`, etc.
- **Domain layer** (`App\Controllers`, `App\Models`, `App\Policies`)
  - Controllers: thin HTTP controllers per area (Auth, Dashboard, Admin, Public)
  - Models: one per table (`UserModel`, `BlogModel`, `PostModel`, `CommentModel`, etc.)
  - Policies: `BlogPolicy`, `PostPolicy`, `UserPolicy` for authorization logic

This layering keeps business logic out of controllers, SQL out of services, and makes policies the single source of truth for access rules.

---

## Request Lifecycle

1. **HTTP request enters** at `public/index.php`.
2. **Pre‑routing pipeline** (`App\Http\PreRouting\*`) runs fast, stateless steps:
   - `HttpsRedirector`, `SubdomainNormalizer`, `MaintenanceModeGate`
   - `UserAgentBlocklist`, `HealthCheckBypass`
   - `CanonicalQueryKeys`, `PathCanonicalization`, `TrailingSlashNormalizer`
   - `CacheControlHint`, `LocaleAwareStaticBypass`, `LocalePrefixIntake`
3. **Router** (`Framework\Core\Router`) matches the route and resolves:
   - Controller class (e.g. `App\Controllers\Dashboard\PostController`)
   - Action method
   - Route parameters and middleware stack
4. **Middleware pipeline** runs (framework and app middleware):
   - `AuthMiddleware`, `RequireRoleMiddleware`, `SecurityHeadersMiddleware`
   - `ThemeResolverMiddleware`, breadcrumb/nav locale middleware, etc.
5. **Controller action** executes:
   - Validates request data
   - Delegates to services and models
   - Uses policies for authorization
   - Returns a `Response` (HTML, JSON, redirect)
6. **View rendering and caching**:
   - `TemplateRenderer` compiles `.lex.php` templates
   - `CacheMiddleware` and `FragmentCache` apply page/fragment caching

---

## Data and Models

All application models live under `App\Models` and extend `AppModel`, which itself extends the framework `Model`.

- Models receive a `Database` instance via dependency injection.
- All database access goes through the `Database` wrapper, not raw PDO.
- Nullable fetch methods return `?array` rather than `array|false`.
- Multi‑table write workflows belong in **services**, not in models.

Key models include:

- `UserModel`, `UserProfileModel`, `UserPreferencesModel`, `UserSocialLinkModel`
- `BlogModel`, `BlogSettingsModel`
- `PostModel`, `CommentModel`
- `CategoryModel`, `TagModel`, `PermissionModel`, `RoleModel`, `ReservedSlugModel`

See `docs/database/relationships.md` for how these map onto the schema.

---

## Services and Business Logic

Services encapsulate multi‑step workflows and cross‑model orchestration while delegating all SQL to models. Examples:

- `UserDeletionService`: pseudonymization and GDPR‑style deletion across user, profile, social links, preferences, and uploads.
- `BlogDeletionService`: blog and related content teardown (posts, comments, blog settings, collaborators).
- `CacheManagementService`: clearing and warming the HTTP/cache layer.
- `PasswordResetRateLimiter`, `LoginRateLimiter`: throttling for sensitive flows.

Services are typically registered as shared instances (singletons) in the container and injected into controllers.

---

## Authorization and Policies

Authorization uses a Gate + Policy pattern:

- **Roles and permissions** are stored in the `roles` and `permissions` tables.
- The `blog_users` pivot table links collaborators to blogs with role information.
- Policies (`App\Policies\BlogPolicy`, `PostPolicy`, `UserPolicy`) encapsulate:
  - `view`, `create`, `update`, `delete` actions
  - Resource‑specific operations (e.g. `manageUsers`, `publish`, `review`)

Controllers call the Gate or policies instead of inlining role checks. This keeps access rules centralized and testable.

---

## Caching Architecture

The caching system has three layers:

- **Browser cache** via HTTP headers on static assets and HTML responses.
- **Page cache** via `CacheMiddleware` and `CacheService` (per‑URL HTML caching).
- **Fragment cache** via `FragmentCache` and `{% cache %}` blocks in templates.

`CacheService` uses a file‑based store with an index file for fast pattern invalidation (e.g. `cache()->deletePattern('*:GET:/blogs*')`).

See `docs/api/cache.md` for details on usage and key patterns.

---

## Middleware and HTTP Pipeline

Middleware is responsible for cross‑cutting concerns:

- **Authentication**: `AuthMiddleware` ensures a user is signed in.
- **Role/permission checks**: `RequireRoleMiddleware` and policies.
- **Security headers**: `SecurityHeadersMiddleware` (e.g. CSP, X‑Frame‑Options).
- **Localization and UI**: navigation and breadcrumb middlewares, translation globals.

Pre‑routing classes (`App\Http\PreRouting\*`) handle extremely early, cheap checks and URL normalization before routing.

---

## Testing Architecture

Tests are organized into three main suites:

- **Unit tests** (`tests/Unit`):
  - No real database or HTTP.
  - Use Mockery, factories, and datasets.
- **Integration tests** (`tests/Integration`):
  - Use the real database via `Framework\Database`.
  - Exercise models and services end‑to‑end.
- **Feature tests** (`tests/Feature`):
  - Drive full HTTP workflows (e.g. login, post publishing).

See `docs/testing.md` for commands, patterns, and coverage goals.

