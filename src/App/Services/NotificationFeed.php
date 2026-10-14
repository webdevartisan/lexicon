<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogInvitationModel;
use App\Models\BlogModel;
use App\Models\UserProfileModel;
use App\Presenters\NotificationPresenter;

/**
 * Presents a batch of notification rows with the details their payloads only
 * point at: the blog's current name, and the actor's name and picture.
 *
 * NotificationPresenter works on one row at a time from its payload alone.
 * Anything that needs the database is resolved here once for the whole batch,
 * so a panel or page costs at most two extra queries however many rows it has.
 * Live blog names also mean a renamed blog reads correctly in old rows, and
 * rows written before the payload carried a name still get one.
 */
final class NotificationFeed
{
    public function __construct(
        private BlogModel $blogs,
        private UserProfileModel $profiles,
        private BlogRoleLabels $roleLabels,
        private BlogInvitationModel $invitations,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows  notifications table rows
     * @return array<int, array<string, mixed>> Presented items, each with `actor` resolved
     *                                          (name, avatar_url) and `source` set to the blog
     *                                          name for content rows, null for personal ones
     */
    public function present(array $rows): array
    {
        $items = NotificationPresenter::forAll($rows);

        if ($items === []) {
            return [];
        }

        $blogNames = $this->blogNames($items);
        $actors = $this->actors($items);
        $openInvites = array_flip($this->invitations->openTokenHashes(array_map(
            static fn (string $token): string => hash('sha256', $token),
            array_values(array_filter(array_column($items, 'inviteToken')))
        )));

        return array_map(fn (array $item): array => $this->complete($item, $blogNames, $actors, $openInvites), $items);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $blogNames
     * @param  array{byHandle: array<string, array<string, mixed>>, byId: array<int, array<string, mixed>>}  $actors
     * @param  array<string, int>  $openInvites  Hashes of invitations that can still be answered
     * @return array<string, mixed>
     */
    private function complete(array $item, array $blogNames, array $actors, array $openInvites): array
    {
        $blogName = $blogNames[$item['blogId']] ?? $item['blogName'];
        $sentence = $item['sentence'];

        if ($this->targetIsBlog($item['type'])) {
            $sentence['target'] = $blogName;
        }

        $sentence['targetFallback'] = match (true) {
            $this->targetIsBlog($item['type']) => 'notifications.aBlog',
            $item['type'] === 'moderation.reporting_paused' => 'notifications.furtherNotice',
            default => 'notifications.aPost',
        };

        if ($item['role'] !== null) {
            $sentence['params']['role'] = $this->roleLabels->label($item['role']);
        }

        $actor = $item['actor'];
        if ($actor !== null) {
            $known = ($actor['handle'] !== null ? $actors['byHandle'][$actor['handle']] ?? null : null)
                ?? ($actor['id'] !== null ? $actors['byId'][$actor['id']] ?? null : null);

            $actor = [
                'name' => $known['name'] ?? $actor['name'],
                'avatar_url' => $known['avatar_url'] ?? null,
            ];
            $sentence['actor'] = $actor['name'];
        }

        // Accept and Decline only while answering can still work; an accepted,
        // declined or expired invitation is just a record of what happened.
        $token = $item['inviteToken'];
        $item['invite'] = $token !== null && isset($openInvites[hash('sha256', $token)])
            ? ['accept' => '/invite/'.rawurlencode($token).'/accept', 'decline' => '/invite/'.rawurlencode($token).'/decline']
            : null;
        unset($item['inviteToken']);

        $item['sentence'] = $sentence;
        $item['actor'] = $actor;
        $item['source'] = $item['scope'] === 'content' ? $blogName : null;

        return $item;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, string>
     */
    private function blogNames(array $items): array
    {
        $ids = array_values(array_unique(array_filter(array_column($items, 'blogId'))));

        $names = [];
        foreach ($this->blogs->findByIds($ids) as $blog) {
            $names[(int) $blog['id']] = (string) $blog['blog_name'];
        }

        return $names;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{byHandle: array<string, array<string, mixed>>, byId: array<int, array<string, mixed>>}
     */
    private function actors(array $items): array
    {
        $handles = [];
        $ids = [];
        foreach ($items as $item) {
            if ($item['actor'] === null) {
                continue;
            }
            if ($item['actor']['handle'] !== null) {
                $handles[] = $item['actor']['handle'];
            }
            if ($item['actor']['id'] !== null) {
                $ids[] = $item['actor']['id'];
            }
        }

        $byHandle = [];
        $byId = [];
        foreach ($this->profiles->actorsFor($handles, $ids) as $person) {
            $byHandle[$person['handle']] = $person;
            $byId[$person['id']] = $person;
        }

        return ['byHandle' => $byHandle, 'byId' => $byId];
    }

    private function targetIsBlog(string $type): bool
    {
        return in_array($type, [
            'blog.invite', 'blog.invite_declined', 'collaborator.role_changed', 'collaborator.removed', 'analytics.spike',
        ], true);
    }
}
