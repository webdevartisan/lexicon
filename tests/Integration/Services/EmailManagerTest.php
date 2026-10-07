<?php

declare(strict_types=1);

use App\Mail\InsightsDigestMail;
use App\Mail\NewPostMail;
use App\Mail\PasswordResetEmail;
use App\Models\UserModel;
use Tests\Factories\UserFactory;

/**
 * What the control panel may save. Nothing goes in that would stop an email
 * from being built, every save is checked through the code that sends email,
 * and resets and deletes leave the shipped files in charge again.
 */
beforeEach(function () {
    $_ENV['APP_URL'] = 'https://example.test';
    $_ENV['APP_NAME'] = 'Lexicon';

    $this->repository = emailRepositoryFor($this->db);
    $this->manager = emailManagerFor($this->repository, $this->db);
    $this->adminId = UserFactory::new(new UserModel($this->db))->admin()->create();
    $this->newPost = $this->manager->email('NewPostMail');

    $this->words = fn (array $overrides = []): array => $overrides + [
        'subject' => 'Νέο στο {{ blog_name }}: {{ post_title }}',
        'preheader' => '',
        'body' => '<tr><td><a href="{{ post_url }}">Διάβασέ το</a></td></tr>',
        'footer_note' => '<a href="{{ unsubscribe_url }}">Διαγραφή</a>',
        'layout' => 'default',
    ];
});

test('a new language is saved, checked by building it, and its readers get it', function () {
    $result = $this->manager->saveContent($this->newPost, 'el', ($this->words)(), $this->adminId);

    expect($result['ok'])->toBeTrue()
        ->and($this->repository->locales(NewPostMail::class))->toBe(['el', 'en'])
        ->and($this->manager->buildSample($this->newPost, 'el')->getSubject())->toBe('Νέο στο Travel Stories: Ten Hidden Beaches in Crete')
        ->and((int) $this->db->query("SELECT COUNT(*) FROM activity_log WHERE action = 'email.content_saved'")->fetchColumn())->toBe(1);
});

test('words that refer to data the email does not have are refused, and nothing is saved', function () {
    $result = $this->manager->saveContent($this->newPost, 'el', ($this->words)(['body' => '<tr><td>{{ author_name }}</td></tr>']), $this->adminId);

    expect($result['ok'])->toBeFalse()
        ->and(implode(' ', $result['errors']))->toContain('{{ author_name }}')
        ->and($this->repository->storedContent(NewPostMail::class, 'el'))->toBeNull();
});

test('scripts, an empty subject and an unknown layout are refused before anything is built', function () {
    $result = $this->manager->saveContent($this->newPost, 'en', ($this->words)([
        'subject' => '  ',
        'body' => '<tr><td onclick="x()">{{ post_title }}</td></tr><script>alert(1)</script>',
        'layout' => 'nope',
    ]), $this->adminId);
    $errors = implode(' ', $result['errors']);

    expect($result['ok'])->toBeFalse()
        ->and($errors)->toContain('subject cannot be empty')
        ->and($errors)->toContain('<script>')
        ->and($errors)->toContain('Event handler')
        ->and($errors)->toContain("no layout 'nope'");
});

test('a language the site does not offer cannot be written', function () {
    expect($this->manager->saveContent($this->newPost, 'xx', ($this->words)(), $this->adminId)['ok'])->toBeFalse();
});

test('the repeated section is part of the words only for an email that repeats one', function () {
    $digest = $this->manager->email('InsightsDigestMail');
    $english = $this->repository->content(InsightsDigestMail::class, 'en');
    $input = ['subject' => 'Your week', 'preheader' => '', 'body' => $english['body'], 'footer_note' => '', 'layout' => 'default'];

    $missing = $this->manager->saveContent($digest, 'el', $input + ['repeat' => ''], $this->adminId);
    $saved = $this->manager->saveContent($digest, 'el', $input + ['repeat' => '<tr><td>{{ blog_name }}: {{ views }}</td></tr>'], $this->adminId);
    $ignored = $this->manager->saveContent($this->newPost, 'el', ($this->words)(['repeat' => '<tr><td>x</td></tr>']), $this->adminId);

    expect($missing['ok'])->toBeFalse()
        ->and($saved['ok'])->toBeTrue()
        ->and($this->manager->buildSample($digest, 'el')->getBody())->toContain('Travel Stories: 1.240')
        ->and($ignored['item']['repeat'])->toBeNull();
});

test('choosing a layout is saved for every language, and choosing the shipped one again clears it', function () {
    $this->db->execute("INSERT INTO email_layouts (slug, name, html) VALUES ('plain', 'Plain', '<html><body class=\"plain\">{{ content }}{{ footer_note }}</body></html>')");
    $this->repository->flush();

    $english = $this->repository->content(NewPostMail::class, 'en');
    $input = ['subject' => $english['subject'], 'preheader' => $english['preheader'], 'body' => $english['body'], 'footer_note' => $english['footer_note']];

    $this->manager->saveContent($this->newPost, 'en', $input + ['layout' => 'plain'], $this->adminId);
    expect($this->repository->layoutFor(NewPostMail::class))->toBe('plain');

    $this->manager->saveContent($this->newPost, 'en', $input + ['layout' => 'default'], $this->adminId);
    expect($this->repository->layoutFor(NewPostMail::class))->toBe('default')
        ->and((int) $this->db->query('SELECT COUNT(*) FROM email_settings')->fetchColumn())->toBe(0);
});

