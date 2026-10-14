<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Models\BlogInvitationModel;
use App\Services\InviteSummary;
use Framework\Core\Response;

/**
 * The accept/decline page for a signed-in invitee. It lives under /dashboard so
 * the control panel menu, blog switcher and notification bell are the ones the
 * navigation middleware builds for that area.
 */
final class InviteController extends AppController
{
    public function __construct(
        private BlogInvitationModel $invitationModel,
        private InviteSummary $summary,
    ) {}

    /**
     * Show who invited the user, to which blog and as what (GET /dashboard/invite/{token}).
     *
     * @param  string  $token  Raw token from the email or notification link
     */
    public function show(string $token): Response
    {
        $invite = $this->invitationModel->findValidByToken(hash('sha256', $token));
        $details = $invite ? $this->summary->describe($invite) : null;

        // The public page owns the expired state, so a dead token lands there.
        if ($details === null) {
            return $this->redirect(lurl('/invite/'.$token));
        }

        $accountEmail = (string) auth()->user()['email'];

        return $this->view('dashboard.invite.accept', [
            'noBreadcrumb' => true,
            'token' => $token,
            'accountEmail' => $accountEmail,
            'wrongAccount' => strcasecmp($accountEmail, $details['invitedEmail']) !== 0,
        ] + $details);
    }
}
