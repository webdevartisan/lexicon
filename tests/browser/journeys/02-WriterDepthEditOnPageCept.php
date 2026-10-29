<?php

declare(strict_types=1);

use Facebook\WebDriver\Exception\StaleElementReferenceException;
use Support\JourneyTester;

$I = new JourneyTester($scenario);
$I->wantTo('edit my post where readers see it: toolbar in reach, library images, wrapping and the cover');

$appearance = '/en/dashboard/blog/'.$I->recall('blogId').'/appearance';
$useTheme = static function (string $theme) use ($I, $appearance): void {
    $I->amOnPage($appearance);
    // The active theme has no activate form, which is also how the switch is seen to have happened
    $form = "input[name=\"theme\"][value=\"{$theme}\"]";
    $I->executeJS("var input = document.querySelector('{$form}'); if (input) input.form.requestSubmit();");
    $I->waitForJS("return document.readyState === 'complete' && !document.querySelector('{$form}');", 15);
};

$bar = '[data-edit-bar]';
$body = '[data-edit-body]';
$picker = '#media-picker-modal';
$pop = '.tox-pop';
$textTop = "return Math.round(document.querySelector('{$body}').getBoundingClientRect().top);";
$popClearsHeader = 'return document.querySelector(".tox-pop").getBoundingClientRect().top'
    .' >= document.querySelector("[data-edit-header]").getBoundingClientRect().bottom;';
$inFullView = static fn (string $selector): string => "var r = document.querySelector('{$selector}').getBoundingClientRect();"
    .' return r.top >= 0 && r.bottom <= window.innerHeight && r.left >= 0 && r.right <= window.innerWidth;';
$menuItem = static fn (string $text): array => [
    'xpath' => "//*[contains(@class, 'tox-collection__item')][normalize-space() = '{$text}']",
];
// TinyMCE redraws a pop up each time the selection changes, so a button found a moment ago can be gone by the click
$clickPop = static function (string $label) use ($I, $pop): void {
    $button = "{$pop} button[aria-label=\"{$label}\"]";
    for ($try = 1; ; $try++) {
        $I->waitForElementVisible($button);
        try {
            $I->click($button);

            return;
        } catch (StaleElementReferenceException $redrawn) {
            if ($try === 3) {
                throw $redrawn;
            }
            $I->wait(0.3);
        }
    }
};

$I->signInAs('writer');

