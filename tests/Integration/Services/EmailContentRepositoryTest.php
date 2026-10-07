<?php

declare(strict_types=1);

use App\Mail\Mailable;
use App\Mail\NewPostMail;
use App\Mail\PasswordResetEmail;
use App\Mail\Templates\ShippedEmailSource;
use App\Models\EmailContentModel;
use App\Models\EmailLayoutModel;
use App\Models\EmailSettingModel;
use App\Models\MailQueueModel;
use App\Models\NotificationModel;
use App\Models\UserModel;
use App\Services\AdminNotificationDispatcher;
use App\Services\EmailContentRepository;
use App\Services\EmailManager;
use App\Services\EmailRenderer;
use App\Services\EmailTemplateRegistry;
use App\Services\MailQueueService;
use App\Services\MailService;
use Framework\Core\App;
use Tests\Factories\UserFactory;

/**
 * What is saved in the control panel, laid over the shipped files, end to
 * end: saved words and layouts reach the mail queue, other languages exist
 * only as rows, deleting a row resets it, and a saved version that cannot be
 * built sends the shipped one instead of losing the email.
 */
beforeEach(function () {
    $_ENV['APP_URL'] = 'https://example.test';
    $_ENV['APP_NAME'] = 'Lexicon';

    $this->repository = new EmailContentRepository(
        new ShippedEmailSource(),
        new EmailLayoutModel($this->db),
        new EmailSettingModel($this->db),
        new EmailContentModel($this->db),
    );
    $this->adminId = UserFactory::new(new UserModel($this->db))->admin()->create();

    // The renderer the app installs at bootstrap, built on this test's repository.
    $this->fallbacks = [];
    Mailable::resolveTemplatesUsing(fn () => new EmailRenderer(
        $this->repository,
        true,
        $this->repository->shipped(),
        function (string $what, Throwable $e): void {
            $this->fallbacks[] = $what;
            (new AdminNotificationDispatcher(new NotificationModel($this->db), new UserModel($this->db)))
                ->dispatch('admin.email_template_failed', 'manage_email_templates', ['email' => $what, 'error' => $e->getMessage()], 60);
        },
    ));

    $this->newPost = fn (): NewPostMail => new NewPostMail('reader@example.test', 'Travel Stories', 'Ten Beaches', 'travel', 'ten-beaches', str_repeat('ab', 32));
    $this->saveContent = function (string $class, string $locale, array $fields): void {
        $this->db->execute(
            'INSERT INTO email_contents (mailable_class, locale, subject, preheader, body, footer_note, repeat_html) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$class, $locale, $fields['subject'] ?? 'Subject', $fields['preheader'] ?? '', $fields['body'] ?? '<tr><td>Body</td></tr>', $fields['footer_note'] ?? '', $fields['repeat'] ?? null]
        );
        $this->repository->flush();
    };
});

afterEach(function () {
    Mailable::resolveTemplatesUsing(null);
});

test('with empty tables every email is as shipped', function () {
    expect($this->repository->locales(NewPostMail::class))->toBe(['en'])
        ->and($this->repository->content(NewPostMail::class, 'en')['source'])->toBe('built-in')
        ->and($this->repository->layout('default')['source'])->toBe('built-in')
        ->and($this->repository->layoutFor(NewPostMail::class))->toBe('default');
});

test('saved English words reach the mail queue, and deleting the row resets them', function () {
    ($this->saveContent)(NewPostMail::class, 'en', [
        'subject' => 'Fresh from {{ blog_name }}: {{ post_title }}',
        'body' => '<tr><td><a href="{{ post_url }}">Have a look</a></td></tr>',
    ]);

    $queue = new MailQueueService(new MailQueueModel($this->db), App::container()->get(MailService::class));
    $queue->enqueue(($this->newPost)(), 'post', 1);
    $row = $this->db->query('SELECT * FROM mail_queue ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);

    expect($row['subject'])->toBe('Fresh from Travel Stories: Ten Beaches')
        ->and($row['body_html'])->toContain('Have a look')
        ->and($row['body_text'])->toContain('Have a look (https://example.test/blog/travel/ten-beaches)')
        ->and($this->repository->content(NewPostMail::class, 'en')['source'])->toBe('customized')
        ->and($this->fallbacks)->toBe([]);

    $this->db->execute('DELETE FROM email_contents');
    $this->repository->flush();

    expect(($this->newPost)()->getSubject())->toBe('New on Travel Stories: Ten Beaches');
});

test('another language exists only once it is written, and its readers get it', function () {
    expect(Mailable::inLocale('el', fn () => ($this->newPost)())->getLocale())->toBe('en');

    ($this->saveContent)(NewPostMail::class, 'el', ['subject' => 'Νέο στο {{ blog_name }}']);

    $greek = Mailable::inLocale('el', fn () => ($this->newPost)());

    expect($this->repository->locales(NewPostMail::class))->toBe(['el', 'en'])
        ->and($this->repository->content(NewPostMail::class, 'el')['source'])->toBe('custom')
        ->and($greek->getLocale())->toBe('el')
        ->and($greek->getSubject())->toBe('Νέο στο Travel Stories')
        ->and($greek->getBody())->toContain('<html lang="el"');
});

test('a saved layout overrides the shipped one, and an email can be moved to a new layout', function () {
    $this->db->execute(
        "INSERT INTO email_layouts (slug, name, html, primary_color) VALUES ('default', 'Default', '<html><body style=\"color:{{ primary_color }}\">{{ content }}{{ footer_note }}</body></html>', '#16A34A'),
                                                                     ('plain', 'Plain', '<html><body class=\"plain\">{{ content }}{{ footer_note }}</body></html>', '#000000')"
    );
    $this->db->execute('INSERT INTO email_settings (mailable_class, layout_slug) VALUES (?, ?)', [PasswordResetEmail::class, 'plain']);
    $this->repository->flush();

    $reset = new PasswordResetEmail(['email' => 'jo@example.test', 'handle' => 'jo'], 'tok');

    expect(($this->newPost)()->getBody())->toContain('style="color:#16A34A"')
        ->and($reset->getBody())->toContain('<body class="plain">')
        ->and($this->repository->layout('default')['source'])->toBe('customized')
        ->and($this->repository->layout('plain')['source'])->toBe('custom');
});

test('a saved version that cannot be built sends the shipped one and tells the admins', function () {
    // Written straight to the table, past any checks, the way a hand-edited
    // row or a half-applied deploy could leave it.
    ($this->saveContent)(NewPostMail::class, 'en', ['body' => '<tr><td>{{ headline_typo }}</td></tr>']);

    $mail = ($this->newPost)();

    expect($mail->getSubject())->toBe('New on Travel Stories: Ten Beaches')
        ->and($this->fallbacks)->toBe(['NewPostMail']);

    $notice = $this->db->query("SELECT * FROM notifications WHERE type = 'admin.email_template_failed'")->fetch(PDO::FETCH_ASSOC);
    expect($notice)->not->toBeFalse()
        ->and((int) $notice['user_id'])->toBe($this->adminId)
        ->and($notice['data'])->toContain('headline_typo');
});

test('the control panel sees which saved versions cannot be built, per language', function () {
    ($this->saveContent)(NewPostMail::class, 'el', ['body' => '<tr><td>{{ headline_typo }}</td></tr>']);

    $manager = new EmailManager($this->repository, new EmailTemplateRegistry());
    $problems = $manager->problems();

    expect(array_keys($problems))->toBe([NewPostMail::class])
        ->and(array_keys($problems[NewPostMail::class]))->toBe(['el'])
        ->and($problems[NewPostMail::class]['el'])->toContain('headline_typo');
});
