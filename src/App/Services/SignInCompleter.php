<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth;
use App\Services\Analytics\VisitorLink;
use Framework\Session;

/**
 * What happens once someone is signed in: the audit row, the analytics link,
 * their language, a reply they had started, and where they land.
 *
 * Shared by the password step and the second step, so both end the same way.
 */
final class SignInCompleter
{
    public function __construct(
        private Auth $auth,
        private Session $session,
        private VisitorLink $visitorLink,
        private SessionLocaleSync $localeSync,
        private CommentService $comments,
        private ReaderLocaleTarget $localeTarget,
    ) {}

    /**
     * @param  \Framework\Core\Request  $request  The request that finished the sign-in
     * @param  string|null  $returnTo  Validated path the sign-in page was opened for
     * @return array{path: string, flash: array{type: string, message: string}|null}
     */
    public function complete(\Framework\Core\Request $request, ?string $returnTo): array
    {
        $user = $this->auth->user();
        $userId = (int) $user['id'];
        $ip = $request->ip();

        audit()->log($userId, 'auth.login', 'user', $userId, [], $ip);

        $this->visitorLink->toAccount($request, $userId);

        // The session still holds the locale this visitor was browsing as a
        // guest, and every redirect below is built from it. Adopt the
        // account's language now that there is an account to read it from.
        $preferredLocale = $this->localeSync->apply($userId);

        // A reply captured before login gets posted now and wins the
        // redirect: the reader lands back on the comment they answered.
        $resume = $this->comments->resumePending($user, $ip);
        if ($resume !== null) {
            return [
                'path' => $resume['path'],
                'flash' => ['type' => $resume['ok'] ? 'success' : 'error', 'message' => $resume['message']],
            ];
        }

        // Redirect priority: blog-front return_to beats the stored
        // intended URL, and only when neither exists do we fall back to
        // the dashboard (readers land on their reading hub there).
        $intendedUrl = safe_return_to($this->session->get('intended_url'));
        $this->session->remove('intended_url');

        $target = $returnTo ?? ($intendedUrl ?: '/dashboard');

        // A prefix on one of the platform's own pages only records what the
        // last visitor was reading, which on a shared browser is somebody
        // else. A prefix on a post is part of the link and stays put.
        return ['path' => $this->localeTarget->resolve($target, $preferredLocale), 'flash' => null];
    }
}
