<?php

declare(strict_types=1);

use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('reword my blog home where readers see it, in every theme');

$appearance = '/en/dashboard/blog/'.$I->recall('blogId').'/appearance';
$home = '/en/blog/'.$I->recall('blogSlug');
$useTheme = static function (string $theme) use ($I, $appearance): void {
    $I->amOnPage($appearance);
    $form = "input[name=\"theme\"][value=\"{$theme}\"]";
    $I->executeJS("var input = document.querySelector('{$form}'); if (input) input.form.requestSubmit();");
    $I->waitForJS("return document.readyState === 'complete' && !document.querySelector('{$form}');", 15);
};

$bar = '[data-edit-bar]';
$tagline = '[data-edit-text="tagline"]';
// Puts the cursor over all of a text, so typing replaces it the way a person would
$selectAll = static fn (string $selector): string => "var el = document.querySelector('{$selector}');"
    ." el.scrollIntoView({block: 'center'}); el.focus(); var range = document.createRange(); range.selectNodeContents(el);"
    .' var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range);';
// On a phone the header links wait behind the menu button
$reveal = static fn (string $selector): string => "var toggle = document.querySelector('.nav-toggle');"
    ." if (document.querySelector('{$selector}').offsetParent === null && toggle && toggle.offsetParent"
    ." && toggle.getAttribute('aria-expanded') === 'false') toggle.click();";
// The reset arrow beside a theme text gives it the theme's wording back
$resetText = static fn (string $selector): string => "document.querySelector('".str_replace('data-edit-text', 'data-edit-reset-for', $selector)."').click();";
$pseudo = static fn (string $selector, string $which): string => "return getComputedStyle(document.querySelector('{$selector}'), '{$which}').content;";
$saveAndReload = static function () use ($I, $bar): void {
    $I->executeJS('window.__beforeSave = true;');
    $I->click('Save', $bar);
    // Saving swaps the new texts into the page without leaving it
    $I->waitForText('Saved', 15, "{$bar} [data-edit-status]");
    $I->assertTrue($I->executeJS("return window.__beforeSave === true && !document.documentElement.classList.contains('is-editing-in-place');"));
    // What the page shows now must be what a reload draws, so note it before reloading
    $drawn = "return JSON.stringify(Array.prototype.map.call(document.querySelectorAll('[data-edit-text]'),"
        ." function (el) { return el.getAttribute('data-edit-text') + '=' + el.hidden + ':' + el.innerHTML.trim(); }));";
    $swapped = $I->executeJS($drawn);
    // Nothing is left unsaved, so a reload goes ahead without the browser asking to leave
    $I->executeJS("window.addEventListener('beforeunload', function (e) { sessionStorage.setItem('walkAskedToLeave', String(e.defaultPrevented)); });"
        .' location.reload();');
    $I->waitForJS("return document.readyState === 'complete' && !window.__beforeSave && !!document.querySelector('{$bar} [data-edit-start]');", 15);
    $I->assertSame('false', $I->executeJS("var asked = sessionStorage.getItem('walkAskedToLeave'); sessionStorage.removeItem('walkAskedToLeave'); return asked;"));
    $I->assertSame($I->executeJS($drawn), $swapped);
};

$I->signInAs('writer');

