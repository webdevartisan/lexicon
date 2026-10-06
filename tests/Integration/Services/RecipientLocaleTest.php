<?php

declare(strict_types=1);

use App\Mail\Mailable;
use App\Models\BlogModel;
use App\Models\BlogSubscriberModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Services\LocaleRegistry;
use App\Services\MailQueueService;
use App\Services\RecipientLocale;
use App\Services\SubscriberNotificationService;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * The person who triggers an email is often not the one who reads it, so the
 * language comes from the reader: their choice, then the page they are on when
 * they are the one acting, then what is known about the address, then the
 * blog's language, then the site's.
 */
beforeEach(function () {
    $this->users = new UserModel($this->db);
    $this->locales = new RecipientLocale($this->db, LocaleRegistry::instance());
});

afterEach(function () {
    Mockery::close();
});

function userWithLanguage(UserModel $users, ?string $preferred, ?string $last, array $attributes = []): int
{
    $id = UserFactory::new($users)->withAttributes($attributes)->create();
    test()->db->execute('UPDATE users SET last_locale = ? WHERE id = ?', [$last, $id]);

    if ($preferred !== null) {
        test()->db->execute('INSERT INTO user_preferences (user_id, locale) VALUES (?, ?)', [$id, $preferred]);
    }

    return $id;
}

test('a chosen language wins over everything else', function () {
    $id = userWithLanguage($this->users, 'el', 'ar');

    expect($this->locales->forUser($id))->toBe('el')
        ->and($this->locales->forUser($id, readingNow: 'ar'))->toBe('el');
});

test('without a choice, the page they are reading beats the language they last signed in from', function () {
    $id = userWithLanguage($this->users, null, 'ar');

    expect($this->locales->forUser($id))->toBe('ar')
        ->and($this->locales->forUser($id, readingNow: 'el'))->toBe('el');
});

test('with nothing known, or only a language the site no longer offers, it is the site default', function () {
    expect($this->locales->forUser(userWithLanguage($this->users, null, null)))->toBe('en')
        ->and($this->locales->forUser(userWithLanguage($this->users, null, 'fr')))->toBe('en')
        ->and($this->locales->forUser(999999))->toBe('en');
});

test('an address with an account gets its language, any other address the fallback given', function () {
    userWithLanguage($this->users, 'ar', null, ['email' => 'member@example.test']);

    expect($this->locales->forAddress('member@example.test', 'el'))->toBe('ar')
        ->and($this->locales->forAddress('stranger@example.test', 'el'))->toBe('el')
        ->and($this->locales->forAddress('stranger@example.test'))->toBe('en');
});

test('a blog has the language set in its settings', function () {
    $blogId = BlogFactory::new(new BlogModel($this->db))->create(UserFactory::new($this->users)->create());
    $this->db->execute(
        "INSERT INTO blog_settings (blog_id, default_locale) VALUES (?, 'el') ON DUPLICATE KEY UPDATE default_locale = 'el'",
        [$blogId]
    );

    expect($this->locales->forBlog($blogId))->toBe('el')
        ->and($this->locales->forBlog(999999))->toBe('en');
});

test('each subscriber gets a new post in their own language', function () {
    $ownerId = UserFactory::new($this->users)->create();
    $blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($ownerId);
    $this->db->execute(
        "INSERT INTO blog_settings (blog_id, default_locale) VALUES (?, 'el') ON DUPLICATE KEY UPDATE default_locale = 'el'",
        [$blogId]
    );
    $postId = PostFactory::new(new PostModel($this->db))->published()
        ->withAttributes(['author_id' => $ownerId, 'blog_id' => $blogId])->create();

    $subscribers = new BlogSubscriberModel($this->db);
    $member = userWithLanguage($this->users, 'ar', null, ['email' => 'member@example.test']);
    $subscribers->subscribe($blogId, 'member@example.test', $member, true, 'en');      // account choice wins
    $subscribers->subscribe($blogId, 'reader@example.test', null, true, 'ar');         // the page they subscribed from
    $subscribers->subscribe($blogId, 'old@example.test', null, true);                  // nothing known: the blog's

    $sent = [];
    $queue = Mockery::mock(MailQueueService::class);
    $queue->shouldReceive('enqueue')->andReturnUsing(function (Mailable $mail) use (&$sent): int {
        $sent[array_key_first($mail->getTo())] = [$mail->getLocale(), $mail->getBody()];

        return 1;
    });

    $service = new SubscriberNotificationService(
        $this->db, new PostModel($this->db), new BlogModel($this->db), $subscribers, $queue, $this->locales
    );

    expect($service->notifyPostPublished($postId))->toBe(3)
        ->and($sent['member@example.test'][0])->toBe('ar')
        ->and($sent['member@example.test'][1])->toContain('<html lang="ar" dir="rtl">')
        ->and($sent['reader@example.test'][0])->toBe('ar')
        ->and($sent['old@example.test'][0])->toBe('el')
        ->and($sent['old@example.test'][1])->toContain('<html lang="el" dir="ltr">');
});

test('subscribing again from a page in another language moves the subscription to it', function () {
    $blogId = BlogFactory::new(new BlogModel($this->db))->published()->create(UserFactory::new($this->users)->create());
    $subscribers = new BlogSubscriberModel($this->db);

    $subscribers->subscribe($blogId, 'reader@example.test', null, true, 'el');
    $subscribers->subscribe($blogId, 'reader@example.test', null, true, 'ar');
    $subscribers->subscribe($blogId, 'reader@example.test', null, true);

    expect($subscribers->forBlog($blogId)[0]['locale'])->toBe('ar');
});
