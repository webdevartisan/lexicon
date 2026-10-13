<?php

declare(strict_types=1);

namespace App\Services\Sessions;

use App\Models\UserModel;
use App\Presenters\SessionListPresenter;
use App\Services\TranslationService;
use Framework\Session;
use Framework\Session\SessionRegistry;

/**
 * The "where you're signed in" list, for the account owner and for an
 * administrator looking at the account.
 */
class SessionList
{
    public function __construct(
        private SessionRegistry $registry,
        private Session $session,
        private UserModel $users,
    ) {}

    /**
     * The owner's own sessions, in their interface language. Support
     * sessions an administrator opened as them are left out.
     *
     * @return list<array<string, mixed>>
     */
    public function forOwner(int $userId, string $locale, string $timezone): array
    {
        $presenter = new SessionListPresenter('chrome_translate', $locale, $timezone);

        return $presenter->present($this->registry->forUser($userId, false, time()), $this->session->hashedId());
    }

    /**
     * Every session of the account, support sessions named, addresses
     * shortened to their network, plus whether pruning has stalled.
     *
     * @return array{sessions: list<array<string, mixed>>, sessionImpersonators: array<int, string>, sessionPruneStalled: bool}
     */
    public function forAdministrator(int $userId, string $timezone): array
    {
        $now = time();
        $presenter = new SessionListPresenter([new TranslationService('en'), 'translate'], 'en', $timezone);

        $sessions = $presenter->present(
            $this->registry->forUser($userId, true, $now),
            $this->session->hashedId(),
            true
        );

        $impersonators = [];
        foreach ($sessions as $session) {
            $adminId = $session['impersonated_by'];
            if ($adminId !== null && !isset($impersonators[$adminId])) {
                $impersonators[$adminId] = (string) ($this->users->find($adminId)['handle'] ?? '#'.$adminId);
            }
        }

        $oldestExpired = $this->registry->oldestExpired($now);

        return [
            'sessions' => $sessions,
            'sessionImpersonators' => $impersonators,
            // Pruning runs hourly, so anything expired for two hours means it stopped.
            'sessionPruneStalled' => $oldestExpired !== null && $now - $oldestExpired > 7200,
        ];
    }
}
