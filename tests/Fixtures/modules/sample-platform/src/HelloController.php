<?php

declare(strict_types=1);

namespace Tests\Fixtures\Modules\SamplePlatform;

use App\Controllers\AppController;
use Framework\Core\Response;

/**
 * Plain text pages, enough to prove where the routes are mounted.
 */
final class HelloController extends AppController
{
    public function show(): Response
    {
        return $this->text('Hello from the sample platform extra');
    }

    public function settings(): Response
    {
        return $this->text('Sample platform extra settings');
    }

    private function text(string $body): Response
    {
        $response = new Response();
        $response->setBody($body);

        return $response;
    }
}