// The walk blog ends on Offset, as other journeys expect
foreach (['folio', 'closest', 'vernissage', 'nocturne', 'offset'] as $theme) {
    $useTheme($theme);
    $I->amOnPage($home);
    $I->declineCookies();

    // All six texts are marked, and nothing on the page can be typed into before editing starts
    $I->assertSame(6, $I->executeJS("return document.querySelectorAll('[data-edit-text]:not([data-edit-template])').length;"));
    $I->dontSeeElement('[data-edit-text][contenteditable]');

    $I->click('Edit page', $bar);
    $I->seeElement('html.is-editing-in-place');
    $I->assertSame(6, $I->executeJS("return document.querySelectorAll('[data-edit-text]:not([data-edit-template])[contenteditable]:not([hidden])').length;"));
    $I->see('All settings', $bar);
    // Some themes fade their hero texts in after the page loads
    $I->waitForJS("return getComputedStyle(document.querySelector('{$tagline}')).opacity === '1';", 5);
    $I->makeScreenshot("front-texts-{$theme}");
    // Each text carries a pencil
    $I->assertSame('""', $I->executeJS($pseudo($tagline, '::after')));
    $I->assertNotSame('none', $I->executeJS("return getComputedStyle(document.querySelector('{$tagline}'), '::after').maskImage;"));
    // Even beside the biggest heading the pencil stays a small mark
    $I->assertLessThanOrEqual(20, (float) $I->executeJS("return Math.max.apply(null, Array.prototype.map.call(document.querySelectorAll('[data-edit-text]'),"
        ." function (el) { return parseFloat(getComputedStyle(el, '::after').width) || 0; }));"));

    // Typing over the tagline and saving shows it as readers will see it
    $I->executeJS($selectAll($tagline));
    $I->type("Walk tagline {$theme}");
    $saveAndReload();
    $I->assertSame("Walk tagline {$theme}", $I->executeJS("return document.querySelector('{$tagline}').textContent.trim();"));
    $I->dontSeeElement('html.is-editing-in-place');

    // Cancel puts the page back as it was
    $I->click('Edit page', $bar);
    $I->executeJS($selectAll('[data-edit-text="newsletter_text"]'));
    $I->type('Not kept');
    $I->executeJS('window.blogConfirm = function () { return Promise.resolve(true); };');
    $I->click('Cancel', $bar);
    $I->dontSee('Not kept');
    $I->dontSeeElement('[data-edit-text][contenteditable]');

    // A cleared text shows the theme's own wording faded while editing, and readers get it back after saving
    $I->click('Edit page', $bar);
    $I->executeJS($selectAll($tagline).' document.execCommand("delete");');
    $I->assertSame('', $I->executeJS("return document.querySelector('{$tagline}').textContent;"));
    $default = (string) $I->executeJS("return document.querySelector('{$tagline}').getAttribute('data-edit-default');");
    $I->assertSame($default === '' ? '"Tagline"' : json_encode($default, JSON_UNESCAPED_UNICODE), $I->executeJS($pseudo($tagline, '::before')));
    $saveAndReload();
    $I->dontSee("Walk tagline {$theme}");
    // Closest shows no tagline at all until the owner writes one
    $I->assertSame(
        $theme === 'closest',
        $I->executeJS("var el = document.querySelector('{$tagline}'); return el === null || el.hidden;")
    );
}

// Nocturne lets the owner reword its own texts too: headings on the home, the archive, and the footer on every page
$edition = '[data-edit-text="nocturne::home.edition"]';
$desk = '[data-edit-text="nocturne::home.desk"]';
$heading = '[data-edit-text="nocturne::archive.heading"]';
$footer = '[data-edit-text="nocturne::footer.noteMore"]';
$useTheme('nocturne');
$I->amOnPage($home);

$I->click('Edit page', $bar);
// A theme text starts from the theme's wording, so a small change needs no retyping
$I->assertSame('The Night Edition', $I->executeJS("return document.querySelector('{$edition}').textContent;"));
// The owner's name sits in the text as a locked tag
$I->seeElementInDOM("{$desk} .edit-token[data-token=\"owner\"][contenteditable=\"false\"]");
$I->executeJS($selectAll($edition));
$I->type('The Morning Edition');
$saveAndReload();
$I->see('The Morning Edition', $edition);

// A changed text offers its own reset; Reset page puts every text back; nothing is saved until Save
$resetEdition = '[data-edit-reset-for="nocturne::home.edition"]';
$I->click('Edit page', $bar);
// The saved wording differs from the theme's, so its reset shows; one click and it is the theme's again
$I->waitForElementVisible($resetEdition);
$I->click($resetEdition);
$I->assertSame('The Night Edition', $I->executeJS("return document.querySelector('{$edition}').textContent;"));
$I->waitForElementNotVisible($resetEdition);
$I->executeJS($selectAll($edition));
$I->type('The Evening Edition');
$I->executeJS($selectAll($tagline));
$I->type('Walk tagline reset');
$I->click('Reset page', $bar);
$I->waitForElementVisible('dialog.blog-confirm');
$I->click('dialog.blog-confirm .blog-confirm-ok');
// The dialog answers once it has closed
$I->waitForJS("return document.querySelector('{$edition}').textContent === 'The Night Edition'"
    ." && document.querySelector('{$tagline}').textContent === '';", 5);
// Cancel asks first, in the site's dialog, because there are changes to lose
$I->click('Cancel', $bar);
$I->waitForElementVisible('dialog.blog-confirm');
$I->click('dialog.blog-confirm .blog-confirm-ok');
// The rail shows it in capitals, so the stored words are compared, not the visible ones
$I->waitForJS("return document.querySelector('{$edition}').textContent === 'The Morning Edition';", 5);

