<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\TimezoneHelper;
use App\Models\UserPreferencesModel;
use Framework\Session;

/**
 * Which clock a signed-in person reads dates on.
 *
 * A zone picked in Preferences wins. An account that never picked one is on
 * "Automatic" and follows the device it is used from, which the browser reports
 * once per session. Until it has, the site's zone stands in.
 */
final class AccountZone
{
    private const DEVICE_KEY = 'device_zone';

    private const REPORTED_KEY = 'device_zone_reported';

    public function __construct(
        private UserPreferencesModel $preferences,
        private Session $session,
    ) {}

    /**
     * The zone resolved for one account.
     *
     * @param  int  $userId  User identifier
     * @return string A valid timezone identifier
     */
    public function resolve(int $userId): string
    {
        return $this->chosen($userId) ?? $this->device() ?? site_timezone();
    }

    /**
     * The zone the account picked, or null when it is on Automatic.
     *
     * @param  int  $userId  User identifier
     */
    public function chosen(int $userId): ?string
    {
        $zone = $this->preferences->getTimezone($userId);

        return TimezoneHelper::isValid($zone) ? $zone : null;
    }

    /**
     * The zone this session's device reported, or null when it has not yet.
     */
    public function device(): ?string
    {
        $zone = $this->session->get(self::DEVICE_KEY);

        return is_string($zone) && TimezoneHelper::isValid($zone) ? $zone : null;
    }

    /**
     * The name exactly as the browser last sent it, so it can tell whether to
     * send again. It differs from device() for older names and for ones refused.
     */
    public function reportedDevice(): ?string
    {
        $reported = $this->session->get(self::REPORTED_KEY);

        return is_string($reported) ? $reported : null;
    }

    /**
     * Keep the zone the browser reported for the rest of the session.
     *
     * @param  string  $reported  Zone name from the browser, possibly an older one such as Asia/Calcutta
     * @return string|null The name kept, or null when it is not a zone PHP knows
     */
    public function rememberDevice(string $reported): ?string
    {
        $reported = mb_substr(trim($reported), 0, 64);
        $zone = TimezoneHelper::currentName($reported);

        $this->session->set(self::REPORTED_KEY, $reported);
        if ($zone === null) {
            $this->session->remove(self::DEVICE_KEY);
        } else {
            $this->session->set(self::DEVICE_KEY, $zone);
        }

        return $zone;
    }
}
