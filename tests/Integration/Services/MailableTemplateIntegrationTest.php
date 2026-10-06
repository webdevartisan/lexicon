<?php

declare(strict_types=1);

use App\Mail\Mailable;
use App\Mail\NewPostMail;
use App\Mail\PasswordResetEmail;
use App\Mail\Templates\CatalogTemplateSource;
use App\Models\EmailComponentModel;
use App\Models\EmailTemplateModel;
use App\Models\MailableTemplateBindingModel;
use App\Models\MailQueueModel;
use App\Models\NotificationModel;
use App\Models\UserModel;
use App\Services\AdminNotificationDispatcher;
use App\Services\AuditService;
use App\Services\EmailTemplateManager;
use App\Services\EmailTemplateRegistry;
use App\Services\EmailTemplateRepository;
use App\Services\MailQueueService;
use App\Services\MailService;
use App\Services\TemplateRendererService;
use Framework\Core\App;
use Tests\Factories\UserFactory;

/**
 * Email templates edited in the control panel, end to end: the manager refuses
 * edits that would break an email, saved edits reach the mail queue, and a
 * broken stored template degrades to the built-in design instead of losing mail.
 */
beforeEach(function () {
    $_ENV['APP_URL'] = 'https://example.test';
    $_ENV['APP_NAME'] = 'Lexicon';

    $this->repository = new EmailTemplateRepository(
        new CatalogTemplateSource(),
        new EmailComponentModel($this->db),
        new EmailTemplateModel($this->db),
        new MailableTemplateBindingModel($this->db),
    );

    $this->manager = new EmailTemplateManager(
        $this->repository,
        new EmailComponentModel($this->db),
        new EmailTemplateModel($this->db),
        new MailableTemplateBindingModel($this->db),
        new EmailTemplateRegistry(),
        App::container()->get(AuditService::class),
    );

    $this->adminId = UserFactory::new(new UserModel($this->db))->admin()->create();

    // The renderer the app installs at bootstrap, built on this test's repository.
    $this->fallbacks = [];
    Mailable::resolveTemplatesUsing(fn () => new TemplateRendererService(
        $this->repository,
        true,
        $this->repository->builtIn(),
        function (string $what, Throwable $e): void {
            $this->fallbacks[] = $what;
            (new AdminNotificationDispatcher(new NotificationModel($this->db), new UserModel($this->db)))
                ->dispatch('admin.email_template_failed', 'manage_email_templates', ['email' => $what, 'error' => $e->getMessage()], 60);
        },
    ));

    $this->button = $this->repository->component('button');
    $this->newPost = fn (): NewPostMail => new NewPostMail('reader@example.test', 'Travel Stories', 'Ten Beaches', 'travel', 'ten-beaches', str_repeat('ab', 32));
});

afterEach(function () {
    Mailable::resolveTemplatesUsing(null);
});

/**
 * @param  array<string, mixed>  $component
 * @return array<string, mixed>
 */
function componentInput(array $component, array $overrides = []): array
{
    return $overrides + [
        'slug' => $component['slug'],
        'label' => $component['label'],
        'category' => $component['category'],
        'description' => $component['description'],
        'html' => $component['html'],
        'text_mode' => $component['text'] === null ? 'auto' : ($component['text'] === '' ? 'none' : 'custom'),
        'text' => (string) $component['text'],
        'css' => $component['css'],
        'preview' => $component['preview_data'],
    ];
}