// Leaving by a link on the page asks in the site's own dialog, and staying keeps the edits
$I->click('Edit page', $bar);
$I->executeJS($selectAll($edition));
$I->type('Unsaved edition');
$I->executeJS("document.querySelector('a[href*=\"/archive\"]').click();");
$I->waitForElementVisible('dialog.blog-confirm');
$I->click('dialog.blog-confirm .blog-confirm-button:not(.blog-confirm-ok)');
$I->seeInCurrentUrl($home);
$I->see('Unsaved edition', $edition);
$I->executeJS('window.blogConfirm = function () { return Promise.resolve(true); };');
$I->click('Cancel', $bar);

// The archive's heading
$I->amOnPage($home.'/archive');
$I->click('Edit page', $bar);
$I->executeJS($selectAll($heading));
$I->type('Every Morning, Filed.');
$saveAndReload();
$I->see('Every Morning, Filed.', $heading);

// On a post the footer joins the post's own edit and saves with it
$I->amOnPage($I->recall('postUrl'));
$I->click('Edit post', $bar);
$I->waitForElementVisible("{$bar} [data-edit-toolbar] .tox-toolbar", 15);
$I->seeElement("{$footer}[contenteditable]");
$I->executeJS($selectAll($footer));
$I->type('Read it at breakfast.');
$I->click('Save', $bar);
$I->waitForText('Saved', 15, "{$bar} [data-edit-status]");
$I->amOnPage($I->recall('postUrl'));
$I->see('Read it at breakfast.', $footer);

// Resetting a theme text gives the theme's wording back; the walk blog keeps none of its own
foreach ([[$home, $edition, 'The Night Edition'], [$home.'/archive', $heading, 'Every Night, Filed.']] as [$page, $text, $theirs]) {
    $I->amOnPage($page);
    $I->click('Edit page', $bar);
    $I->executeJS($resetText($text));
    if ($text === $edition) {
        $I->executeJS($resetText($footer));
    }
    $saveAndReload();
    $I->assertSame($theirs, $I->executeJS("return document.querySelector('{$text}').textContent.trim();"));
}
$I->see('Read whenever you like.', $footer);

// Folio's own texts work the same way
$indexTitle = '[data-edit-text="folio::home.indexTitle"]';
$featured = '[data-edit-text="folio::home.featuredLine"]';
$continue = '[data-edit-text="folio::home.continue"]';
$shelf = '[data-edit-text="folio::archive.heading"]';
$useTheme('folio');
$I->amOnPage($home);

$I->click('Edit page', $bar);
$I->seeElementInDOM("{$continue} .edit-token[data-token=\"title\"][contenteditable=\"false\"]");
$I->executeJS($selectAll($indexTitle));
$I->type('The Shelf');
$I->executeJS($selectAll($featured));
$I->type('Picked');
// Adding to the end of the long read line keeps the post title it names
$I->executeJS("var el = document.querySelector('{$continue}'); el.scrollIntoView({block: 'center'}); el.focus();"
    .' var range = document.createRange(); range.selectNodeContents(el); range.collapse(false);'
    .' var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range);');
$I->type(' Take your time.');
$saveAndReload();
$I->assertSame('The Shelf', $I->executeJS("return document.querySelector('{$indexTitle}').textContent;"));
// Only the label is the owner's; the reading time beside it stays the theme's
$I->assertMatchesRegularExpression('/^Picked · \d+ Min$/', (string) $I->executeJS("return document.querySelector('{$featured}').parentNode.textContent.trim();"));
$I->seeElement("{$continue} em");
$I->assertStringEndsWith('Take your time.', (string) $I->executeJS("return document.querySelector('{$continue}').textContent.trim();"));

$I->amOnPage($home.'/archive');
$I->click('Edit page', $bar);
$I->executeJS($selectAll($shelf));
$I->type('Everything, Filed.');
$saveAndReload();
$I->assertSame('Everything, Filed.', $I->executeJS("return document.querySelector('{$shelf}').textContent;"));

