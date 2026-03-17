## Migrations

Lexicon’s database schema is currently defined by a unified install migration located at:

- `database/migrations/2026_02_02_install.sql`

This file creates all core tables, supporting tables, and the `migrations` metadata table.

---

## Initial Install

On a fresh database:

1. Create an empty database (e.g. `blog` or `lexicon`).
2. Import the unified migration:

```bash
mysql -u your_user -p your_database < database/migrations/2026_02_02_install.sql
```

3. Configure your `.env` with matching database credentials.
4. Run any application setup commands (see `docs/getting-started.md`).

---

## `migrations` Table

The migration file creates a simple `migrations` table:

- `id` – auto‑increment primary key
- `filename` – the name of the applied migration file
- `applied_at` – timestamp when it was applied

As you add incremental migrations in the future, record them here to track schema versioning and avoid re‑applying the same file.

---

## Future Incremental Migrations

If you split the schema into incremental migrations:

- Place new SQL files under `database/migrations/` with timestamped filenames.
- Ensure each migration is **idempotent** or guarded appropriately (e.g. using `IF NOT EXISTS`).
- After running a migration, insert a row into the `migrations` table with its filename.

Application‑level tooling (console commands) can later automate:

- Scanning `database/migrations/` for unapplied files
- Applying them in order
- Recording them in `migrations`

For a conceptual overview of what the schema contains, see:

- `docs/database/schema.md`
- `docs/database/relationships.md`

