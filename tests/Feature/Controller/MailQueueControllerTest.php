<?php

declare(strict_types=1);

use App\Controllers\Admin\MailQueueController;
use App\Models\MailQueueModel;
use App\Models\ScheduledTaskModel;
use App\Models\UserModel;
use App\Services\MailQueueService;
use Framework\Core\Response;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\UserFactory;

/**
 * Feature tests for MailQueueController's state-changing actions.
 *
 * Cancel, retry, and resend are each scoped to exactly one source status at
 * the model layer; these tests exist to prove the controller cannot be used
 * to route around that, whether the id comes from a single-row form or the
 * bulk endpoint.
 */
beforeEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }

    $this->db = \Framework\Core\App::container()->get(\Framework\Database::class);

    if (!$this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->beginTransaction();
    }

    $_SESSION = [];
    auth()->logout();

    $this->userModel = new UserModel($this->db);
    $this->queue = new MailQueueModel($this->db);

    $password = 'password123';
    $email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new($this->userModel)
        ->withAttributes(['email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT)])
        ->create();

    expect(auth()->login($email, $password))->toBeTrue();

    $container = \Framework\Core\App::container();
    $this->controller = new MailQueueController(
        new Response(),
        $this->queue,
        $container->get(MailQueueService::class),
        new ScheduledTaskModel($this->db),
    );

    $this->mockViewer = new class() implements TemplateViewerInterface
    {
        public ?string $capturedTemplate = null;

        /** @var array<string,mixed> */
        public array $capturedData = [];

        public function render(string $template, array $data = []): string
        {
            $this->capturedTemplate = $template;
            $this->capturedData = $data;

            return 'mocked view';
        }

        public function addGlobals(array $vars): void {}

        public function compiledViewStats(): array
        {
            return [];
        }

        public function pruneCompiledViews(int $maxAgeSeconds): int
        {
            return 0;
        }

        public function clearCompiledViews(): array
        {
            return [];
        }
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

/** Run a request through the controller with a real, session-matched CSRF token. */
function csrfPost(MailQueueController $controller, TemplateViewerInterface $viewer, string $uri, array $post = []): \Framework\Core\Request
{
    $request = makeRequest($uri, 'POST', array_merge(['_token' => csrf()->getToken()], $post));
    setupController($controller, $request, $viewer);

    return $request;
}

// ============================================================================
// cancel()
// ============================================================================

it('cancels a pending email', function () {
    $id = $this->queue->enqueue(['to_email' => 'a@b.test', 'subject' => 's', 'body_html' => 'h']);

    csrfPost($this->controller, $this->mockViewer, "/admin/mail-queue/{$id}/cancel");
    $response = $this->controller->cancel((string) $id);

    expect($response->getStatusCode())->toBe(302);

    $row = $this->queue->find($id);
    expect($row['status'])->toBe('cancelled')
        ->and((int) $row['cancelled_by'])->toBe($this->userId)
        ->and($row['cancelled_at'])->not->toBeNull();
});

it('does not cancel an email that already sent', function () {
    $id = $this->queue->enqueue(['to_email' => 'a@b.test', 'subject' => 's', 'body_html' => 'h']);
    $this->queue->markSent($id);

    csrfPost($this->controller, $this->mockViewer, "/admin/mail-queue/{$id}/cancel");
    $this->controller->cancel((string) $id);

    expect($this->queue->find($id)['status'])->toBe('sent');
});

// ============================================================================
// resend()
// ============================================================================

it('resends a sent email as a fresh queued row', function () {
    $id = $this->queue->enqueue(['to_email' => 'reader@example.test', 'subject' => 'Welcome', 'body_html' => '<p>Hi</p>']);
    $this->queue->markSent($id);

    csrfPost($this->controller, $this->mockViewer, "/admin/mail-queue/{$id}/resend");
    $response = $this->controller->resend((string) $id);

    expect($response->getStatusCode())->toBe(302);

    $newRow = $this->db->query('SELECT * FROM mail_queue WHERE resent_from_id = ?', [$id])->fetch(\PDO::FETCH_ASSOC);

    expect($newRow)->not->toBeFalse()
        ->and($newRow['status'])->toBe('pending')
        ->and($newRow['to_email'])->toBe('reader@example.test')
        ->and($newRow['subject'])->toBe('Welcome');
});

it('does not resend an email that is still pending', function () {
    $id = $this->queue->enqueue(['to_email' => 'a@b.test', 'subject' => 's', 'body_html' => 'h']);

    csrfPost($this->controller, $this->mockViewer, "/admin/mail-queue/{$id}/resend");
    $this->controller->resend((string) $id);

    $copyExists = (bool) $this->db->query('SELECT 1 FROM mail_queue WHERE resent_from_id = ?', [$id])->fetchColumn();
    expect($copyExists)->toBeFalse();
});

// ============================================================================
// restore()
// ============================================================================

it('puts a cancelled email back in the queue', function () {
    $id = $this->queue->enqueue(['to_email' => 'a@b.test', 'subject' => 's', 'body_html' => 'h']);
    $this->queue->cancel($id, $this->userId);

    csrfPost($this->controller, $this->mockViewer, "/admin/mail-queue/{$id}/restore");
    $response = $this->controller->restore((string) $id);

    expect($response->getStatusCode())->toBe(302);

    $row = $this->queue->find($id);
    expect($row['status'])->toBe('pending')
        ->and($row['cancelled_at'])->toBeNull()
        ->and($row['cancelled_by'])->toBeNull();
});

it('does not restore an email that was never cancelled', function () {
    $id = $this->queue->enqueue(['to_email' => 'a@b.test', 'subject' => 's', 'body_html' => 'h']);
    $this->queue->markSent($id);

    csrfPost($this->controller, $this->mockViewer, "/admin/mail-queue/{$id}/restore");
    $this->controller->restore((string) $id);

    expect($this->queue->find($id)['status'])->toBe('sent');
});

// ============================================================================
// bulk()
// ============================================================================

it('applies cancel only to the selected rows that are pending', function () {
    $pending = $this->queue->enqueue(['to_email' => 'a@b.test', 'subject' => 's', 'body_html' => 'h']);
    $sentId = $this->queue->enqueue(['to_email' => 'a@b.test', 'subject' => 's', 'body_html' => 'h']);
    $this->queue->markSent($sentId);

    csrfPost($this->controller, $this->mockViewer, '/admin/mail-queue/bulk', [
        'bulk_action' => 'cancel',
        'mail_ids' => [$pending, $sentId],
    ]);
    $response = $this->controller->bulk();

    expect($response->getStatusCode())->toBe(302)
        ->and($this->queue->find($pending)['status'])->toBe('cancelled')
        ->and($this->queue->find($sentId)['status'])->toBe('sent');
});

it('applies retry only to the selected rows that have failed', function () {
    $failedId = $this->queue->enqueue(['to_email' => 'a@b.test', 'subject' => 's', 'body_html' => 'h', 'max_attempts' => 1]);
    $this->queue->markFailed($failedId, 'boom', 0);
    $pendingId = $this->queue->enqueue(['to_email' => 'a@b.test', 'subject' => 's', 'body_html' => 'h']);

    csrfPost($this->controller, $this->mockViewer, '/admin/mail-queue/bulk', [
        'bulk_action' => 'retry',
        'mail_ids' => [$failedId, $pendingId],
    ]);
    $this->controller->bulk();

    expect($this->queue->find($failedId)['status'])->toBe('pending')
        ->and((int) $this->queue->find($failedId)['attempts'])->toBe(0);
});

it('applies resend only to the selected rows that are sent', function () {
    $sentId = $this->queue->enqueue(['to_email' => 'a@b.test', 'subject' => 's', 'body_html' => 'h']);
    $this->queue->markSent($sentId);
    $pendingId = $this->queue->enqueue(['to_email' => 'a@b.test', 'subject' => 's', 'body_html' => 'h']);

    csrfPost($this->controller, $this->mockViewer, '/admin/mail-queue/bulk', [
        'bulk_action' => 'resend',
        'mail_ids' => [$sentId, $pendingId],
    ]);
    $this->controller->bulk();

    $resentCount = (int) $this->db->query('SELECT COUNT(*) FROM mail_queue WHERE resent_from_id IN (?, ?)', [$sentId, $pendingId])->fetchColumn();
    expect($resentCount)->toBe(1);
});

it('applies restore only to the selected rows that are cancelled', function () {
    $cancelledId = $this->queue->enqueue(['to_email' => 'a@b.test', 'subject' => 's', 'body_html' => 'h']);
    $this->queue->cancel($cancelledId, $this->userId);
    $sentId = $this->queue->enqueue(['to_email' => 'a@b.test', 'subject' => 's', 'body_html' => 'h']);
    $this->queue->markSent($sentId);

    csrfPost($this->controller, $this->mockViewer, '/admin/mail-queue/bulk', [
        'bulk_action' => 'restore',
        'mail_ids' => [$cancelledId, $sentId],
    ]);
    $this->controller->bulk();

    expect($this->queue->find($cancelledId)['status'])->toBe('pending')
        ->and($this->queue->find($sentId)['status'])->toBe('sent');
});

it('applies a bulk action to every row matching the filter, not just the page', function () {
    $mine = $this->queue->enqueue(['to_email' => 'wide@example.test', 'subject' => 's', 'body_html' => 'h']);
    $alsoMine = $this->queue->enqueue(['to_email' => 'wide@example.test', 'subject' => 's', 'body_html' => 'h']);
    $other = $this->queue->enqueue(['to_email' => 'narrow@example.test', 'subject' => 's', 'body_html' => 'h']);

    // No ids at all: the filter is what says which rows are meant.
    csrfPost($this->controller, $this->mockViewer, '/admin/mail-queue/bulk', [
        'bulk_action' => 'cancel',
        'select_scope' => 'filter',
        'status' => 'pending',
        'q' => 'wide@',
    ]);
    $response = $this->controller->bulk();

    expect($response->getStatusCode())->toBe(302)
        ->and($this->queue->find($mine)['status'])->toBe('cancelled')
        ->and($this->queue->find($alsoMine)['status'])->toBe('cancelled')
        ->and($this->queue->find($other)['status'])->toBe('pending');
});

it('drops a filter value the form was not allowed to send rather than trusting it', function () {
    $pending = $this->queue->enqueue(['to_email' => 'forged@example.test', 'subject' => 's', 'body_html' => 'h']);

    csrfPost($this->controller, $this->mockViewer, '/admin/mail-queue/bulk', [
        'bulk_action' => 'cancel',
        'select_scope' => 'filter',
        // Neither is a real status or tier, so both are dropped and the search
        // is left to decide the scope on its own.
        'status' => "' OR 1=1 --",
        'tier' => 'made-up',
        'q' => 'forged@',
    ]);
    $this->controller->bulk();

    expect($this->queue->find($pending)['status'])->toBe('cancelled');
});

it('rejects a bulk action outside the allowed set and touches nothing', function () {
    $id = $this->queue->enqueue(['to_email' => 'a@b.test', 'subject' => 's', 'body_html' => 'h']);

    csrfPost($this->controller, $this->mockViewer, '/admin/mail-queue/bulk', [
        'bulk_action' => 'delete_everything',
        'mail_ids' => [$id],
    ]);
    $response = $this->controller->bulk();

    expect($response->getStatusCode())->toBe(302)
        ->and($this->queue->find($id)['status'])->toBe('pending');
});

it('does nothing when no rows are selected', function () {
    csrfPost($this->controller, $this->mockViewer, '/admin/mail-queue/bulk', [
        'bulk_action' => 'cancel',
        'mail_ids' => [],
    ]);
    $response = $this->controller->bulk();

    expect($response->getStatusCode())->toBe(302);
});

// ============================================================================
// preview() / renderHtml()
// ============================================================================

it('shows a queued email on the preview page', function () {
    $id = $this->queue->enqueue(['to_email' => 'reader@example.test', 'subject' => 'Preview me', 'body_html' => '<p>Body</p>']);

    $request = makeRequest("/admin/mail-queue/{$id}/preview", 'GET');
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->preview((string) $id);

    expect($response->getStatusCode())->toBe(200)
        ->and($this->mockViewer->capturedTemplate)->toBe('areas/admin/MailQueue/preview.lex.php')
        ->and($this->mockViewer->capturedData['entry']['subject'])->toBe('Preview me');
});

it('redirects with a flash message when previewing an id that does not exist', function () {
    $request = makeRequest('/admin/mail-queue/999999/preview', 'GET');
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->preview('999999');

    expect($response->getStatusCode())->toBe(302);
});

it('serves the raw stored HTML for the preview iframe', function () {
    $id = $this->queue->enqueue(['to_email' => 'a@b.test', 'subject' => 's', 'body_html' => '<p>Raw body</p>']);

    $request = makeRequest("/admin/mail-queue/{$id}/preview/render", 'GET');
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->renderHtml((string) $id);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getBody())->toBe('<p>Raw body</p>');
});

it('returns 404 html for a render request on a missing id', function () {
    $request = makeRequest('/admin/mail-queue/999999/preview/render', 'GET');
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->renderHtml('999999');

    expect($response->getStatusCode())->toBe(404);
});

it('sandboxes the rendered HTML so a stored script cannot run even on direct navigation', function () {
    $id = $this->queue->enqueue(['to_email' => 'a@b.test', 'subject' => 's', 'body_html' => '<script>alert(1)</script>']);

    $request = makeRequest("/admin/mail-queue/{$id}/preview/render", 'GET');
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->renderHtml((string) $id);

    // The iframe's own sandbox attribute only applies when framed; this header
    // is what stops the same script from running if the URL is opened directly.
    expect((string) $response->getHeader('Content-Security-Policy'))->toContain('sandbox')
        ->and($response->getHeader('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->getBody())->toBe('<script>alert(1)</script>');
});
