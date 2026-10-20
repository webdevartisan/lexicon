<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\SignInCompleter;
use Framework\Core\Response;

/**
 * The last answer of a sign-in, for the full login page and the blog modal alike.
 *
 * Shared by the password step and the second step, so a change to how a
 * sign-in lands cannot reach one and miss the other.
 */
trait AnswersSignIn
{
    /**
     * Finish a session that has just started and tell the client where it lands.
     *
     * @param  string|null  $returnTo  Validated path the sign-in was opened for
     */
    private function finishSignIn(?string $returnTo): Response
    {
        $done = app(SignInCompleter::class)->complete($this->request, $returnTo);

        // No flash for the modal: it shows its own success state and usually
        // finishes the reader's action in place instead of navigating
        if ($this->request->isAjax()) {
            return $this->jsonSuccess(['redirect' => $done['path']]);
        }

        if ($done['flash'] !== null) {
            $this->flash($done['flash']['type'], $done['flash']['message']);
        }

        return $this->redirect($done['path']);
    }

    /**
     * Send the visitor back to the start of signing in.
     *
     * @param  string  $message  Why, already translated
     * @param  int  $status  Status the modal gets; the full page is redirected
     */
    private function backToLogin(string $message, int $status): Response
    {
        if ($this->request->isAjax()) {
            return $this->jsonError($message, $status);
        }

        $this->flash('error', $message);

        return $this->redirect('/login');
    }
}
