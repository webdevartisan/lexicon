<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogModel;
use App\Models\UserProfileModel;

/**
 * Builds the logged-in reader summary the masthead menu is drawn from.
 *
 * Every page that renders the masthead needs the same four facts, and the
 * masthead renders on the Lexicon front and inside all five blog themes, so it
 * lives here rather than being rebuilt in each controller.
 *
 * The result is memoised for the request. Both the nav globals middleware and
 * the blog controller ask for it on a theme page, and the badge count in
 * particular should cost one query however many times the menu is drawn.
 */
final class ViewerContext
{
    /**
     * @var array{name: string, avatar_url: string|null, is_reader: bool, unread_replies: int}|null|false
     *                                                                                                    False means "not resolved yet"; null is a real answer meaning nobody is signed in.
     */
    private array|null|false $current = false;

    public function __construct(
        private BlogModel $blogs,
        private UserProfileModel $profiles,
        private ReaderService $reader
    ) {}

    /**
     * Summary of the current user for masthead rendering.
     *
     * @return array{name: string, avatar_url: string|null, is_reader: bool, unread_replies: int}|null
     *                                                                                                 Null when nobody is logged in.
     */
    public function current(): ?array
    {
        if ($this->current !== false) {
            return $this->current;
        }

        if (!auth()->check()) {
            return $this->current = null;
        }

        $user = auth()->user();
        $userId = (int) $user['id'];

        return $this->current = [
            'name' => $user['display_name_cached'] ?? ($user['username'] ?? ''),
            'avatar_url' => $this->profiles->getProfileAvatar($userId)['avatar_url'] ?? null,
            // Decides which primary button the masthead shows. Deliberately not
            // the back area's isReader, which is only computed on dashboard
            // pages and is therefore always false out on the front.
            'is_reader' => $this->blogs->userIsReaderOnly($userId),
            'unread_replies' => $this->reader->unreadReplyCount($userId),
        ];
    }
}
