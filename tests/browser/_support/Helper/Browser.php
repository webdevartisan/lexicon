<?php

declare(strict_types=1);

namespace Support\Helper;

use Codeception\Module\WebDriver;
use Codeception\TestInterface;
use Facebook\WebDriver\Exception\UnknownErrorException;

/**
 * Codeception's WebDriver module, with waits that survive the page being replaced mid-check.
 * ChromeDriver answers "Node with given id does not belong to the document" instead of a
 * stale-element error when a wait looks at a page that is just navigating away.
 */
class Browser extends WebDriver
{
    /**
     * Turn off smooth scrolling on every page this browser opens. Chrome scrolls an element
     * into view before clicking it, and a smooth scroll is still moving when the click lands.
     */
    public function _before(TestInterface $test): void
    {
        parent::_before($test);
        $this->webDriver->executeCustomCommand('/session/:sessionId/goog/cdp/execute', 'POST', [
            'cmd' => 'Page.addScriptToEvaluateOnNewDocument',
            'params' => ['source' => "document.addEventListener('DOMContentLoaded', () =>"
                ." document.documentElement.style.setProperty('scroll-behavior', 'auto', 'important'));"],
        ]);
    }

    public function waitForText(string $text, int $timeout = 10, $selector = null): void
    {
        $this->whilePageSwaps(fn () => parent::waitForText($text, $timeout, $selector));
    }

    public function waitForElementVisible($element, int $timeout = 10): void
    {
        $this->whilePageSwaps(fn () => parent::waitForElementVisible($element, $timeout));
    }

    public function waitForElementNotVisible($element, int $timeout = 10): void
    {
        $this->whilePageSwaps(fn () => parent::waitForElementNotVisible($element, $timeout));
    }

    private function whilePageSwaps(callable $wait): void
    {
        for ($try = 1; ; $try++) {
            try {
                $wait();

                return;
            } catch (UnknownErrorException $e) {
                if ($try >= 5 || !str_contains($e->getMessage(), 'does not belong to the document')) {
                    throw $e;
                }
                usleep(200_000);
            }
        }
    }
}
