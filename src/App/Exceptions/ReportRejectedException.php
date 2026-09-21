<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A reader's report could not be accepted. The message is written for them.
 */
class ReportRejectedException extends RuntimeException {}
