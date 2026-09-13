<?php

declare(strict_types=1);

test('a name and a tag render together inside one link', function () {
    $html = author_byline('Admin User', 'theboss', 'theboss');

    expect($html)->toContain('>Admin User<')
        ->and($html)->toContain('>@theboss<')
        ->and(substr_count($html, '<a '))->toBe(1)
        ->and($html)->toContain('rel="author"');
});

test('a name equal to the tag prints only once', function () {
    $html = author_byline('theboss', 'theboss', 'theboss');

    expect($html)->toContain('>@theboss<')
        ->and($html)->not->toContain('lx-byline-name')
        ->and(substr_count($html, 'theboss'))->toBe(2); // the href and the tag
});

test('no slug means no link, but still a tag', function () {
    $html = author_byline('Admin User', 'theboss', null);

    expect($html)->not->toContain('<a ')
        ->and($html)->toContain('>@theboss<')
        ->and($html)->toContain('Admin User');
});

test('a guest with no handle keeps a plain name', function () {
    expect(author_byline('Some Guest', null, null))->toBe('Some Guest');
});

test('the name and tag are escaped', function () {
    $html = author_byline('<script>x</script>', 'a&b', null);

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&amp;b');
});

test('an extra class lands on the anchor without losing the byline class', function () {
    $html = author_byline('Admin User', 'theboss', 'theboss', 'card-author');

    expect($html)->toContain('class="lx-byline card-author"');
});
