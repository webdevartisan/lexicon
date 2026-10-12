<?php

declare(strict_types=1);

namespace App\Services\Archive;

use App\Models\PostModel;
use App\ValueObjects\PostFieldRules;
use Framework\Validation\Validator;

/**
 * Holds a post from an archive to the limits the editor enforces, and reduces it
 * to the fields the archive format defines.
 *
 * The archive is untrusted input, so every value is type-checked before the
 * editor's own rules run: those rules let arrays and numbers through, because the
 * editor only ever receives strings.
 */
final class ArchivePostValidator
{
    private const TEXT_FIELDS = [
        'title', 'slug', 'content', 'excerpt', 'featured_image', 'status', 'visibility', 'published_at', 'timezone',
        'focus_keyword', 'meta_title', 'meta_description', 'canonical_url', 'og_title', 'og_description', 'og_image',
        'og_image_alt', 'twitter_card_type', 'twitter_title', 'twitter_description', 'twitter_image',
    ];

    private const FLAG_FIELDS = ['comments_enabled', 'meta_noindex', 'meta_nofollow'];

    private const MAX_TAGS = 50;

    public function __construct(private ArchiveMediaUrls $urls) {}

    /**
     * Check one archive post.
     *
     * @param  mixed  $raw  One element of posts.json
     * @return array{data: array<string, mixed>, errors: list<string>}
     */
    public function validate(mixed $raw): array
    {
        if (!is_array($raw)) {
            return ['data' => [], 'errors' => ['The entry is not a post.']];
        }

        $errors = [];
        $data = $this->typed($raw, $errors);
        if ($errors !== []) {
            return ['data' => $data, 'errors' => $errors];
        }

        $data = $this->withDefaults($data);
        $errors = array_merge(
            $this->ruleErrors($data),
            $this->enumErrors($data),
            $this->imageErrors($data),
        );

        $data['published_at'] = $this->publishedAt($data['published_at'], $errors);
        $data['category'] = $this->term($raw['category'] ?? null, 'category', $errors);
        $data['tags'] = $this->tags($raw['tags'] ?? [], $errors);
        $data['author_name'] = is_string($raw['author_name'] ?? null) ? mb_substr($raw['author_name'], 0, 150) : '';

        return ['data' => $data, 'errors' => $errors];
    }

    /**
     * Known fields only, each as a string, a boolean or null.
     *
     * @param  array<mixed>  $raw
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function typed(array $raw, array &$errors): array
    {
        $data = [];

        foreach (self::TEXT_FIELDS as $field) {
            $value = $raw[$field] ?? null;
            if ($value !== null && !is_string($value)) {
                $errors[] = "The {$field} field is not text.";
                $value = null;
            }
            $data[$field] = $value;
        }

        foreach (self::FLAG_FIELDS as $field) {
            $value = $raw[$field] ?? false;
            if (!is_bool($value) && !in_array($value, [0, 1], true)) {
                $errors[] = "The {$field} field is not true or false.";
            }
            $data[$field] = (bool) $value;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withDefaults(array $data): array
    {
        $data['status'] ??= 'draft';
        $data['visibility'] ??= 'public';
        $data['timezone'] = ($data['timezone'] ?? '') !== '' ? $data['timezone'] : 'UTC';
        $data['twitter_card_type'] = ($data['twitter_card_type'] ?? '') !== '' ? $data['twitter_card_type'] : 'summary_large_image';
        $data['content'] ??= '';

        foreach ($data as $field => $value) {
            if (is_string($value) && trim($value) === '' && !in_array($field, ['title', 'slug', 'content'], true)) {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /**
     * The editor's own rules. Image fields are checked separately: an archive holds
     * them as archive paths, which the editor's url rule would refuse.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function ruleErrors(array $data): array
    {
        $rules = PostFieldRules::CORE + PostFieldRules::SEO;
        unset($rules['og_image'], $rules['twitter_image']);

        $validator = (new Validator($data))->rules($rules);
        if ($validator->passes()) {
            return [];
        }

        $errors = [];
        foreach ($validator->errors() as $field => $messages) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)).': '.implode(' ', $messages);
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function enumErrors(array $data): array
    {
        $errors = [];
        $statuses = array_diff(PostModel::STATUSES, ['moderated']);

        if (!in_array($data['status'], $statuses, true)) {
            $errors[] = 'Unknown status "'.mb_substr((string) $data['status'], 0, 20).'".';
        }
        if (!in_array($data['visibility'], PostModel::VISIBILITIES, true)) {
            $errors[] = 'Unknown visibility "'.mb_substr((string) $data['visibility'], 0, 20).'".';
        }

        return $errors;
    }

    /**
     * Image fields hold an archive path, an outside web address, or this site's upload URL.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function imageErrors(array $data): array
    {
        $errors = [];

        foreach (ArchiveFormat::IMAGE_FIELDS as $field) {
            $value = $data[$field];
            if ($value === null) {
                continue;
            }

            $valid = mb_strlen($value) <= 255 && (
                preg_match(ArchiveFormat::MEDIA_PATH_PATTERN, $value) === 1
                || ($this->urls->isExternal($value) && filter_var($value, FILTER_VALIDATE_URL) !== false)
                || $this->urls->canonical($value) !== null
            );

            if (!$valid) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)).' is not an image address.';
            }
        }

        return $errors;
    }

    /**
     * The publish date as the database stores it (UTC), or null.
     *
     * @param  list<string>  $errors
     */
    private function publishedAt(?string $value, array &$errors): ?string
    {
        if ($value === null) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d\TH:i:s\Z') !== $value) {
            $errors[] = 'The publish date is not a valid UTC date.';

            return null;
        }

        return $date->format('Y-m-d H:i:s');
    }

    /**
     * A category or tag: a name and a slug, both within the column and slug rules.
     *
     * @param  list<string>  $errors
     * @return array{name: string, slug: string}|null
     */
    private function term(mixed $raw, string $kind, array &$errors): ?array
    {
        if ($raw === null) {
            return null;
        }

        $name = is_array($raw) && is_string($raw['name'] ?? null) ? trim($raw['name']) : '';
        $slug = is_array($raw) && is_string($raw['slug'] ?? null) ? $raw['slug'] : '';

        if ($name === '' || mb_strlen($name) > 100 || strlen($slug) > 120
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            $errors[] = "A {$kind} has an invalid name or slug.";

            return null;
        }

        return ['name' => $name, 'slug' => $slug];
    }

    /**
     * @param  list<string>  $errors
     * @return list<array{name: string, slug: string}>
     */
    private function tags(mixed $raw, array &$errors): array
    {
        if (!is_array($raw) || !array_is_list($raw) || count($raw) > self::MAX_TAGS) {
            $errors[] = 'The tags are not a list of at most '.self::MAX_TAGS.'.';

            return [];
        }

        $tags = [];
        foreach ($raw as $item) {
            $tag = $this->term($item, 'tag', $errors);
            if ($tag !== null) {
                $tags[$tag['slug']] = $tag;
            }
        }

        return array_values($tags);
    }
}
