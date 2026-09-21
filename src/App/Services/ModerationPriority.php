<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Orders the reports queue by how urgently a person should look, not by age.
 *
 * Severity picks the band: every critical case sits above every high one, and
 * so on down. Within a band, volume, a recent burst, the author's track record
 * and a rule waiting on a person push a case up. Each of those is capped, and
 * all of them together stay below the gap between two bands, so no amount of
 * reporting can lift a spam case above a harassment case.
 */
final class ModerationPriority
{
    /** 200 apart; the signals below add at most 135. */
    private const SEVERITY_BAND = ['low' => 0, 'medium' => 200, 'high' => 400, 'critical' => 600];

    /**
     * @param  string|null  $severity  Most severe category on the case
     * @param  int  $counted  Reports from reporters in good standing
     * @param  int  $uncounted  Reports that did not meet the standing rules
     * @param  int  $lastDay  Reports within a day of the most recent one
     * @param  int  $priorUpheld  Earlier cases against the same author that were upheld
     * @param  bool  $awaitingPerson  A rule proposed an action, or an automatic one failed
     */
    public static function score(
        ?string $severity,
        int $counted,
        int $uncounted,
        int $lastDay,
        int $priorUpheld,
        bool $awaitingPerson,
    ): int {
        return (self::SEVERITY_BAND[$severity ?? 'low'] ?? self::SEVERITY_BAND['low'])
            + 5 * min($counted, 10)
            + min($uncounted, 10)
            + ($lastDay >= 3 ? 10 : 0)
            + 15 * min($priorUpheld, 3)
            + ($awaitingPerson ? 20 : 0);
    }
}