foreach ([
    $home => [$indexTitle => 'The Index', $featured => 'Featured', $continue => null],
    $home.'/archive' => [$shelf => 'The Full Index.'],
] as $page => $texts) {
    $I->amOnPage($page);
    $I->click('Edit page', $bar);
    foreach (array_keys($texts) as $text) {
        $I->executeJS($resetText($text));
    }
    $saveAndReload();
    foreach ($texts as $text => $theirs) {
        $shown = (string) $I->executeJS("return document.querySelector('{$text}').textContent.trim();");
        $theirs === null
            ? $I->assertStringEndsWith('slow, deliberate form.', $shown)
            : $I->assertSame($theirs, $shown);
    }
}

// The other themes' own texts: plain lines, labels beside data, a header link, a filter tab, a form label and a button
foreach ([
    'closest' => [
        'closest::home.keyDot' => 'One published waypoint',
        'closest::home.youAreHere' => 'You are here',
        'closest::nav.home' => 'Trailhead',
        'closest::home.all' => 'Full route',
        'closest::emailLabel' => 'Your address',
        'closest::home.surveyedSinceLine' => 'Surveyed since',
    ],
    'offset' => ['offset::home.leadStoryLine' => 'Lead story', 'offset::home.est' => 'Est.'],
    'vernissage' => ['vernissage::home.subscribe' => 'RSVP', 'vernissage::footer.hours' => 'Open daily. Admission free'],
] as $theme => $texts) {
    $useTheme($theme);
    $I->amOnPage($home);
    $I->click('Edit page', $bar);
    foreach (array_keys($texts) as $key) {
        $text = "[data-edit-text=\"{$key}\"]";
        $I->executeJS($reveal($text));
        // A click on a text inside a button or link places the cursor and sends nothing
        $I->executeJS("document.querySelector('{$text}').click();");
        $I->executeJS($selectAll($text));
        $I->type("Walk {$theme}");
    }
    $I->seeElement('html.is-editing-in-place');
    $saveAndReload();
    foreach (array_keys($texts) as $key) {
        $I->assertSame("Walk {$theme}", $I->executeJS("return document.querySelector('[data-edit-text=\"{$key}\"]').textContent;"));
    }
    if ($theme === 'closest') {
        // The founding year after the label is still its own text, and the filter tab did not filter
        $I->seeElement('.legend-est [data-edit-text="founded_year"]');
        $I->seeElement('.filter-list li.is-active [data-edit-text="closest::home.all"]');
    }
    if ($theme === 'offset') {
        // The category beside the label stays the post's
        $I->assertMatchesRegularExpression('/^Walk offset · \S/', (string) $I->executeJS(
            "return document.querySelector('[data-edit-text=\"offset::home.leadStoryLine\"]').parentNode.textContent.trim();"
        ));
    }

    $I->click('Edit page', $bar);
    foreach (array_keys($texts) as $key) {
        $I->executeJS($resetText("[data-edit-text=\"{$key}\"]"));
    }
    $saveAndReload();
    foreach ($texts as $key => $theirs) {
        $I->assertSame($theirs, $I->executeJS("return document.querySelector('[data-edit-text=\"{$key}\"]').textContent.trim();"));
    }
}

// Emptying a text removes it for readers, while a link keeps needing words
$removed = '[data-edit-text="offset::home.colophonEyebrow"]';
$link = '[data-edit-text="offset::nav.home"]';
$shownAs = static fn (string $selector): string => "return getComputedStyle(document.querySelector('{$selector}')).display;";
$useTheme('offset');
$I->amOnPage($home);
$I->click('Edit page', $bar);
$I->executeJS($selectAll($removed).' document.execCommand("delete");');
$I->executeJS($reveal($link));
$I->executeJS($selectAll($link).' document.execCommand("delete");');
$I->executeJS("document.querySelector('{$link}').blur();");
$I->assertSame('Home', $I->executeJS("return document.querySelector('{$link}').textContent;"));
$I->see('needs words', "{$bar} [data-edit-status]");
$saveAndReload();
$I->assertSame('none', $I->executeJS($shownAs($removed)));

// While editing, the removed text waits as an empty box; its reset arrow brings the theme wording back
$I->click('Edit page', $bar);
$I->assertNotSame('none', $I->executeJS($shownAs($removed)));
$I->assertSame('', $I->executeJS("return document.querySelector('{$removed}').textContent;"));
$I->executeJS("document.querySelector('[data-edit-reset-for=\"offset::home.colophonEyebrow\"]').click();");
$saveAndReload();
$I->assertSame('Colophon · About this blog', $I->executeJS("return document.querySelector('{$removed}').textContent.trim();"));
$I->assertNotSame('none', $I->executeJS($shownAs($removed)));
