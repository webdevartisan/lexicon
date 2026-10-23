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
 * Owners get the six steps that take a blog from created to shared, and
 * co-owners get the same blog steps their role can act on. Other
 * collaborators get a shorter list picked from what their role allows, so a
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

        $audience = $this->audience($blog, $userId);
        if ($audience === null) {
            return null;
        }

        $marks = $this->marks->marksFor($userId, $blogId);
        $steps = $audience === 'member'
            ? $this->memberSteps($blog, $userId, $marks)
            : $this->ownerSteps($blog, $userId, $marks);
        $done = count(array_filter($steps, fn (SetupStep $step) => $step->done));

        return [
            'steps' => $steps,
            'done' => $done,
            'total' => count($steps),
            'state' => $this->state($marks, $done === count($steps)),
            'audience' => $audience,
        ];
    }

    /**
     * Which list a person gets: 'owner', 'co_owner', 'member', or null when
     * they have no access to the blog.
     */
    private function audience(BlogResource $blog, int $userId): ?string
    {
        if ($blog->ownerId() === $userId) {
            return 'owner';
        }

        if (!$this->blogs->userCanAccessBlog($userId, $blog->id())) {
            return null;
        }

        // Only the owner bundle and co-owner roles may delete the blog, which
        // separates someone who runs it from an editor who works on it.
        return $blog->userCan($userId, 'delete_own_blog') ? 'co_owner' : 'member';
    }

    /**
     * The blog's own setup, shared by the owner and co-owners. Steps that
     * lead to a page the person's role can't open are left out.
     *
     * @param  array<string, true>  $marks
     * @return SetupStep[]
     */
    private function ownerSteps(BlogResource $blog, int $userId, array $marks): array
    {
        $facts = $this->facts->forOwner($blog->id(), $blog->ownerId());
        $base = '/dashboard/blog/'.$blog->id();

        $steps = [
            $this->step('start', true, null, false),
            $this->step('first_post', $facts['published_post'], '/dashboard/post/new', true, 'setup.steps.first_post.done'),
        ];

        if ($blog->userCan($userId, 'edit_own_blog')) {
            $steps[] = $this->step('look', isset($marks['look_chosen']) || $facts['has_look'], $base.'/appearance');
        }

        $steps[] = $this->step('share', isset($marks['link_copied']), null);

        if ($blog->userCan($userId, 'manage_team')) {
            $steps[] = $this->step('followers', $facts['has_subscriber'] || isset($marks['subscribers_seen']), $base.'/subscribers');
            $steps[] = $this->step('team', $facts['has_team'], $base.'/team');
        }

        return $steps;
    }

    /**
     * @param  array<string, true>  $marks
     * @return SetupStep[]
     */
    private function memberSteps(BlogResource $blog, int $userId, array $marks): array
    {
        $facts = $this->facts->forMember($userId, $blog->id());
        $steps = [];
        $writes = $blog->userCan($userId, 'create_posts');

        if ($writes) {
            $canPublish = $blog->canPublish($userId);
            // Without review, someone who can't publish has only a Save button, so a draft is as far as they can go.
            $wrote = $facts['wrote_post'] || (!$canPublish && !$blog->reviewsPosts() && $facts['saved_draft']);
            $steps[] = $this->step('member_first_post', $wrote, '/dashboard/post/new');

            if ($blog->reviewsPosts() && !$canPublish) {
                $steps[] = $this->step('member_review', $facts['submitted_post'], null);
            }
        }

        $profileDone = $facts['has_profile'] || isset($marks['profile_saved']);
        $steps[] = $this->step($writes ? 'member_profile' : 'reviewer_profile', $profileDone, '/account/profile');

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
