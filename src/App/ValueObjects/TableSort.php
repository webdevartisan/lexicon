<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Framework\Core\Request;

/**
 * A validated "sort this table by that column" choice, taken from the query string.
 *
 * The request never names a SQL column. It names a key, and the whitelist passed
 * by the caller decides what SQL that key means, so nothing unlisted can reach an
 * ORDER BY clause. An unknown key falls back to the default instead of erroring,
 * because a hand-edited URL should not 500 an admin page.
 */
final class TableSort
{
    /**
     * @param  array<string, string>  $columns  key => SQL expression
     * @param  array<string, mixed>  $query  current query string, for building links
     */
    private function __construct(
        private readonly string $key,
        private readonly string $direction,
        private readonly array $columns,
        private readonly string $tiebreaker,
        private readonly array $query,
    ) {}

    /**
     * Read `sort` and `dir` off the request and validate both.
     *
     * @param  array<string, string>  $columns  key => SQL expression, e.g. `['name' => 'b.blog_name']`
     * @param  string  $defaultKey  Key to use when the request names none (or names a bad one)
     * @param  string  $defaultDirection  `asc` or `desc`
     * @param  string  $tiebreaker  SQL appended after the sort column so paging stays stable
     */
    public static function fromRequest(
        Request $request,
        array $columns,
        string $defaultKey,
        string $defaultDirection = 'desc',
        string $tiebreaker = ''
    ): self {
        // `?sort[]=x` hands us an array, and casting one to string is a fatal.
        // Anything that is not a scalar is simply not a sort key.
        $rawKey = $request->get['sort'] ?? '';
        $rawDirection = $request->get['dir'] ?? '';

        $key = is_scalar($rawKey) ? (string) $rawKey : '';
        $direction = strtolower(trim(is_scalar($rawDirection) ? (string) $rawDirection : ''));

        if (!isset($columns[$key])) {
            $key = $defaultKey;
            $direction = '';
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = $defaultDirection;
        }

        return new self($key, $direction, $columns, $tiebreaker, $request->get ?? []);
    }

    /**
     * The ORDER BY body, ready to interpolate. Contains only whitelisted SQL.
     */
    public function orderBy(): string
    {
        $clause = $this->columns[$this->key].' '.strtoupper($this->direction);

        // Without a unique tiebreaker, rows sharing a sort value can swap places
        // between pages and appear twice (or not at all) while paging.
        return $this->tiebreaker !== ''
            ? $clause.', '.$this->tiebreaker
            : $clause;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function direction(): string
    {
        return $this->direction;
    }

    /**
     * Whether the table is currently sorted by this column.
     */
    public function isOn(string $key): bool
    {
        return $this->key === $key;
    }

    /**
     * The direction a click on this column should produce: flip if it is already
     * the active column, otherwise start ascending.
     */
    public function nextDirection(string $key): string
    {
        if (!$this->isOn($key)) {
            return 'asc';
        }

        return $this->direction === 'asc' ? 'desc' : 'asc';
    }

    /**
     * Whether a key is sortable at all, so a view can degrade to a plain header.
     */
    public function has(string $key): bool
    {
        return isset($this->columns[$key]);
    }

    /**
     * Link that sorts by `$key`, keeping every active filter and dropping the
     * page number — page 4 of the old order means nothing in the new one.
     */
    public function urlFor(string $basePath, string $key): string
    {
        $params = $this->query;
        unset($params['page']);

        $params['sort'] = $key;
        $params['dir'] = $this->nextDirection($key);

        return $basePath.'?'.http_build_query($params);
    }
}
