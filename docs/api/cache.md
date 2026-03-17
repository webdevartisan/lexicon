## Cache System

Lexicon uses a layered caching system to improve performance for static assets, full HTML pages, and expensive template fragments.

---

## Layers

- **Layer 1: Browser cache (web server headers)**
  - Static files (CSS, JS, images) are cached by the browser using `Cache-Control` headers configured at the web server layer (e.g. `.htaccess`).
- **Layer 2: Page cache (`CacheMiddleware`)**
  - Full HTML responses are cached per URL and locale.
  - Responses include headers such as `X-Cache-Status: HIT` and short `Cache-Control` lifetimes.
- **Layer 3: Fragment cache (`FragmentCache`)**
  - Template fragments (e.g. sidebars, icon sets) are cached independently and embedded into page responses.

---

## Components

- **`CacheService`**
  - Stores and retrieves cache entries on disk.
  - Manages an index file for fast pattern matching.
  - Used by `CacheMiddleware`, `FragmentCache`, and the `cache()` helper.
  - Bound in the container as a shared singleton.

- **`CacheKey`**
  - Generates normalized cache keys from requests.
  - Encodes locale, HTTP method, and path (optionally with whitelisted query parameters).
  - Stateless utility, also registered as a shared singleton.

- **`CacheMiddleware`**
  - Wraps the HTTP pipeline to serve and store cached HTML responses.
  - Uses `CacheService` and `CacheKey` to handle per‑URL caches.
  - Typically registered once in the HTTP middleware stack.

- **`FragmentCache`**
  - Used from templates to cache subtrees of the rendered output.
  - Exposed through template directives (e.g. `{% cache 'sidebar:nav' %}`).
  - Uses `CacheService` under the hood.

---

## Index File Format

The cache index is stored at:

- `storage/cache/keys.index`

Each line maps a logical key to a hashed filename:

```text
en:GET:/→abc123def456
en:GET:/blogs→789ghi012jkl
en:GET:/blogs?page=2→mno345pqr678
el:GET:/→stu901vwx234
```

- **Left side**: logical key (`{locale}:{method}:{path}[?query]`)
- **Right side**: hash used as the cache filename

---

## Core Operations

### 1. Caching a Page

```php
cache()->set('en:GET:/blogs', $html, 600);
```

What happens:

- Hash is generated from the key.
- Cache file is written under `storage/cache/{hash}.cache`.
- Entry is added to `keys.index`.

### 2. Deleting by Exact Key

```php
cache()->delete('en:GET:/blogs');
```

This:

- Looks up the hash in the index.
- Deletes the corresponding cache file.
- Removes the mapping from the index.

### 3. Deleting by Pattern

```php
cache()->deletePattern('*blogs*');
```

This:

- Reads the index file.
- Matches all keys using a wildcard pattern (via `fnmatch()`).
- Deletes matching cache files and removes their index entries.

Common examples:

```php
// Delete all blog-related cache
cache()->deletePattern('*blogs*');

// Delete all English cache
cache()->deletePattern('en:*');

// Delete all page 2 cache (any locale)
cache()->deletePattern('*page=2*');

// Delete all homepage cache across locales
cache()->deletePattern('*:GET:/');

// Delete everything
cache()->deletePattern('*');
```

---

## Thread Safety and Performance

Concurrent write operations are guarded by:

- `flock(LOCK_EX)` to prevent race conditions on index updates
- Atomic file operations (`rename`) when writing

Performance characteristics:

| Operation                  | Without Index | With Index |
|---------------------------|---------------|-----------:|
| `deletePattern('*blogs*')`| Not supported | Fast       |
| `set()`                   | Fast          | Slightly slower (index write) |
| `get()`                   | Fast          | Fast (no index read) |
| `clear()`                 | Fast          | Fast (index file deleted) |

The extra index write typically adds ~1–2 ms per cache write.

---

## Usage Patterns

### Auto‑invalidate on content changes

```php
public function create(array $data): int
{
    $id = parent::create($data);

    // Invalidate blog listing pages when a new post is created.
    cache()->deletePattern('*:GET:/blogs*');

    return $id;
}

public function update(int $id, array $data): bool
{
    $result = parent::update($id, $data);

    if ($result) {
        // Invalidate both the post page and listings.
        cache()->deletePattern('*:GET:/blogs*');
        cache()->deletePattern("*:GET:/post/{$id}*");
    }

    return $result;
}

public function delete(int $id): bool
{
    $result = parent::delete($id);

    if ($result) {
        // Invalidate affected cache entries.
        cache()->deletePattern('*:GET:/blogs*');
        cache()->deletePattern("*:GET:/post/{$id}*");
    }

    return $result;
}
```

### Locale‑specific invalidation

```php
// Clear only English cache
cache()->deletePattern('en:*');

// Clear only Greek cache
cache()->deletePattern('el:*');
```

### Selective clearing

```php
// Clear search results cache
cache()->deletePattern('*?q=*');

// Clear paginated results
cache()->deletePattern('*?page=*');

// Clear category pages
cache()->deletePattern('*category=*');
```

---

## Monitoring

You can inspect cache health using `cache()->stats()`:

```php
$stats = cache()->stats();

echo "Cache files: {$stats['live_files']}\n";
echo "Index entries: {$stats['index_entries']}\n";

if ($stats['live_files'] !== $stats['index_entries']) {
    echo "Warning: Index may be out of sync\n";
    // Optionally trigger index rebuild
}
```

For testing pattern behavior:

```php
cache()->set('en:GET:/blogs', 'Blog list EN', 600);
cache()->set('en:GET:/blogs?page=2', 'Blog page 2 EN', 600);
cache()->set('el:GET:/blogs', 'Blog list EL', 600);
cache()->set('en:GET:/', 'Homepage EN', 600);

$deleted = cache()->deletePattern('*blogs*');
echo "Deleted: {$deleted} entries\n";  // Expected: 3

var_dump(cache()->has('en:GET:/'));       // true
var_dump(cache()->has('en:GET:/blogs'));  // false
```

