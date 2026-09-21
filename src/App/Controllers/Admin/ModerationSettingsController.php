<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\ModerationCategoryModel;
use App\Services\ModerationConfigService;
use App\Services\ModerationSettings;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * The moderation settings screen: what each report reason does once enough
 * people agree, and the safeguards in front of every automatic action.
 */
class ModerationSettingsController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'configureModeration';

    private const PAGE = '/admin/reports/settings';

    public function __construct(
        private ModerationCategoryModel $categories,
        private ModerationSettings $settings,
        private ModerationConfigService $config,
    ) {}

    public function index(): Response
    {
        return $this->view('report.settings', [
            'categories' => $this->categories->ordered(),
            'safeguards' => $this->settings->storedValues(),
            'bounds' => ModerationSettings::KEYS,
        ]);
    }

    public function updateReason(string $slug): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $current = $this->categories->findBySlug($slug)
            ?? throw new PageNotFoundException("Report reason '{$slug}' not found.");

        $checked = $this->config->checkCategory($current, $this->request->post, $this->actorId());

        if ($checked['errors'] !== []) {
            return $this->refuse($checked['errors']);
        }

        $changes = $this->config->saveCategory($current, $checked['fields'], $this->actorId(), $this->request->ip());

        $this->flash('success', $changes === []
            ? 'Nothing was changed.'
            : '"'.$checked['fields']['label'].'" saved.');

        return $this->redirect(self::PAGE);
    }

    public function updateSafeguards(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $checked = $this->config->checkSafeguards($this->request->post);

        if ($checked['errors'] !== []) {
            return $this->refuse($checked['errors']);
        }

        $changes = $this->config->saveSafeguards($checked['values'], $this->actorId(), $this->request->ip());

        $this->flash('success', $changes === [] ? 'Nothing was changed.' : 'Safeguards saved.');

        return $this->redirect(self::PAGE);
    }

    /**
     * Send the form back with its errors and what was typed, the way a failed
     * validateOrFail() does, so the right modal reopens filled in.
     *
     * @param  array<string, string[]>  $errors
     */
    private function refuse(array $errors): Response
    {
        $input = $this->request->post;
        unset($input['_token']);

        $this->session->set('_errors', $errors);
        $this->session->set('_old_input', $input);
        $this->flash('error', 'Nothing was saved. Please correct the errors and try again.');

        return $this->redirect(self::PAGE);
    }

    private function actorId(): int
    {
        return (int) (auth()->user()['id'] ?? 0);
    }
}
