<?php

declare(strict_types=1);

namespace Support\Helper;

use Codeception\Module;

/**
 * The few Lexicon actions the built-in WebDriver steps can't say in one line.
 */
class Lexicon extends Module
{
    /**
     * Become a first-time visitor: no cookies, and no pages kept from earlier.
     */
    public function amAGuest(): void
    {
        $driver = $this->browser()->webDriver;
        $driver->manage()->deleteAllCookies();
        // Public pages may be kept by the browser for minutes, which would hide what a new visitor sees
        $driver->executeCustomCommand('/session/:sessionId/goog/cdp/execute', 'POST', [
            'cmd' => 'Network.clearBrowserCache',
            'params' => new \stdClass(),
        ]);
    }

    /**
     * Reject all cookies if the banner is up, and wait out the reload it causes.
     */
    public function declineCookies(): void
    {
        $browser = $this->browser();
        $reject = '[data-consent-action=reject_all]';
        if (!$browser->executeJS("return !!document.querySelector('{$reject}')?.checkVisibility()")) {
            return;
        }
        // The choice reloads the page; a marker on the old window tells us when the new one is in
        $browser->executeJS('window.beforeConsent = true');
        $browser->click('Reject all');
        try {
            $browser->waitForJS("return !window.beforeConsent && document.readyState === 'complete'", 4);
        } catch (\Facebook\WebDriver\Exception\TimeoutException) {
            // Some pages apply the choice without reloading
        }
    }

    /**
     * Bring an element to the middle of the screen. Chrome's own scroll before a click puts it
     * at the top edge, where the dashboard's fixed header can sit over it.
     */
    public function scrollDownTo(string $selector): void
    {
        $browser = $this->browser();
        $browser->waitForElement($selector);
        $browser->executeJS(
            'arguments[0].scrollIntoView({ block: "center", behavior: "instant" })',
            [$browser->_findElements($selector)[0]]
        );
    }

    /**
     * Open the media library's upload panel unless it already shows, as it does on an empty library.
     * Upload messages appear inside it, so a closed panel hides them.
     */
    public function openTheMediaUploader(): void
    {
        $browser = $this->browser();
        if ($browser->executeJS("return document.getElementById('media-upload-panel').classList.contains('hidden')")) {
            $browser->click('#media-upload-toggle');
        }
    }

    /**
     * Open the replies under a comment unless they already show, as they do right after replying.
     */
    public function showRepliesTo(string $commentId): void
    {
        $toggle = "[data-collapse-toggle=\"{$commentId}\"]";
        $this->scrollDownTo($toggle);
        if ($this->browser()->grabAttributeFrom($toggle, 'aria-expanded') === 'false') {
            $this->browser()->click($toggle);
        }
        $this->browser()->waitForElementVisible("#replies-{$commentId}");
    }

    /**
     * The sign-up check solves itself in the browser; wait until it has.
     */
    public function waitForTheSignUpCheck(): void
    {
        // The sign-in window shows "Check complete." and holds its button until then;
        // the sign-up page fills a hidden altcha input instead
        $this->browser()->waitForJS(
            "const status = document.querySelector('dialog[open] [data-captcha-status]');"
            .' if (status) return !status.hidden && /check complete/i.test(status.innerText);'
            ." const answers = [...document.querySelectorAll('input[name=altcha]')];"
            .' return answers.length === 0 || answers.some(a => a.value);',
            20
        );
    }

    /**
     * Save a screenshot under the given name and fail if the page scrolls sideways.
     */
    public function seeThePageLooksRight(string $name): void
    {
        $browser = $this->browser();
        $browser->makeScreenshot($name);
        $wide = $browser->executeJS('return document.documentElement.scrollWidth - window.innerWidth');
        $this->assertLessThanOrEqual(1, $wide, "The page scrolls sideways by {$wide}px");
    }

    /**
     * Become a person an earlier journey signed up: their saved session if this run has
     * one, otherwise through the login page with the address and password they used.
     * The admin comes from WALK_ADMIN_EMAIL and WALK_ADMIN_PASSWORD, never from the run file.
     */
    public function signInAs(string $person): void
    {
        [$email, $password] = $person === 'admin'
            ? [getenv('WALK_ADMIN_EMAIL'), getenv('WALK_ADMIN_PASSWORD')]
            : [$this->recall("{$person}Email"), $this->recall("{$person}Password")];

        $browser = $this->browser();
        // Cookies can only be set once the browser is on the site
        $browser->amOnPage('/en');
        if ($browser->loadSessionSnapshot($person)) {
            $browser->amOnPage('/en/account/profile');
            if (!str_contains($browser->grabFromCurrentUrl(), '/login')) {
                return;
            }
        }

        $this->amAGuest();
        $browser->amOnPage('/en/login');
        $this->declineCookies();
        $browser->fillField('Email address', $email);
        $browser->fillField('Password', $password);
        $this->waitForTheSignUpCheck();
        $browser->click('Sign in', 'form[action="/login"]');
        $browser->waitForJS("return !location.pathname.endsWith('/login')", 15);
        $browser->saveSessionSnapshot($person);
    }

