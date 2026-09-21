<?php

declare(strict_types=1);

use App\Models\UserModel;
use App\Services\ImpersonationService;
use Framework\Security\Csrf;
use Framework\Session;
use Tests\Factories\UserFactory;

/**
 * "Log in as": who may, who may not, and that the admin always gets their own
 * session back with every step on the record.
 *
 * Sessions are disabled under the CLI, so Session reads and writes $_SESSION
 * directly and regenerate() is a no-op; the id rotation itself is covered by
 * the manual verification, the session contents by these tests.
 */
beforeEach(function () {
    $_SESSION = [];

    $this->users = new UserModel($this->db);
    $this->session = new Session();
    $this->csrf = new Csrf($this->session);
    $this->service = new ImpersonationService($this->session, $this->db, $this->users, $this->csrf);

    $this->adminId = UserFactory::new($this->users)->admin()->create();
    $this->otherAdminId = UserFactory::new($this->users)->admin()->create();
    $this->targetId = UserFactory::new($this->users)->create();

    $managerRole = $this->db->query("SELECT id FROM roles WHERE role_slug = 'content_manager'")->fetchColumn();
    $this->managerId = UserFactory::new($this->users)->withRoles([(int) $managerRole])->create();

    // Signed in as the administrator, the way Auth::login() leaves the session.
    $this->session->set('user_id', $this->adminId);
    $this->session->set('session_epoch', 0);
});

afterEach(function () {
    $_SESSION = [];
});

function actorRow(UserModel $users, int $id): array
{
    return ['id' => $id, 'roles' => $users->getUserRoles($id)];
}

it('refuses anyone who is not an administrator', function () {
    $this->session->set('user_id', $this->managerId);

    expect($this->service->refusalReason(actorRow($this->users, $this->managerId), $this->targetId))
        ->toBe('Only administrators can sign in as another account.');

    expect(fn () => $this->service->start(actorRow($this->users, $this->managerId), $this->targetId, 'x', null))
        ->toThrow(RuntimeException::class);

    expect($this->session->get('user_id'))->toBe($this->managerId);
});

it('re-reads the actor\'s roles instead of trusting the ones passed in', function () {
    $forged = ['id' => $this->managerId, 'roles' => ['administrator']];

    expect($this->service->refusalReason($forged, $this->targetId))
        ->toBe('Only administrators can sign in as another account.');
});

it('refuses to sign in as another administrator', function () {
    expect($this->service->refusalReason(actorRow($this->users, $this->adminId), $this->otherAdminId))
        ->toBe('You cannot sign in as another administrator.');
});

it('refuses a suspended account', function () {
    $this->db->execute('UPDATE users SET is_active = 0 WHERE id = ?', [$this->targetId]);

    expect($this->service->refusalReason(actorRow($this->users, $this->adminId), $this->targetId))
        ->toContain('suspended');
});

it('refuses to nest one impersonation inside another', function () {
    $this->service->start(actorRow($this->users, $this->adminId), $this->targetId, 'Ticket 1', '127.0.0.1');

    $secondTarget = UserFactory::new($this->users)->create();

    expect($this->service->refusalReason(actorRow($this->users, $this->adminId), $secondTarget))
        ->toContain('already signed in as someone else');
});

it('swaps the session to the target and opens a record of it', function () {
    $this->service->start(actorRow($this->users, $this->adminId), $this->targetId, 'Ticket 1', '127.0.0.1');

    $record = $this->db->query('SELECT admin_id, target_user_id, reason, ended_at FROM impersonation_sessions')->fetch();

    expect($this->session->get('user_id'))->toBe($this->targetId)
        ->and($this->service->impersonatorId())->toBe($this->adminId)
        ->and((int) $record['admin_id'])->toBe($this->adminId)
        ->and((int) $record['target_user_id'])->toBe($this->targetId)
        ->and($record['reason'])->toBe('Ticket 1')
        ->and($record['ended_at'])->toBeNull();
});