// Every theme marks its post page for editing; the walk blog ends on Offset, as other journeys expect
foreach (['folio', 'closest', 'vernissage', 'nocturne', 'offset'] as $theme) {
    $useTheme($theme);
    $I->amOnPage($I->recall('postUrl'));
    $I->declineCookies();

    // Starting to edit leaves the page where it was, even though the empty cover slot appears above the text
    $I->executeJS("document.querySelector('{$body}').scrollIntoView({block: 'start'}); window.scrollBy(0, -40);");
    // Stands in for a theme script that rearranges the text it shows; the editor starts from the stored text
    $I->executeJS("document.querySelector('{$body}').insertAdjacentHTML('beforeend', '<div class=\"theme-made\">Shown, never stored</div>');");
    // The theme slides the text up as it fades in; measure once it has landed
    $I->waitForJS("var el = document.querySelector('{$body}'); return getComputedStyle(el).opacity === '1' && !el.getAnimations().length;", 5);
    $before = $I->executeJS($textTop);
    $I->click('Edit post', $bar);
    $I->waitForElementVisible("{$bar} [data-edit-toolbar] .tox-toolbar", 15);
    $I->waitForElementVisible('[data-edit-cover] .edit-cover-controls');
    $I->assertEqualsWithDelta($before, $I->executeJS($textTop), 2);
    $I->assertFalse($I->executeJS("return document.activeElement === document.querySelector('[data-edit-title]');"));
    $I->dontSeeElementInDOM("{$body} .theme-made");

    // The toolbar lives in the bar, so it is still on screen at the bottom of a long page, and fits a phone
    $I->executeJS('window.scrollTo(0, document.body.scrollHeight);');
    $I->assertTrue($I->executeJS($inFullView($bar)));
    $I->assertTrue($I->executeJS(
        'return window.innerWidth > 640 || document.querySelector("[data-edit-toolbar] .tox-toolbar").getBoundingClientRect().height < 50;'
    ));

    // Paragraph and insert tools in the bar, in the bar's colours; each tool in one place only
    foreach (['Table', 'Align left', 'Align center', 'Align right', 'Bullet list', 'Insert image from library'] as $tool) {
        $I->seeElementInDOM("{$bar} [data-edit-toolbar] button[aria-label=\"{$tool}\"]");
    }
    foreach (['Bold', 'Insert/edit link', 'Left, text wraps on the right'] as $tool) {
        $I->dontSeeElementInDOM("{$bar} .tox-toolbar button[aria-label=\"{$tool}\"]");
    }
    $I->assertSame('rgba(0, 0, 0, 0)', $I->executeJS("return getComputedStyle(document.querySelector('[data-edit-toolbar] button[aria-label=\"Bullet list\"]')).backgroundColor;"));

    // Selecting words offers styling next to them, never under the theme's header
    $I->executeJS(
        "var ed = tinymce.activeEditor, p = ed.getBody().querySelector('p');"
        ." window.scrollBy(0, p.getBoundingClientRect().top - document.querySelector('[data-edit-header]').getBoundingClientRect().bottom - 10);"
        .' ed.focus(); ed.selection.select(p); ed.nodeChanged();'
    );
    $I->waitForElementVisible("{$pop} button[aria-label=\"Bold\"]");
    $I->assertTrue($I->executeJS($popClearsHeader));

    // The link dialog opens over the page, all of it in view
    $clickPop('Insert/edit link');
    $I->waitForElementVisible('.tox-dialog');
    $I->assertTrue($I->executeJS($inFullView('.tox-dialog')));
    $I->click('.tox-dialog button[aria-label="Close"]');
    $I->waitForElementNotVisible('.tox-dialog');

    // Accent is the theme's own colour; Normal takes it off again
    $I->executeJS("var ed = tinymce.activeEditor, p = ed.getBody().querySelector('p'); ed.focus(); ed.selection.select(p); ed.nodeChanged();");
    $clickPop('Text colour');
    $I->click($menuItem('Accent'));
    $I->seeElement("{$body} p:first-of-type span.color-accent");
    // Folio is black and white only, so its Accent keeps the text colour
    if ($theme !== 'folio') {
        $I->assertTrue($I->executeJS(
            "var probe = document.createElement('span'); probe.style.color = 'var(--post-accent)'; document.body.appendChild(probe);"
            .' var accent = getComputedStyle(probe).color; probe.remove();'
            .' return accent !== getComputedStyle(document.body).color'
            ." && getComputedStyle(document.querySelector('{$body} span.color-accent')).color === accent;"
        ));
    }
    $clickPop('Text colour');
    $I->click($menuItem('Normal'));
    $I->dontSeeElement("{$body} .color-accent");

    // The library opens on the blog page, styled, and an upload goes straight into the text
    $I->executeJS("var ed = tinymce.activeEditor; ed.selection.select(ed.getBody().querySelector('p:last-of-type'), true); ed.selection.collapse(false);");
    $I->click("{$bar} button[aria-label=\"Insert image from library\"]");
    $I->waitForElementVisible("{$picker} .media-picker-panel");
    $I->assertNotSame('rgba(0, 0, 0, 0)', $I->executeJS("return getComputedStyle(document.querySelector('{$picker} .media-picker-panel')).backgroundColor;"));

    $image = $I->makeImage('walk-onpage');
    $card = "{$picker} .media-picker-name[title^=\"".pathinfo($image, PATHINFO_FILENAME).'"]';
    $I->attachFile("{$picker} [data-picker-upload]", $image);
    $I->waitForElement($card, 15);
    $url = (string) $I->executeJS("return document.querySelector('{$card}').closest('[data-picker-pick]').getAttribute('data-picker-pick');");
    $I->executeJS("document.querySelector('{$card}').closest('[data-picker-pick]').click();");
    $I->waitForElementNotVisible("{$picker} .media-picker-panel");
    $inserted = "{$body} img[src=\"{$url}\"]";
    $I->waitForElement($inserted);

    // Clicking the image offers wrapping and moving; text then flows around it
    $I->executeJS("document.querySelector('{$inserted}').scrollIntoView({block: 'center'});");
    $I->click($inserted);
    $clickPop('Left, text wraps on the right');
    $I->seeElement("{$inserted}.img-wrap-left");

    // Centre puts it on a line of its own, in the middle, which is also how wrapping is turned off
    $clickPop('Centre, no text beside it');
    $I->seeElement("{$inserted}.img-center");
    $I->dontSeeElement("{$inserted}.img-wrap-left");
    $I->assertTrue($I->executeJS(
        "var img = document.querySelector('{$inserted}'), box = img.getBoundingClientRect(), text = img.parentNode.getBoundingClientRect();"
        .' return Math.abs((box.left - text.left) - (text.right - box.right)) < 3;'
    ));

    // Size and alt text open in full view as well
    $clickPop('Size, alt text and wrap');
    $I->waitForElementVisible('.tox-dialog');
    $I->assertTrue($I->executeJS($inFullView('.tox-dialog')));
    $I->click('.tox-dialog button[aria-label="Close"]');
    $I->waitForElementNotVisible('.tox-dialog');

    // A picture whose top edge sits under the header still gets its tools where they can be seen
    $I->executeJS(
        "var img = document.querySelector('{$inserted}');"
        ." window.scrollBy(0, img.getBoundingClientRect().top - document.querySelector('[data-edit-header]').getBoundingClientRect().bottom + 20);"
    );
    $I->click($inserted);
    $I->waitForElementVisible("{$pop} button[aria-label=\"Move up a paragraph\"]");
    $I->assertTrue($I->executeJS($popClearsHeader));

    $clickPop('Left, text wraps on the right');
    $I->seeElement("{$inserted}.img-wrap-left");

    // Text alignment aligns the paragraph and never floats the picture a second way
    $I->click("{$bar} button[aria-label=\"Align center\"]");
    $I->assertStringNotContainsString('float', (string) $I->executeJS("return document.querySelector('{$inserted}').getAttribute('style') || '';"));
    $I->click("{$bar} button[aria-label=\"Align left\"]");

    // Move down puts the picture in the next paragraph, the way a phone moves it
    $landing = "{$body} p.walk-landing";
    $I->executeJS("var ed = tinymce.activeEditor; ed.dom.add(ed.getBody(), 'p', {'class': 'walk-landing'}, 'Drop the picture somewhere in this sentence.');");
    $I->click($inserted);
    $clickPop('Move down a paragraph');
    $I->seeElement("{$landing} img[src=\"{$url}\"]");

    if ($I->executeJS('return window.innerWidth > 640;')) {
        // Dragging with a mouse drops it where the pointer lets go, always inside a paragraph
        // The pointer has to press the picture itself, not the edit bar over its lower half
        $I->executeJS("document.querySelector('{$inserted}').scrollIntoView({block: 'center'});");
        $I->dragAndDrop($inserted, "{$body} > p:first-child");
    } else {
        // A finger scrolls instead of dragging, so a phone moves the picture back up with the button
        $I->click($inserted);
        $clickPop('Move up a paragraph');
    }
    $I->dontSeeElement("{$landing} img");
    $I->assertTrue($I->executeJS(
        "var img = document.querySelector('{$inserted}');"
        ." return img.parentNode.nodeName === 'P' && !!(document.querySelector('{$landing}').compareDocumentPosition(img) & Node.DOCUMENT_POSITION_PRECEDING);"
    ));

    // The cover comes from the same library and lands in the theme's own frame
    $I->scrollTo('[data-edit-cover]');
    // "Add a cover", or "Change cover" when an earlier walk stopped before cleaning up
    $I->click('[data-edit-cover] .edit-cover-controls button:first-child');
    $I->waitForElementVisible("{$picker} .media-picker-panel");
    $I->waitForElement($card, 15);
    $I->executeJS("document.querySelector('{$card}').closest('[data-picker-pick]').click();");
    $I->waitForElement("[data-edit-cover] img[src=\"{$url}\"]");
    $I->assertTrue($I->executeJS(
        "var slot = document.querySelector('[data-edit-cover] [data-edit-cover-slot]');"
        ." return !slot || slot.contains(document.querySelector('[data-edit-cover] img'));"
    ));
    $I->see('Remove cover', '[data-edit-cover]');

    $I->click('Save', $bar);
    $I->waitForText('Saved', 15, "{$bar} [data-edit-status]");

    // What readers get after a fresh load
    $I->amOnPage($I->recall('postUrl'));
    $I->seeElement("[data-edit-cover] img[src=\"{$url}\"]");
    // The theme fades the text in once it scrolls into view, and a faded out element does not count as seen
    $I->executeJS("document.querySelector('{$inserted}').scrollIntoView({block: 'center'});");
    $I->waitForElementVisible("{$inserted}.img-wrap-left");
    // Phones show wrapped pictures full width, so the float only shows on a wide screen
    $I->assertSame(
        $I->executeJS('return window.innerWidth > 600;') ? 'left' : 'none',
        $I->executeJS("return getComputedStyle(document.querySelector('{$inserted}')).float;")
    );

    // Take both out again so the walk's post stays as other journeys expect it
    $I->click('Edit post', $bar);
    $I->waitForElementVisible("{$bar} [data-edit-toolbar] .tox-toolbar", 15);
    $I->scrollTo('[data-edit-cover]');
    $I->click('Remove cover', '[data-edit-cover]');
    $I->executeJS("var dom = tinymce.activeEditor.dom; dom.remove(dom.select('img')); dom.remove(dom.select('p').filter(function (p) { return p.textContent.indexOf('Drop the picture') !== -1; })); tinymce.activeEditor.setDirty(true);");
    $I->click('Save', $bar);
    $I->waitForText('Saved', 15, "{$bar} [data-edit-status]");
    $I->dontSeeElement("{$body} img");
    $I->dontSeeElement('[data-edit-cover] img');
}
