<?php

declare(strict_types=1);

namespace App\ValueObjects;

/**
 * One row of the "Your next steps" card. Text is held as translation keys,
 * and actionUrl is an app path without the locale prefix.
 */
final class SetupStep
{
    public function __construct(
        public string $key,
        public bool $done,
        public string $titleKey,
        public string $descriptionKey,
        public ?string $actionUrl,
        public ?string $actionLabelKey,
        public ?string $doneNoteKey = null,
    ) {}
}
