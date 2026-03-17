<?php

declare(strict_types=1);

namespace App\Models;

use Framework\Model;

/**
 * Application-specific base model.
 *
 * Provides a centralized extension point for application-level model behavior.
 * All application models should extend this class rather than Framework\Model
 * directly to ensure consistent functionality across the application.
 */
abstract class AppModel extends Model
{
    // Extension point for future app-specific features
}
