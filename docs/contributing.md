# Contributing to Lexicon

> ⚠️ Lexicon is in **alpha**. APIs, schemas, and conventions may change without notice.
> Read this guide fully before opening a PR.

← [`Back to Developer Docs`](README.md)

---

## Before You Start

- **Open an issue or discussion first** for any large structural change, new feature, or refactor.
- **Check [`docs/README.md`](README.md)** to understand the subsystem you're working on.
- **Do not assume API stability** - always check the relevant `docs/api/` file before building on top of any subsystem.

---

## Workflow

Lexicon uses a **branch + Pull Request** model. Direct pushes to `main` are not allowed.

### Feature or Enhancement

```bash
git checkout -b feature/your-feature-name
# make your changes
git push origin feature/your-feature-name
```


### Bug Fix

```bash
git checkout -b fix/short-description
# make your changes
git push origin fix/short-description
```


### Chore / Refactor / Docs

```bash
git checkout -b chore/short-description
git checkout -b refactor/short-description
git checkout -b docs/short-description
```

Then open a **Pull Request** targeting `main` on GitHub. CI will run automatically.

---

## Branch Naming

| Type | Pattern | Example |
| :-- | :-- | :-- |
| Feature | `feature/` | `feature/tag-filtering` |
| Bug fix | `fix/` | `fix/login-redirect-loop` |
| Refactor | `refactor/` | `refactor/auth-service` |
| Chore | `chore/` | `chore/update-dependencies` |
| Docs | `docs/` | `docs/update-api-cache` |


---

## Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>: <short description>
```

| Type | When to use |
| :-- | :-- |
| `feat` | New feature |
| `fix` | Bug fix or type-safety correction |
| `refactor` | Code change with no behaviour change |
| `chore` | Tooling, dependencies, config |
| `docs` | Documentation only |
| `style` | Formatting, whitespace (no logic change) |
| `test` | Adding or updating tests |

**Examples:**

```
feat: add tag filtering to post index
fix: cast selectedBlogId to int before setDefaultBlogId call
refactor: extract consent logic into dedicated service
chore: add captainhook with pint and phpstan pre-push hooks
docs: update api/cache.md with invalidation patterns
```


---

## Code Quality

All contributions must pass the following checks, which run automatically on `git push` via CaptainHook:

```bash
# Code style (auto-fixable)
php vendor/bin/pint

# Static analysis
php vendor/bin/phpstan analyse --memory-limit=2G
```

Run them locally before pushing:

```bash
php vendor/bin/pint --test   # dry-run, shows what would change
php vendor/bin/phpstan analyse --memory-limit=2G
```


---

## Pull Request Checklist

Before opening a PR, make sure:

- [ ] Branch is up to date with `main`
- [ ] Pint passes with no violations
- [ ] PHPStan passes with no errors
- [ ] Tests pass: `composer test`
- [ ] New behaviour is covered by tests where applicable
- [ ] Relevant `docs/` files are updated if you changed a subsystem

---

## Code Style

- PHP 8.3+ syntax
- Follow PSR-12 (enforced by Pint)
- PHPDoc on all public methods, classes, and interfaces
- Inline comments only for non-obvious logic - explain **why**, not **what**
- Follow SOLID, DRY, and KISS principles

---

## License

By contributing, you agree that your code will be licensed under the **GPL v2 or later**.