<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ModerationCategoryModel;
use Framework\Core\Response;

/**
 * The reasons a reader can pick when reporting, for the report dialog.
 *
 * Served on request rather than baked into the page, because public pages
 * come from the full-page cache and would keep offering a retired reason.
 */
class ReportReasonController extends AppController
{
    public function __construct(private ModerationCategoryModel $categories) {}

    public function index(): Response
    {
        $reasons = array_map(static fn (array $category): array => [
            'slug' => (string) $category['slug'],
            'label' => (string) $category['label'],
            'description' => (string) ($category['description'] ?? ''),
        ], $this->categories->active());

        return $this->jsonSuccess($reasons);
    }
}
