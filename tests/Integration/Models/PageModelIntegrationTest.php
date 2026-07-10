<?php

declare(strict_types=1);

use App\Models\PageModel;
use App\Models\SiteContentModel;

/**
 * Integration tests for the static pages and editable site content models.
 */
beforeEach(function () {
    $this->pageModel = new PageModel($this->db);
    $this->contentModel = new SiteContentModel($this->db);
});

// ============================================================================
// PAGES
// ============================================================================

/**
 * A published page resolves for its own locale.
 */
it('finds a published page by slug and locale', function () {
    $this->pageModel->insert([
        'slug' => 'test-page',
        'locale' => 'en',
        'title' => 'Test Page',
        'content' => '<p>Hello</p>',
        'is_published' => 1,
    ]);

    $page = $this->pageModel->findPublished('test-page', 'en');

    expect($page)->not->toBeNull()
        ->and($page['title'])->toBe('Test Page');
});

/**
 * A missing translation falls back to English instead of a 404.
 */
it('falls back to english when a translation is missing', function () {
    $this->pageModel->insert([
        'slug' => 'fallback-page',
        'locale' => 'en',
        'title' => 'Fallback Page',
        'content' => '<p>English body</p>',
        'is_published' => 1,
    ]);

    $page = $this->pageModel->findPublished('fallback-page', 'el');

    expect($page)->not->toBeNull()
        ->and($page['locale'])->toBe('en');
});

/**
 * The visitor's locale wins over the English fallback when both exist.
 */
it('prefers the visitor locale over the english fallback', function () {
    $this->pageModel->insert([
        'slug' => 'localized-page',
        'locale' => 'en',
        'title' => 'English Title',
        'content' => '<p>en</p>',
        'is_published' => 1,
    ]);
    $this->pageModel->insert([
        'slug' => 'localized-page',
        'locale' => 'el',
        'title' => 'Greek Title',
        'content' => '<p>el</p>',
        'is_published' => 1,
    ]);

    $page = $this->pageModel->findPublished('localized-page', 'el');

    expect($page['title'])->toBe('Greek Title');
});

/**
 * Unpublished pages stay invisible to visitors.
 */
it('does not return unpublished pages', function () {
    $this->pageModel->insert([
        'slug' => 'draft-page',
        'locale' => 'en',
        'title' => 'Draft Page',
        'content' => '<p>Draft</p>',
        'is_published' => 0,
    ]);

    expect($this->pageModel->findPublished('draft-page', 'en'))->toBeNull();
});

// ============================================================================
// SITE CONTENT OVERRIDES
// ============================================================================

/**
 * Locale-specific overrides win, locale-independent rows act as a fallback,
 * and unknown keys return null so the translation default kicks in.
 */
it('resolves site content by locale with fallback to the locale independent row', function () {
    $this->contentModel->setMany(['front.test.title' => 'English title'], 'en');
    $this->contentModel->setMany(['contact.email' => 'team@example.com'], '');

    expect($this->contentModel->get('front.test.title', 'en'))->toBe('English title')
        ->and($this->contentModel->get('front.test.title', 'el'))->toBeNull()
        ->and($this->contentModel->get('contact.email', 'el'))->toBe('team@example.com')
        ->and($this->contentModel->get('front.unknown', 'en'))->toBeNull();
});

/**
 * Saving an empty value deletes the override instead of storing a blank.
 */
it('deletes an override when an empty value is saved', function () {
    $this->contentModel->setMany(['front.test.subtitle' => 'Something'], 'en');
    expect($this->contentModel->get('front.test.subtitle', 'en'))->toBe('Something');

    $this->contentModel->setMany(['front.test.subtitle' => ''], 'en');
    expect($this->contentModel->get('front.test.subtitle', 'en'))->toBeNull();
});

/**
 * getExact never falls back, so the admin form shows each locale's own state.
 */
it('returns exact locale values without fallback for the admin form', function () {
    $this->contentModel->setMany(['contact.phone' => '123456'], '');

    expect($this->contentModel->getExact('contact.phone', 'en'))->toBeNull()
        ->and($this->contentModel->getExact('contact.phone', ''))->toBe('123456');
});
