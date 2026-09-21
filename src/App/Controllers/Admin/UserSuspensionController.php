<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\UserModel;
use App\Services\AccountErasureService;
use App\Services\UserSuspensionService;
use DateTimeImmutable;
use DateTimeZone;
use Framework\Core\Response;
use RuntimeException;

/**
 * Suspends accounts, temporarily or permanently, and lifts suspensions.
 *
 * One confirmation page serves both directions: it offers the suspend form for
 * an active account and the lift form for a suspended one, so an admin always
 * reads what is about to happen before it happens.
 */
class UserSuspensionController extends ManagedUserController
{
    /**
     * Preset lengths offered in the duration picker, in hours.
     */
    public const DURATIONS = [
        '1h' => ['hours' => 1, 'label' => '1 hour'],
        '24h' => ['hours' => 24, 'label' => '24 hours'],
        '3d' => ['hours' => 72, 'label' => '3 days'],
        '7d' => ['hours' => 168, 'label' => '7 days'],
        '14d' => ['hours' => 336, 'label' => '14 days'],
        '30d' => ['hours' => 720, 'label' => '30 days'],
        '90d' => ['hours' => 2160, 'label' => '90 days'],
    ];

    /** A custom end date further out than this is a permanent suspension in disguise. */
    private const MAX_CUSTOM_DAYS = 365;

    public function __construct(
        UserModel $users,
        AccountErasureService $erasure,
        private UserSuspensionService $suspensions,
    ) {
        parent::__construct($users, $erasure);
    }

    public function confirm(string $id): Response
    {
        $user = $this->target($id);
        $this->guardAdministratorTarget($user);

        return $this->view('user.suspend', [
            'user' => $user,
            'current' => $this->suspensions->current((int) $user['id']),
            'impact' => $this->suspensions->impact((int) $user['id']),
            'durations' => self::DURATIONS,
            'isSelf' => (int) $user['id'] === $this->actorId(),
            'viewerTimezone' => viewer_timezone(),
        ]);
    }

    public function suspend(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = $this->target($id);
        $this->guardAdministratorTarget($user);
        $userId = (int) $user['id'];
        $back = '/admin/users/'.$userId.'/suspend';

        $refusal = $this->suspendRefusal($userId);

        if ($refusal !== null) {
            $this->flash('error', $refusal);

            return $this->redirect($back);
        }

        $validator = $this->validateOrFail([
            'type' => 'required|in:temporary,permanent',
            'reason' => 'required|min:3|max:500',
        ]);
        $input = $validator->validated();

        $expiresAt = null;

        if ($input['type'] === UserSuspensionService::TYPE_TEMPORARY) {
            try {
                $expiresAt = $this->resolveExpiry();
            } catch (RuntimeException $e) {
                $this->flash('error', $e->getMessage());

                // Back rather than to $back, so the reason they typed is kept.
                return $this->redirectBack();
            }
        }

        try {
            $hidden = $this->suspensions->suspend($userId, $expiresAt, trim((string) $input['reason']), $this->actorId());
        } catch (RuntimeException $e) {
            $this->flash('error', 'The account was not suspended. '.$e->getMessage());

            return $this->redirect($back);
        }

        audit()->log(
            $this->actorId(),
            'user.suspended',
            'user',
            $userId,
            [
                'type' => $input['type'],
                'expires_at' => $expiresAt,
                'reason' => trim((string) $input['reason']),
                'blogs_hidden' => $hidden['blogs'],
                'comments_hidden' => $hidden['comments'],
            ],
            $this->request->ip()
        );

        $this->flash('success', sprintf(
            '@%s is suspended %s. %d %s and %d %s hidden.',
            $user['handle'],
            $expiresAt === null ? 'permanently' : 'until '.local_datetime($expiresAt, 'M j, Y H:i T'),
            $hidden['blogs'],
            $hidden['blogs'] === 1 ? 'blog' : 'blogs',
            $hidden['comments'],
            $hidden['comments'] === 1 ? 'comment' : 'comments'
        ));

        return $this->redirect($this->showUrl($userId));
    }

    public function lift(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = $this->target($id);
        $this->guardAdministratorTarget($user);
        $userId = (int) $user['id'];

        try {
            $restored = $this->suspensions->lift($userId, $this->actorId(), 'manual');
        } catch (RuntimeException $e) {
            $this->flash('error', 'The suspension was not lifted. '.$e->getMessage());

            return $this->redirect('/admin/users/'.$userId.'/suspend');
        }

        audit()->log(
            $this->actorId(),
            'user.suspension_lifted',
            'user',
            $userId,
            ['kind' => 'manual', 'blogs_restored' => $restored['blogs'], 'comments_restored' => $restored['comments']],
            $this->request->ip()
        );

        $this->flash('success', sprintf(
            'Suspension lifted for @%s. %d %s and %d %s restored.',
            $user['handle'],
            $restored['blogs'],
            $restored['blogs'] === 1 ? 'blog' : 'blogs',
            $restored['comments'],
            $restored['comments'] === 1 ? 'comment' : 'comments'
        ));

        return $this->redirect($this->showUrl($userId));
    }

    private function suspendRefusal(int $userId): ?string
    {
        if ($userId === $this->actorId()) {
            return 'You cannot suspend your own account.';
        }

        // The last administrator suspended means nobody can reach the panel to lift it.
        if ($this->isAdministrator($userId) && $this->users->countAdministrators() <= 1) {
            return 'This is the last active administrator. Give another account the Administrator role first.';
        }

        return null;
    }

    /**
     * The UTC end time the form asked for.
     *
     * A custom date arrives in the admin's own timezone, because that is the
     * clock they were looking at when they picked it.
     *
     * @throws RuntimeException With a message fit to show the admin
     */
    private function resolveExpiry(): string
    {
        $duration = (string) $this->request->postParam('duration', '');
        $utc = new DateTimeZone('UTC');

        if (isset(self::DURATIONS[$duration])) {
            $hours = self::DURATIONS[$duration]['hours'];

            return (new DateTimeImmutable('now', $utc))->modify("+{$hours} hours")->format('Y-m-d H:i:s');
        }

        if ($duration !== 'custom') {
            throw new RuntimeException('Choose how long the suspension should last.');
        }

        $raw = trim((string) $this->request->postParam('until', ''));
        $until = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $raw, new DateTimeZone(viewer_timezone()));

        if ($until === false) {
            throw new RuntimeException('Enter an end date and time for the suspension.');
        }

        $now = new DateTimeImmutable('now', $utc);
        $until = $until->setTimezone($utc);

        if ($until <= $now) {
            throw new RuntimeException('The end date has to be in the future.');
        }

        if ($until > $now->modify('+'.self::MAX_CUSTOM_DAYS.' days')) {
            throw new RuntimeException('A temporary suspension can last at most a year. Choose permanent instead.');
        }

        return $until->format('Y-m-d H:i:s');
    }
}