test('a customized block reaches every email using it, all the way into the mail queue', function () {
    $html = str_replace('background:#2563EB', 'background:#16A34A', $this->button['html']);
    $result = $this->manager->saveComponent('button', componentInput($this->button, ['html' => $html]), $this->adminId);

    expect($result['ok'])->toBeTrue()
        ->and($this->repository->component('button')['source'])->toBe('customized');

    $queue = new MailQueueService(new MailQueueModel($this->db), App::container()->get(MailService::class));
    $queue->enqueue(($this->newPost)(), 'post', 1);

    $row = $this->db->query('SELECT * FROM mail_queue ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);

    expect($row['body_html'])->toContain('background:#16A34A')
        ->and($row['body_html'])->not->toContain('background:#2563EB')
        ->and($row['body_text'])->toContain('Read it: https://example.test/blog/travel/ten-beaches')
        ->and($row['subject'])->toBe('New on Travel Stories: Ten Beaches')
        ->and($this->fallbacks)->toBe([]);
});

test('an edit that would leave an email unable to render is refused and nothing is saved', function () {
    $html = $this->button['html'].'<p>{{ button_caption }}</p>';
    $result = $this->manager->saveComponent('button', componentInput($this->button, ['html' => $html]), $this->adminId);

    expect($result['ok'])->toBeFalse()
        ->and(implode("\n", $result['errors']))->toContain('NewPostMail')
        ->and(implode("\n", $result['errors']))->toContain('{{ button_caption }}')
        ->and((int) $this->db->query('SELECT COUNT(*) FROM email_components')->fetchColumn())->toBe(0);
});

test('a script in a block is refused before anything is rendered', function () {
    $result = $this->manager->saveComponent(null, [
        'slug' => 'promo', 'label' => 'Promo', 'category' => 'content',
        'html' => '<div onclick="steal()">{{ promo }}</div><script>alert(1)</script>',
    ], $this->adminId);

    expect($result['ok'])->toBeFalse()
        ->and(implode("\n", $result['errors']))->toContain('<script> tags are not allowed')
        ->and(implode("\n", $result['errors']))->toContain('Event handler attributes');
});

test('an email binding changes the wording and subject without touching code', function () {
    $binding = $this->repository->binding(NewPostMail::class);
    $mapping = ['heading' => 'Fresh from {{ blog_name }}'] + $binding['mapping'];

    $result = $this->manager->saveBinding(NewPostMail::class, [
        'template' => 'notification',
        'subject' => '{{ post_title }} | {{ blog_name }}',
        'mapping' => $mapping,
        'is_active' => '1',
    ], $this->adminId);

    expect($result['ok'])->toBeTrue();

    $mail = ($this->newPost)();

    expect($mail->getSubject())->toBe('Ten Beaches | Travel Stories')
        ->and($mail->getBody())->toContain('Fresh from Travel Stories')
        ->and($mail->getTextBody())->toStartWith('Fresh from Travel Stories');
});

test('wording that refers to data the email does not have is refused', function () {
    $binding = $this->repository->binding(NewPostMail::class);

    $result = $this->manager->saveBinding(NewPostMail::class, [
        'template' => 'notification',
        'mapping' => ['heading' => 'Hi {{ first_name }}'] + $binding['mapping'],
        'is_active' => '1',
    ], $this->adminId);

    expect($result['ok'])->toBeFalse()
        ->and(implode("\n", $result['errors']))->toContain('{{ first_name }}')
        ->and($this->repository->storedBinding(NewPostMail::class))->toBeNull();
});

test('a binding switched off keeps its edits but the email goes out as built in', function () {
    $binding = $this->repository->binding(NewPostMail::class);

    $this->manager->saveBinding(NewPostMail::class, [
        'template' => 'notification',
        'mapping' => ['heading' => 'Kept for later'] + $binding['mapping'],
    ], $this->adminId);

    expect($this->repository->storedBinding(NewPostMail::class)['mapping']['heading'])->toBe('Kept for later')
        ->and(($this->newPost)()->getBody())->not->toContain('Kept for later')
        ->and(($this->newPost)()->getBody())->toContain('just published a new post');
});

test('reset brings back the shipped block', function () {
    $html = str_replace('Read', 'Read', $this->button['html']).'<!-- edited -->';
    $this->manager->saveComponent('button', componentInput($this->button, ['html' => $html]), $this->adminId);

    expect($this->manager->resetComponent('button', $this->adminId)['ok'])->toBeTrue()
        ->and($this->repository->component('button')['source'])->toBe('built-in')
        ->and(($this->newPost)()->getBody())->not->toContain('edited');
});

test('blocks and templates in use cannot be deleted, and built-in ones never can', function () {
    $this->manager->saveComponent(null, [
        'slug' => 'promo', 'label' => 'Promo', 'category' => 'content', 'html' => '<p>{{ heading }}</p>',
    ], $this->adminId);

    $this->manager->saveTemplate(null, [
        'slug' => 'plain', 'label' => 'Plain', 'category' => 'transactional', 'layout' => ['promo', 'button'],
    ], $this->adminId);

    $this->manager->saveBinding(PasswordResetEmail::class, [
        'template' => 'plain',
        'mapping' => ['heading' => 'Reset', 'button_label' => 'Go', 'button_url' => '{{ reset_url }}'],
        'is_active' => '1',
    ], $this->adminId);

    expect($this->manager->deleteComponent('promo', $this->adminId)['ok'])->toBeFalse()
        ->and($this->manager->deleteTemplate('plain', $this->adminId)['errors'][0])->toContain('PasswordResetEmail')
        ->and($this->manager->deleteComponent('button', $this->adminId)['ok'])->toBeFalse()
        ->and($this->manager->deleteTemplate('notification', $this->adminId)['ok'])->toBeFalse();

    $reset = new PasswordResetEmail(['first_name' => 'Ana', 'email' => 'ana@example.test'], 'tok');
    expect($reset->getBody())->toContain('<p>Reset</p>');

    // Once nothing uses them, they can go.
    $this->manager->resetBinding(PasswordResetEmail::class, $this->adminId);
    expect($this->manager->deleteTemplate('plain', $this->adminId)['ok'])->toBeTrue()
        ->and($this->manager->deleteComponent('promo', $this->adminId)['ok'])->toBeTrue();
});

test('a broken stored template sends the built-in design and tells the admins', function () {
    // Written straight to the table, past the manager's checks, the way a
    // hand-edited row or a half-applied deploy could leave it.
    $this->db->execute(
        "INSERT INTO email_components (slug, label, category, description, html_template) VALUES ('heading', 'Heading', 'content', '', '<h2>{{ headline_typo }}</h2>')"
    );
    $this->repository->flush();

    $mail = ($this->newPost)();

    expect($mail->getBody())->toContain('Travel Stories just published a new post')
        ->and($this->fallbacks)->toBe(['NewPostMail']);

    $notice = $this->db->query("SELECT * FROM notifications WHERE type = 'admin.email_template_failed'")->fetch(PDO::FETCH_ASSOC);
    expect($notice)->not->toBeFalse()
        ->and((int) $notice['user_id'])->toBe($this->adminId)
        ->and($notice['data'])->toContain('headline_typo');
});

test('every change is written to the audit log', function () {
    $this->manager->saveComponent('button', componentInput($this->button, ['html' => $this->button['html'].' ']), $this->adminId);
    $this->manager->resetComponent('button', $this->adminId);

    $actions = $this->db->query("SELECT action FROM activity_log WHERE action LIKE 'email_template.%' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

    expect($actions)->toBe(['email_template.component_updated', 'email_template.component_reset']);
});
