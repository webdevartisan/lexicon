<?php

declare(strict_types=1);

namespace Framework\Modules;

use RuntimeException;

/**
 * Thrown when a module.json file is unreadable or breaks the manifest rules.
 */
final class InvalidManifestException extends RuntimeException
{
}
