# Performance Tips

## 1. Debounce Bulk Operations
If importing many posts:

```php
public function bulkImport(array $posts): int
{
    $imported = 0;
    
    foreach ($posts as $postData) {
        $id = parent::create($postData);  // Don't invalidate per post
        if ($id) {
            $imported++;
        }
    }
    
    // invalidate cache ONCE after all imports.
    if ($imported > 0) {
        cache()->deletePattern('*:GET:/blogs*');
    }
    
    return $imported;
}
```

---

## 2. Smart Invalidation
Only invalidate if published posts change:

```php
public function update(int|string $id, array $data): bool
{
    $post = $this->findResource($id);
    
    $result = parent::update($id, $data);
    
    if ($result) {
        // only invalidate cache if the post is published (or becoming published).
        $wasPublished = $post->isPublished();
        $isPublished = $data['published'] ?? $wasPublished;
        
        if ($wasPublished || $isPublished) {
            $blog = $post->blog();
            cache()->deletePattern("*:GET:/blog/{$blog->slug()}/{$post->slug()}*");
            cache()->deletePattern('*:GET:/blogs*');
        }
    }
    
    return $result;
}
```

---

## 3. Cache Warming
Pre-generate cache after clearing:

```php
public function update(int|string $id, array $data): bool
{
    $result = parent::update($id, $data);
    
    if ($result) {
        // Invalidate cache
        cache()->deletePattern('*:GET:/blogs*');
        
        // Optional: Warm cache by fetching the updated page
        $post = $this->findResource($id);
        if ($post && $post->isPublished()) {
            $blog = $post->blog();
            $url = buildLocalizedUrl("/blog/{$blog->slug()}/{$post->slug()}", true);
            
            // Background request to warm cache (non-blocking)
            exec("curl -s '{$url}' > /dev/null 2>&1 &");
        }
    }
    
    return $result;
}
```

---

# Monitoring Cache Invalidation
Add logging to track invalidations:

```php
public function update(int|string $id, array $data): bool
{
    $post = $this->findResource($id);
    $result = parent::update($id, $data);
    
    if ($result) {
        $blog = $post->blog();
        
        // log cache invalidations for monitoring.
        $patterns = [
            "*:GET:/blog/{$blog->slug()}/{$post->slug()}*",
            '*:GET:/blogs*'
        ];
        
        foreach ($patterns as $pattern) {
            $deleted = cache()->deletePattern($pattern);
            error_log("Cache invalidated: {$pattern} ({$deleted} entries)");
        }
    }
    
    return $result;
}
```

---

# Testing Cache Invalidation
Create a test:

```php
// 1. Create a post
$postId = $postModel->create([
    'title' => 'Test Post',
    'slug' => 'test-post',
    'blog_id' => 1,
    'published' => 1
]);

// 2. Visit the post page (cache it)
// Visit: /blog/my-blog/test-post
// Should see: x-cache-status: STORED

// 3. Update the post
$postModel->update($postId, ['title' => 'Updated Title']);

// 4. Visit the post page again
// Visit: /blog/my-blog/test-post
// Should see: x-cache-status: STORED (not HIT - was invalidated)

// 5. Refresh again
// Should see: x-cache-status: HIT (cached again with new content)
```