    /**
     * Draw a plain PNG to upload and return its name in the data folder. The stamp makes
     * every file different, so the media library never sees a repeat.
     */
    public function makeImage(string $name, string $color = '#3b6e8f'): string
    {
        $file = $name.'-'.uniqid().'.png';
        [$r, $g, $b] = sscanf($color, '#%02x%02x%02x');
        $image = imagecreatetruecolor(1200, 630);
        imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));
        imagestring($image, 5, 40, 40, $file, imagecolorallocate($image, 255, 255, 255));
        imagepng($image, codecept_data_dir($file));

        return $file;
    }

    /**
     * Ask for the "Download my data" export from the open account page and return what came back.
     * WebDriver can't catch a browser download, so the page fetches it with its own cookies.
     */
    public function grabMyDataDownload(): string
    {
        return (string) $this->browser()->executeAsyncJS(
            'const done = arguments[arguments.length - 1];'
            .' const form = document.querySelector(\'form[action$="/account/export"]\');'
            ." fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })"
            .'   .then(response => response.text()).then(done, error => done(String(error)));'
        );
    }

    /**
     * Download the blog archive from the open Backup & restore page and return the zip's name
     * in the data folder, ready to attach to the import form. The page fetches it with its own cookies.
     */
    public function grabBlogArchive(): string
    {
        $base64 = (string) $this->browser()->executeAsyncJS(
            'const done = arguments[arguments.length - 1];'
            .' const form = document.querySelector(\'form[action$="/backup/export"]\');'
            ." fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })"
            .'   .then(response => response.blob())'
            .'   .then(blob => { const reader = new FileReader();'
            .'     reader.onload = () => done(String(reader.result).split(",")[1] || "");'
            .'     reader.readAsDataURL(blob); }, error => done(""));'
        );
        $this->assertNotSame('', $base64, 'The archive download came back empty');

        $file = 'walk-archive-'.uniqid().'.zip';
        file_put_contents(codecept_data_dir($file), base64_decode($base64));

        return $file;
    }

    /**
     * Confirm a new account the way its owner would: open the link in the welcome mail.
     * There is no mail catcher, so the link comes from the queued copy in the database.
     */
    public function followTheConfirmLinkMailedTo(string $email): void
    {
        preg_match_all('/^(DB_HOST|DB_NAME|DB_USER|DB_PASSWORD)=(.*)$/m', file_get_contents(dirname(__DIR__, 4).'/.env'), $found);
        $env = array_map(fn ($value) => trim($value, " \"'"), array_combine($found[1], $found[2]));
        $pdo = new \PDO("mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4", $env['DB_USER'], $env['DB_PASSWORD']);
        $statement = $pdo->prepare('SELECT body_html FROM mail_queue WHERE to_email = ? ORDER BY id DESC LIMIT 20');
        $statement->execute([$email]);

        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) as $body) {
            if (preg_match('#/verify-email/([a-f0-9]{64})#', $body, $link)) {
                $this->browser()->amOnPage('/en/verify-email/'.$link[1]);

                return;
            }
        }

        $this->fail("No confirm link was queued for {$email}");
    }

    /**
     * Run one of the site's own console commands, as the scheduler would, and return what it printed.
     */
    public function runConsoleCommand(string $command): string
    {
        $php = getenv('WALK_PHP') ?: PHP_BINARY;
        exec(escapeshellarg($php).' '.escapeshellarg(dirname(__DIR__, 4).'/cli').' '.$command.' 2>&1', $lines, $code);
        $this->assertSame(0, $code, "'{$command}' failed: ".implode("\n", $lines));

        return implode("\n", $lines);
    }

    /**
     * Keep a value for the journeys that run after this one, like the writer's post address.
     */
    public function remember(string $key, string $value): void
    {
        $run = $this->readRun();
        $run[$key] = $value;
        file_put_contents($this->runFile(), json_encode($run, JSON_PRETTY_PRINT));
    }

    /**
     * Read a value an earlier journey remembered.
     */
    public function recall(string $key): string
    {
        $run = $this->readRun();
        if (!isset($run[$key])) {
            $this->fail("Nothing remembered as '{$key}'. Run the whole suite so the writer journey goes first.");
        }

        return $run[$key];
    }

    private function readRun(): array
    {
        $file = $this->runFile();

        return is_file($file) ? json_decode(file_get_contents($file), true) : [];
    }

    private function runFile(): string
    {
        return codecept_output_dir('run.json');
    }

    private function browser(): Browser
    {
        return $this->getModule(Browser::class);
    }
}
