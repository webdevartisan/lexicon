<?php

declare(strict_types=1);

namespace Framework\Interfaces;

/**
 * Lets framework code show interface text in the reader's language without
 * knowing where translations live.
 */
interface TranslatorInterface
{
    /**
     * @param  array<string, string|int>  $params  Values for {name} placeholders
     */
    public function translate(string $key, array $params = []): string;
}
