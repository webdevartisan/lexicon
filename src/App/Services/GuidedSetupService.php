<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogModel;
use App\Models\GuidedSetupFactsModel;
use App\Models\GuidedSetupMarkModel;
use App\Resources\BlogResource;
use App\ValueObjects\SetupStep;

/**
 * Builds the "Your next steps" list for one person on one blog.
 *
 * Owners get the six steps that take a blog from created to shared.
 * Collaborators get a shorter list picked from what their role allows, so a
 * custom role gets sensible steps without being named here.
 */
final class GuidedSetupService
{
    public function __construct(
        private BlogModel $blogs,
        private GuidedSetupMarkModel $marks,
        private GuidedSetupFactsModel $facts,
    ) {}

    /**
     * The step list, progress and card state for a person on a blog, or null
     * when they have no access to it.
     *
     * @return array{steps: SetupStep[], done: int, total: int, state: string, audience: string}|null
     */
    public function forUser(int $userId, int $blogId): ?array
    {
        $blog = $this->blogs->getBlog($blogId);
        if (!$blog) {
            return null;
        }

        $isOwner = $blog->ownerId() === $userId;
        if (!$isOwner && !$this->blogs->userCanAccessBlog($userId, $blogId)) {
            return null;
        }

        $marks = $this->marks->marksFor($userId, $blogId);
        $steps = $isOwner ? $this->ownerSteps($blog, $marks) : $this->memberSteps($blog, $userId, $marks);
        $done = count(array_filter($steps, fn (SetupStep $step) => $step->done));

        return [
            'steps' => $steps,
            'done' => $done,
            'total' => count($steps),
            'state' => $this->state($marks, $done === count($steps)),
            'audience' => $isOwner ? 'owner' : 'member',
        ];
    }

    /**
     * @param  array<string, true>  $marks
     * @return SetupStep[]
     */
    private function ownerSteps(BlogResource $blog, array $marks): array
    {
        $facts = $this->facts->forOwner($blog->id(), $blog->ownerId());
        $base = '/dashboard/blog/'.$blog->id();

        return [
            $this->step('start', true, null, false),
            $this->step('first_post', $facts['published_post'], '/dashboard/post/new', true, 'setup.steps.first_post.done'),
            $this->step('look', isset($marks['look_chosen']) || $facts['has_look'], $base.'/appearance'),
            $this->step('share', isset($marks['link_copied']), null),
            $this->step('followers', $facts['has_subscriber'] || isset($marks['subscribers_seen']), $base.'/subscribers'),
            $this->step('team', $facts['has_team'], $base.'/team'),
        ];
    }

    /**
     * @param  array<string, true>  $marks
     * @return SetupStep[]
     */
    private function memberSteps(BlogResource $blog, int $userId, array $marks): array
    {
        $facts = $this->facts->forMember($userId, $blog->id());
        $steps = [];

        if ($blog->userCan($userId, 'create_posts')) {
            $steps[] = $this->step('member_first_post', $facts['wrote_post'], '/dashboard/post/new');

            if ($blog->reviewsPosts() && !$blog->canPublish($userId)) {
                $steps[] = $this->step('member_review', $facts['submitted_post'], null);
            }
        }

        $steps[] = $this->step('member_profile', isset($marks['profile_saved']), '/account/profile');

        return $steps;
    }

    /**
     * Steps without a URL still get a label when the card handles them itself
     * (copying the link, opening the review explanation).
     */
    private function step(string $key, bool $done, ?string $url, bool $hasAction = true, ?string $doneNoteKey = null): SetupStep
    {
        $prefix = 'setup.steps.'.$key;

        return new SetupStep(
            key: $key,
            done: $done,
            titleKey: $prefix.'.title',
            descriptionKey: $prefix.'.description',
            actionUrl: $url,
            actionLabelKey: $hasAction ? $prefix.'.action' : null,
            doneNoteKey: $doneNoteKey,
        );
    }

    /**
     * @param  array<string, true>  $marks
     */
    private function state(array $marks, bool $allDone): string
    {
        if (isset($marks['dismissed'])) {
            return 'dismissed';
        }

        if ($allDone) {
            return 'finished';
        }

        return isset($marks['hidden']) ? 'hidden' : 'open';
    }
}