it('gives the admin their own session back on exit and closes the record', function () {
    $this->service->start(actorRow($this->users, $this->adminId), $this->targetId, 'Ticket 1', '127.0.0.1');

    expect($this->service->stop('exited', '127.0.0.1'))->toBe($this->adminId);

    $record = $this->db->query('SELECT ended_at, end_kind FROM impersonation_sessions')->fetch();

    expect($this->session->get('user_id'))->toBe($this->adminId)
        ->and($this->session->get('session_epoch'))->toBe(0)
        ->and($this->service->isImpersonating())->toBeFalse()
        ->and($record['ended_at'])->not->toBeNull()
        ->and($record['end_kind'])->toBe('exited');
});

it('issues a new CSRF token on the way in and on the way out', function () {
    $adminToken = $this->csrf->getToken();

    $this->service->start(actorRow($this->users, $this->adminId), $this->targetId, 'Ticket 1', null);
    $impersonatedToken = $this->csrf->getToken();

    $this->service->stop();
    $returnedToken = $this->csrf->getToken();

    expect($impersonatedToken)->not->toBe($adminToken)
        ->and($returnedToken)->not->toBe($impersonatedToken)
        ->and($returnedToken)->not->toBe($adminToken)
        ->and($this->csrf->isTokenValid($adminToken))->toBeFalse();
});

it('ends by itself once the time box runs out, handing the admin back', function () {
    $this->service->start(actorRow($this->users, $this->adminId), $this->targetId, 'Ticket 1', null);
    $_SESSION['impersonation_expires_at'] = time() - 1;

    expect($this->service->impersonatorId())->toBeNull()
        ->and($this->session->get('user_id'))->toBe($this->adminId)
        ->and($this->db->query('SELECT end_kind FROM impersonation_sessions')->fetchColumn())->toBe('expired');
});

it('hands the admin back when the target is dropped mid-session', function () {
    $this->service->start(actorRow($this->users, $this->adminId), $this->targetId, 'Ticket 1', null);

    // What Auth::user() does when the target is suspended or signed out everywhere.
    $this->session->remove('user_id');

    expect($this->service->impersonatorId())->toBeNull()
        ->and($this->session->get('user_id'))->toBe($this->adminId)
        ->and($this->session->has('impersonator_id'))->toBeFalse();
});

it('carries the target\'s session epoch while impersonating and restores the admin\'s after', function () {
    $this->db->execute('UPDATE users SET session_epoch = 7 WHERE id = ?', [$this->targetId]);

    $this->service->start(actorRow($this->users, $this->adminId), $this->targetId, 'Ticket 1', null);
    expect($this->session->get('session_epoch'))->toBe(7);

    $this->service->stop();
    expect($this->session->get('session_epoch'))->toBe(0);
});

it('files a write made while impersonating under the admin, naming the target', function () {
    $this->service->start(actorRow($this->users, $this->adminId), $this->targetId, 'Ticket 1', null);

    // audit() resolves the container's AuditService, which reads the same $_SESSION.
    audit()->log($this->targetId, 'comment.created', 'comment', 123, ['post_id' => 9]);

    $row = $this->db->query("SELECT user_id, details FROM activity_log WHERE action = 'comment.created'")->fetch();
    $details = json_decode((string) $row['details'], true);

    expect((int) $row['user_id'])->toBe($this->adminId)
        ->and($details['acting_as'])->toBe($this->targetId)
        ->and($details['post_id'])->toBe(9);
});

it('records the start and the end in the audit log, without the acting_as rewrite', function () {
    $this->service->start(actorRow($this->users, $this->adminId), $this->targetId, 'Ticket 1', null);
    $this->service->stop();

    $rows = $this->db->query(
        "SELECT user_id, action, details FROM activity_log WHERE action LIKE 'user.impersonation%' ORDER BY id"
    )->fetchAll();

    expect(array_column($rows, 'action'))->toBe(['user.impersonation_started', 'user.impersonation_ended'])
        ->and(array_map('intval', array_column($rows, 'user_id')))->toBe([$this->adminId, $this->adminId])
        ->and($rows[0]['details'])->not->toContain('acting_as');
});
