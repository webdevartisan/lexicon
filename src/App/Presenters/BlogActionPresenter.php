<?php

declare(strict_types=1);

namespace App\Presenters;

/**
 * Decides which buttons the blog settings action bar offers, in the same shape
 * PostActionPresenter gives the post editor so both render through one partial.
 */
final class BlogActionPresenter
{
    public const INTENT_SAVE_DRAFT = 'save_draft';

    public const INTENT_PUBLISH = 'publish';

    public const INTENT_UPDATE = 'update';

    public const INTENT_UNPUBLISH = 'unpublish';

    public const INTENT_ARCHIVE = 'archive';

    /** @var array<string, array{label: string, tone: string}> */
    private const PILLS = [
        'draft' => ['label' => 'Draft', 'tone' => 'slate'],
        'published' => ['label' => 'Published', 'tone' => 'emerald'],
        'archived' => ['label' => 'Archived', 'tone' => 'zinc'],
    ];

    /**
     * Build the action set for a blog in a given status.
     *
     * @param  string  $status  Current blog status
     * @return array{primary: array<string, string>, secondary: array<string, string>|null, menu: array<int, array<string, string>>, pill: array{label: string, tone: string}}
     */
    public static function for(string $status): array
    {
        return match ($status) {
            'published' => [
                'primary' => self::button(self::INTENT_UPDATE, 'Update', 'save', 'blue'),
                'secondary' => null,
                'menu' => [
                    self::button(self::INTENT_UNPUBLISH, 'Unpublish (move to draft)', 'undo-2', 'slate'),
                    self::button(self::INTENT_ARCHIVE, 'Archive', 'archive', 'slate'),
                ],
                'pill' => self::PILLS['published'],
            ],
            'archived' => [
                'primary' => self::button(self::INTENT_UPDATE, 'Save', 'save', 'blue'),
                'secondary' => null,
                'menu' => [
                    self::button(self::INTENT_PUBLISH, 'Publish again', 'send', 'slate'),
                    self::button(self::INTENT_SAVE_DRAFT, 'Move to draft', 'undo-2', 'slate'),
                ],
                'pill' => self::PILLS['archived'],
            ],
            default => [
                'primary' => self::button(self::INTENT_PUBLISH, 'Publish', 'send', 'green'),
                'secondary' => self::button(self::INTENT_SAVE_DRAFT, 'Save draft', 'save', 'slate'),
                'menu' => [
                    self::button(self::INTENT_ARCHIVE, 'Archive', 'archive', 'slate'),
                ],
                'pill' => self::PILLS['draft'],
            ],
        };
    }

    /**
     * Translate a submitted intent into the status it asks for. An unknown or
     * missing intent keeps the current status, so pressing Enter in a field
     * saves the settings without publishing or unpublishing anything.
     *
     * @param  string|null  $intent  Value of the clicked submit button
     * @param  string  $currentStatus  Status the blog holds today
     * @return string The status to store
     */
    public static function statusForIntent(?string $intent, string $currentStatus): string
    {
        return match ($intent) {
            self::INTENT_SAVE_DRAFT, self::INTENT_UNPUBLISH => 'draft',
            self::INTENT_PUBLISH => 'published',
            self::INTENT_ARCHIVE => 'archived',
            default => $currentStatus,
        };
    }

    /**
     * @return array<string, string>
     */
    private static function button(string $intent, string $label, string $icon, string $variant): array
    {
        return ['intent' => $intent, 'label' => $label, 'icon' => $icon, 'variant' => $variant];
    }
}
