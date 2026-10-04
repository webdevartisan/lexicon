## Migrations

The full database schema lives in one file:

- `database/schema.sql`: every table, index, foreign key and the reference data (roles, permissions, reserved handles, scheduled tasks).
- `database/seeds/*.sql`: default content loaded after the schema, such as the static pages.

The test database is built from `schema.sql`, so it has to stay complete.

---

## Initial Install

Run the setup script:

```bash
php scripts/setup/setup.php
```

On an empty database it installs `schema.sql`, runs every file in `database/seeds/`, and records any files already in `database/migrations/` as applied, since the schema includes them.

To install by hand instead:

```bash
mysql -u your_user -p your_database < database/schema.sql
mysql -u your_user -p your_database < database/seeds/pages.sql
```

---

## Schema Changes

There is no migration runner in the app. A schema change is:

1. A new SQL file in `database/migrations/` with a dated name, e.g. `2026_10_05_add_post_series.sql`.
2. The same change made in `database/schema.sql`.
3. Applied to existing databases, either by running the setup script again (it runs pending files in name order) or by hand, followed by:

```sql
INSERT INTO migrations (filename) VALUES ('2026_10_05_add_post_series.sql');
```

The `migrations` table has `id`, `filename` and `applied_at`. Only record a file after it applied cleanly.

Once every environment has a migration, it can be deleted, because `schema.sql` already holds the change.

---

For a conceptual overview of what the schema contains, see:

- `docs/database/schema.md`
- `docs/database/relationships.md`
