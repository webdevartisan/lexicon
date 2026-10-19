<?php

declare(strict_types=1);

namespace App\Traits;

use Framework\Core\Response;

/**
 * Renders an account page either as a full front page or as the bare panel the
 * account dialog fetches.
 *
 * Both come from the same panel partial, so the form exists once. The dialog
 * sends X-Account-Panel on every request; fetch keeps custom headers across a
 * same-origin redirect, so a save that redirects back to its page lands on the
 * panel again instead of a full page.
 */
trait RendersAccountPanel
{
    /**
     * Whether the account dialog asked for this request.
     */
    protected function wantsAccountPanel(): bool
    {
        return $this->request->header('X-Account-Panel') === '1';
    }

    /**
     * Render an account page, or only its panel for the dialog.
     *
     * @param  string  $page  Page key: the view is public.Account.{page}, the panel partials/account/_{page}.lex.php
     * @param  array<string, mixed>  $data  View data
     */
    protected function accountView(string $page, array $data): Response
    {
        if ($this->wantsAccountPanel()) {
            return $this->view('partials/account/_'.$page.'.lex.php', $data);
        }

        return $this->view('public.Account.'.$page, $data);
    }
}
