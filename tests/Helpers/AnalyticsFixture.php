<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\AnalyticsRollupModel;
use App\Services\Analytics\EventRegistry;
use Framework\Database;

/**
 * Writes raw Insights rows straight to the tables, for tests whose right answers
 * are worked out by hand: a page view, the visit it belongs to, or any other event.
 */
final class AnalyticsFixture
{
    private const VISIT_FIELDS = ['device' => 'desktop', 'browser' => 'Firefox', 'os' => 'Linux', 'country' => null];

    public static function registry(): EventRegistry
    {
        return new EventRegistry((require ROOT_PATH.'/config/analytics.php')['events']);
    }

    public static function rollups(Database $db): AnalyticsRollupModel
    {
        return new AnalyticsRollupModel($db, self::registry());
    }

    /**
     * A page view. Pass 'visit' => a label to put it in that visit, created the first
     * time the label is seen; views sharing a label are one visit. 'visitor' is a label too.
     *
     * @param  array<string, mixed>  $view  analytics_events columns, plus visit, visitor, device, browser, os and country
     * @return string The view id
     */
    public static function view(Database $db, array $view): string
    {
        $viewId = random_bytes(16);
        $visitFields = array_intersect_key($view, self::VISIT_FIELDS) + self::VISIT_FIELDS;
        $view = array_diff_key($view, self::VISIT_FIELDS);

        if (isset($view['visit'])) {
            $view['visit_id'] = self::visit($db, (string) $view['visit'], $view, $visitFields);
        }

        self::event($db, $view + ['name' => 'page_view', 'event_key' => $viewId, 'view_id' => $viewId, 'page_type' => 'post']);

        return $viewId;
    }

    /**
     * Any event. The time defaults to now and the day to the time's UTC day.
     *
     * @param  array<string, mixed>  $event  analytics_events columns, plus a visitor label
     */
    public static function event(Database $db, array $event): void
    {
        unset($event['visit']);

        if (isset($event['visitor'])) {
            $event['visitor_hash'] = md5((string) $event['visitor'], true);
            unset($event['visitor']);
        }

        $createdAt = (string) ($event['created_at'] ?? gmdate('Y-m-d H:i:s'));
        $event += [
            'event_key' => random_bytes(16),
            'visitor_kind' => isset($event['visitor_hash']) ? 'daily' : null,
            'channel' => isset($event['visitor_hash']) ? 'direct' : null,
            'created_at' => $createdAt,
            'local_date' => substr($createdAt, 0, 10),
        ];

        if (isset($event['path']) && !isset($event['path_hash'])) {
            $event['path_hash'] = substr(hash('sha256', (string) $event['path'], true), 0, 8);
        }

        if (isset($event['props']) && is_array($event['props'])) {
            $event['props'] = json_encode($event['props'], JSON_THROW_ON_ERROR);
        }

        $columns = array_keys($event);
        $db->execute(
            'INSERT INTO analytics_events ('.implode(', ', $columns).') VALUES ('.implode(', ', array_fill(0, count($columns), '?')).')',
            array_values($event)
        );
    }

    /**
     * The first view of a visit says how it began; later ones only move it on.
     *
     * @param  array<string, mixed>  $view
     * @param  array<string, mixed>  $fields  device, browser, os and country
     */
    private static function visit(Database $db, string $label, array $view, array $fields): string
    {
        $visitId = md5('visit:'.$label, true);
        $visitor = $view['visitor_hash'] ?? md5((string) ($view['visitor'] ?? $label), true);
        $at = (string) ($view['created_at'] ?? gmdate('Y-m-d H:i:s'));

        $db->execute(
            'INSERT INTO analytics_visits (id, visitor_hash, seq, visitor_kind, started_at, last_seen_at, entry_path, entry_page_type,
                                           entry_blog_id, entry_post_id, channel, referrer_host, referrer_source, utm_source,
                                           utm_medium, utm_campaign, device, browser, os, country)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE page_views = page_views + 1, started_at = LEAST(started_at, VALUES(started_at)),
                                     last_seen_at = GREATEST(last_seen_at, VALUES(last_seen_at))',
            [$visitId, $visitor, crc32($label), $view['visitor_kind'] ?? 'daily', $at, $at, $view['path'] ?? '/',
                $view['page_type'] ?? 'post', $view['blog_id'] ?? null, $view['post_id'] ?? null, $view['channel'] ?? 'direct',
                $view['referrer_host'] ?? null, $view['referrer_source'] ?? null, $view['utm_source'] ?? null,
                $view['utm_medium'] ?? null, $view['utm_campaign'] ?? null,
                $fields['device'], $fields['browser'], $fields['os'], $fields['country']]
        );

        return $visitId;
    }
}
