<?php

declare(strict_types=1);

namespace App\Services;

/**
 * BreadcrumbService
 *
 * manage the breadcrumb trail for navigation.
 * This service provides methods to build, customize, and retrieve breadcrumbs.
 */
class BreadcrumbService
{
    /**
     * @var array<int, array{label: string, url: string|null}> Breadcrumb items
     */
    private array $items = [];

    /**
     * @var bool Whether breadcrumbs were manually set by a controller
     */
    private bool $manuallySet = false;

    /**
     * Add breadcrumb item to the trail
     *
     * append a new item to the end of the breadcrumb trail.
     *
     * @param  string  $label  Visible text for the breadcrumb
     * @param  string|null  $url  Link URL (null for current page, no link)
     * @param  string|null  $translationKey  Translation key for i18n (e.g., 't-dashboard')
     * @return self Fluent interface
     */
    public function add(string $label, ?string $url = null, ?string $translationKey = null): self
    {
        $this->items[] = [
            'label' => $label,
            'url' => $url,
            'key' => $translationKey,
        ];

        return $this;
    }

    /**
     * Add multiple breadcrumbs at once
     *
     * add several breadcrumb items in a single call.
     *
     * @param  array<int, array{label: string, url: string|null, key: string|null}>  $items  Array of breadcrumb items
     * @return self Fluent interface
     */
    public function addMany(array $items): self
    {
        foreach ($items as $item) {
            $this->add(
                $item['label'],
                $item['url'] ?? null,
                $item['key'] ?? null
            );
        }

        return $this;
    }

    /**
     * Set entire breadcrumb trail at once
     *
     * replace all existing breadcrumbs with a new trail.
     * This is used by middleware for auto-generation or by controllers for custom trails.
     *
     * @param  array<int, array{label: string, url: string|null}>  $items  Complete breadcrumb trail
     * @param  bool  $manual  Whether this was manually set by a controller
     * @return self Fluent interface
     */
    public function set(array $items, bool $manual = false): self
    {
        $this->items = $items;
        $this->manuallySet = $manual;

        return $this;
    }

    /**
     * Prepend breadcrumb to the start of the trail
     *
     * add an item at the beginning of the breadcrumb trail.
     * Useful for adding context after auto-generation.
     *
     * @param  string  $label  Visible text
     * @param  string|null  $url  Link URL
     * @param  string|null  $translationKey  Translation key for i18n
     * @return self Fluent interface
     */
    public function prepend(string $label, ?string $url = null, ?string $translationKey = null): self
    {
        array_unshift($this->items, [
            'label' => $label,
            'url' => $url,
            'key' => $translationKey,
        ]);

        return $this;
    }

    /**
     * Replace the last breadcrumb item
     *
     * update the label and URL of the final breadcrumb.
     * Useful for replacing generic labels with specific entity names.
     *
     * @param  string  $label  New label for the last item
     * @param  string|null  $url  New URL (null to keep current)
     * @param  string|null  $translationKey  Translation key (null to remove translation)
     * @return self Fluent interface
     */
    public function replaceLast(string $label, ?string $url = null, ?string $translationKey = null): self
    {
        if (!empty($this->items)) {
            $lastIndex = count($this->items) - 1;
            $this->items[$lastIndex]['label'] = $label;
            if ($url !== null) {
                $this->items[$lastIndex]['url'] = $url;
            }
            // update or remove translation key
            $this->items[$lastIndex]['key'] = $translationKey;
        }

        return $this;
    }

    /**
     * Get all breadcrumb items
     *
     * return the complete breadcrumb trail.
     *
     * @return array<int, array{label: string, url: string|null}> All breadcrumb items
     */
    public function get(): array
    {
        return $this->items;
    }

    /**
     * Get the last breadcrumb item
     *
     * return the final item in the trail.
     *
     * @return array{label: string, url: string|null}|null Last item or null if empty
     */
    public function getLast(): ?array
    {
        return !empty($this->items) ? end($this->items) : null;
    }

    /**
     * Check if trail has items
     *
     * verify if any breadcrumbs exist.
     *
     * @return bool True if breadcrumbs exist
     */
    public function hasItems(): bool
    {
        return !empty($this->items);
    }

    /**
     * Check if breadcrumbs were manually set
     *
     * determine if a controller customized the breadcrumbs.
     * When true, middleware should not override them.
     *
     * @return bool True if manually set by controller
     */
    public function wasManuallySet(): bool
    {
        return $this->manuallySet;
    }

    /**
     * Clear all breadcrumb items
     *
     * reset the breadcrumb trail to empty.
     *
     * @return self Fluent interface
     */
    public function clear(): self
    {
        $this->items = [];
        $this->manuallySet = false;

        return $this;
    }

    /**
     * Get breadcrumb count
     *
     * return the number of breadcrumb items.
     *
     * @return int Number of breadcrumbs
     */
    public function count(): int
    {
        return count($this->items);
    }
}
