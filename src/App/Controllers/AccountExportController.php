<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PersonalDataExportService;
use Framework\Core\Response;

/**
 * Lets a person download a copy of the personal data the site holds about them.
 */
final class AccountExportController extends AppController
{
    public function __construct(
        private PersonalDataExportService $export,
    ) {}

    public function download(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $userId = (int) $user['id'];

        $json = json_encode(
            $this->export->export($userId),
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        audit()->log($userId, 'user.data_exported', 'user', $userId, [], $this->request->ip());

        $this->response->setBody($json);
        $this->response->addHeader('Content-Type', 'application/json; charset=utf-8');
        $this->response->addHeader('Content-Disposition', 'attachment; filename="lexicon-data-'.$user['handle'].'-'.gmdate('Y-m-d').'.json"');
        $this->response->addHeader('Cache-Control', 'private, no-store');

        return $this->response;
    }
}