test('resetting English goes back to the file; deleting a translation stops it being written', function () {
    $this->manager->saveContent($this->newPost, 'en', ($this->words)(['subject' => 'Changed {{ post_title }}']), $this->adminId);
    $this->manager->saveContent($this->newPost, 'el', ($this->words)(), $this->adminId);

    expect($this->manager->resetContent($this->newPost, 'en', $this->adminId)['ok'])->toBeTrue()
        ->and($this->manager->resetContent($this->newPost, 'el', $this->adminId)['ok'])->toBeTrue()
        ->and($this->manager->resetContent($this->newPost, 'el', $this->adminId)['ok'])->toBeFalse()
        ->and($this->repository->locales(NewPostMail::class))->toBe(['en'])
        ->and($this->repository->content(NewPostMail::class, 'en')['source'])->toBe('built-in');
});

test('a translation is flagged once the English is changed after it', function () {
    $this->manager->saveContent($this->newPost, 'el', ($this->words)(), $this->adminId);
    expect($this->manager->languages($this->newPost)['el']['outdated'])->toBeFalse();

    $this->db->execute("UPDATE email_contents SET updated_at = '2026-01-01 00:00:00' WHERE locale = 'el'");
    $this->manager->saveContent($this->newPost, 'en', ($this->words)(['subject' => 'Changed {{ post_title }}']), $this->adminId);

    expect($this->manager->languages($this->newPost)['el']['outdated'])->toBeTrue()
        ->and($this->manager->languages($this->newPost)['en']['outdated'])->toBeFalse();
});

test('a layout needs its content and footer spots, and may not break an email already using it', function () {
    $default = $this->repository->layout('default');
    $input = ['name' => 'Default', 'primary_color' => '#16a34a', 'background_color' => '#FFFFFF', 'support_email' => '', 'company_address' => '1 Harbour Road'];

    $noContent = $this->manager->saveLayout('default', $input + ['html' => '<html><body>{{ footer_note }}</body></html>'], $this->adminId);
    $breaks = $this->manager->saveLayout('default', $input + ['html' => str_replace('{{ year }}', '{{ fiscal_year }}', $default['html'])], $this->adminId);
    $fine = $this->manager->saveLayout('default', $input + ['html' => $default['html']], $this->adminId);

    expect($noContent['ok'])->toBeFalse()
        ->and(implode(' ', $noContent['errors']))->toContain('{{ content }}')
        ->and($breaks['ok'])->toBeFalse()
        ->and(implode(' ', $breaks['errors']))->toContain('{{ fiscal_year }}')
        ->and($fine['ok'])->toBeTrue()
        ->and($this->repository->layout('default')['source'])->toBe('customized')
        ->and($this->repository->layout('default')['primary_color'])->toBe('#16A34A')
        ->and($this->manager->buildSample($this->newPost, 'en')->getBody())->toContain('1 Harbour Road');
});

test('a new layout needs a free slug and valid settings, and a shipped one is reset rather than deleted', function () {
    $html = '<html><body>{{ content }}{{ footer_note }}</body></html>';
    $bad = $this->manager->saveLayout(null, ['slug' => 'Bad Slug', 'name' => '', 'html' => $html, 'primary_color' => 'blue', 'background_color' => '#FFFFFF', 'support_email' => 'nope'], $this->adminId);
    $taken = $this->manager->saveLayout(null, ['slug' => 'default', 'name' => 'Again', 'html' => $html, 'primary_color' => '#000000', 'background_color' => '#FFFFFF'], $this->adminId);
    $plain = $this->manager->saveLayout(null, ['slug' => 'plain', 'name' => 'Plain', 'html' => $html, 'primary_color' => '#000000', 'background_color' => '#FFFFFF'], $this->adminId);

    expect(count($bad['errors']))->toBeGreaterThanOrEqual(4)
        ->and(implode(' ', $taken['errors']))->toContain('already exists')
        ->and($plain['ok'])->toBeTrue()
        ->and($this->repository->layout('plain')['source'])->toBe('custom')
        ->and($this->manager->deleteLayout('default', $this->adminId)['ok'])->toBeFalse()
        ->and($this->manager->resetLayout('plain', $this->adminId)['ok'])->toBeFalse();
});

test('a layout in use cannot be deleted until its emails move', function () {
    $html = '<html><body>{{ content }}{{ footer_note }}</body></html>';
    $this->manager->saveLayout(null, ['slug' => 'plain', 'name' => 'Plain', 'html' => $html, 'primary_color' => '#000000', 'background_color' => '#FFFFFF'], $this->adminId);
    $english = $this->repository->content(PasswordResetEmail::class, 'en');
    $reset = $this->manager->email('PasswordResetEmail');
    $input = ['subject' => $english['subject'], 'preheader' => $english['preheader'], 'body' => $english['body'], 'footer_note' => $english['footer_note']];
    $this->manager->saveContent($reset, 'en', $input + ['layout' => 'plain'], $this->adminId);

    $refused = $this->manager->deleteLayout('plain', $this->adminId);
    $this->manager->saveContent($reset, 'en', $input + ['layout' => 'default'], $this->adminId);

    expect($refused['ok'])->toBeFalse()
        ->and($refused['errors'][0])->toContain('Password Reset')
        ->and($this->manager->deleteLayout('plain', $this->adminId)['ok'])->toBeTrue()
        ->and($this->repository->layout('plain'))->toBeNull();
});
