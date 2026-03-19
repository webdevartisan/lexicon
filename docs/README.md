# Lexicon - Developer Docs

> ⚠️ **Alpha - APIs, database schemas, and internal conventions are subject to
> breaking changes without notice.**

← [`Back to root README`](../README.md)

Internal developer documentation for **Lexicon** - use this as your map to
all subsystems, architecture decisions, and API references. Intended for
contributors and developers working on the project.

---

## Documentation

- **Getting started**
  - [`getting-started.md`](getting-started.md) - Installation,
    environment setup, and common scripts.

- **Architecture**
  - [`architecture.md`](architecture.md) - Framework/app/domain
    layers, request lifecycle, middleware, caching, and testing architecture.

- **Testing**
  - [`testing.md`](testing.md) - Test types, directory layout,
    commands, and coverage goals.

- **Database**
  - [`database/schema.md`](database/schema.md) - Main tables and their purpose.
  - [`database/relationships.md`](database/relationships.md) - How tables and models relate.
  - [`database/migrations.md`](database/migrations.md) - Unified migration and versioning.

- **API and modules**
  - [`api/cache.md`](api/cache.md) - Cache layers, keys, and invalidation patterns.
  - [`api/templates.md`](api/templates.md) - Custom template engine syntax and usage.
  - [`api/security-and-csrf.md`](api/security-and-csrf.md) - CSRF strategy for AJAX, TinyMCE, Dropzone.
  - [`api/config-and-env.md`](api/config-and-env.md) - Dotenv, `env()` helper, and configuration.
  - Additional API docs can be added under `docs/api/` as modules evolve.

- **Setup helpers**
  - [`setup/install-composer.md`](setup/install-composer.md) - Composer installation.
  - [`setup/dependencies.md`](setup/dependencies.md) - Notes on key project dependencies.

- **Scripts**
  - [`/scripts/README.md`](/scripts/README.md) - Deployment, maintenance, setup, and utility scripts.

---

## How to Use These Docs

1. Start with [`getting-started.md`](getting-started.md) to get a
   local environment running.
2. Read [`architecture.md`](architecture.md) to understand how the
   framework, application layer, and domain code fit together.
3. Use [`testing.md`](testing.md) and
   [`/scripts/README.md`](/scripts/README.md) when working on tests, CI, or
   local validation.
4. Consult `docs/database/*` when changing models, writing migrations, or
   debugging data issues.
5. Look under `docs/api/` when you need details on specific subsystems
   (templates, cache, security, configuration).

---

## Contributing

See [`docs/contributing.md`](docs/contributing.md) for the full contribution guide,
branch workflow, commit conventions, and code quality requirements.

---